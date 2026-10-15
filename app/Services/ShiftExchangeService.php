<?php

namespace App\Services;

use App\Models\Shift;
use App\Models\ShiftExchange;
use App\Models\User;
use App\Support\Access;
use App\Support\Audit;
use App\Support\Period;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ShiftExchangeService
{
    /**
     * Mengajukan pengalihan jadwal shift searah (Karyawan A digantikan Karyawan B).
     */
    public function requestReplacement(User $requester, Shift $shift, User $replacement, string $reason): ShiftExchange
    {
        if (! Access::admin() && ! Access::manager()) {
            abort_unless($requester->id === $shift->user_id, 403, 'Anda hanya dapat mengajukan pergantian untuk shift milik Anda sendiri.');
        }

        abort_if($shift->user_id === $replacement->id, 422, 'Tidak dapat menukar jadwal dengan diri sendiri.');
        abort_unless($replacement->active, 422, 'Karyawan pengganti tidak aktif.');

        if (! Access::admin()) {
            abort_unless($replacement->branch_id === $shift->branch_id, 422, 'Karyawan pengganti harus terdaftar di cabang yang sama.');
        }

        Period::writable($shift->branch_id, $shift->start_at->toDateString());

        abort_if($shift->attendance()->exists(), 409, 'Shift sudah memiliki rekaman absensi dan tidak dapat dialihkan.');
        abort_if($shift->exceptions()->exists(), 409, 'Shift memiliki tiket kendala presensi aktif dan tidak dapat dialihkan.');

        // Overlap check untuk karyawan pengganti
        $conflict = Shift::where('user_id', $replacement->id)
            ->where('start_at', '<', $shift->end_at)
            ->where('end_at', '>', $shift->start_at)
            ->exists();
        abort_if($conflict, 409, 'Karyawan pengganti memiliki jadwal shift lain yang tumpang tindih pada jam tersebut.');

        // Cek apakah shift ini sudah memiliki permohonan yang aktif
        $hasActive = ShiftExchange::where('requester_shift_id', $shift->id)
            ->whereIn('status', ['pending_peer', 'pending_manager'])
            ->exists();
        abort_if($hasActive, 409, 'Shift ini sudah memiliki permohonan pergantian yang sedang diproses.');

        $exchange = ShiftExchange::create([
            'type'               => 'replacement',
            'branch_id'          => $shift->branch_id,
            'requester_id'       => $shift->user_id,
            'requester_shift_id' => $shift->id,
            'replacement_id'     => $replacement->id,
            'target_shift_id'    => null,
            'reason'             => $reason,
            'status'             => 'pending_peer',
        ]);

        Audit::record('shift_exchange', $exchange->id, 'request_replacement', $reason);

        return $exchange;
    }

    /**
     * Mengajukan pertukaran jadwal shift dua arah (Shift A ditukar dengan Shift B).
     */
    public function requestSwap(User $requester, Shift $requesterShift, Shift $targetShift, string $reason): ShiftExchange
    {
        if (! Access::admin() && ! Access::manager()) {
            abort_unless($requester->id === $requesterShift->user_id, 403, 'Anda hanya dapat menukar shift milik Anda sendiri.');
        }

        abort_if($requesterShift->user_id === $targetShift->user_id, 422, 'Tidak dapat menukar shift dengan karyawan yang sama atau diri sendiri.');
        
        $replacement = $targetShift->user;
        abort_unless($replacement && $replacement->active, 422, 'Karyawan target tidak aktif.');

        if (! Access::admin()) {
            abort_unless($requesterShift->branch_id === $targetShift->branch_id, 422, 'Pertukaran shift hanya berlaku pada cabang yang sama.');
        }

        Period::writable($requesterShift->branch_id, $requesterShift->start_at->toDateString());
        Period::writable($targetShift->branch_id, $targetShift->start_at->toDateString());

        abort_if($requesterShift->attendance()->exists() || $targetShift->attendance()->exists(), 409, 'Shift yang sudah memiliki rekaman absensi tidak dapat ditukar.');
        abort_if($requesterShift->exceptions()->exists() || $targetShift->exceptions()->exists(), 409, 'Shift dengan kendala presensi tidak dapat ditukar.');

        // Overlap check: Pemohon tidak boleh bentrok dengan targetShift (selain requesterShift miliknya)
        $conflictRequester = Shift::where('user_id', $requesterShift->user_id)
            ->where('id', '!=', $requesterShift->id)
            ->where('start_at', '<', $targetShift->end_at)
            ->where('end_at', '>', $targetShift->start_at)
            ->exists();
        abort_if($conflictRequester, 409, 'Jadwal target bertabrakan dengan jadwal shift lain yang Anda miliki.');

        // Overlap check: Rekan target tidak boleh bentrok dengan requesterShift (selain targetShift miliknya)
        $conflictReplacement = Shift::where('user_id', $targetShift->user_id)
            ->where('id', '!=', $targetShift->id)
            ->where('start_at', '<', $requesterShift->end_at)
            ->where('end_at', '>', $requesterShift->start_at)
            ->exists();
        abort_if($conflictReplacement, 409, 'Karyawan target memiliki jadwal shift lain yang bertabrakan dengan shift Anda.');

        // Cek permohonan aktif pada kedua shift
        $hasActive = ShiftExchange::where(function ($q) use ($requesterShift, $targetShift) {
            $q->whereIn('requester_shift_id', [$requesterShift->id, $targetShift->id])
              ->orWhereIn('target_shift_id', [$requesterShift->id, $targetShift->id]);
        })->whereIn('status', ['pending_peer', 'pending_manager'])->exists();
        abort_if($hasActive, 409, 'Salah satu shift sudah memiliki permohonan pertukaran yang sedang berjalan.');

        $exchange = ShiftExchange::create([
            'type'               => 'swap',
            'branch_id'          => $requesterShift->branch_id,
            'requester_id'       => $requesterShift->user_id,
            'requester_shift_id' => $requesterShift->id,
            'replacement_id'     => $targetShift->user_id,
            'target_shift_id'    => $targetShift->id,
            'reason'             => $reason,
            'status'             => 'pending_peer',
        ]);

        Audit::record('shift_exchange', $exchange->id, 'request_swap', $reason);

        return $exchange;
    }

    /**
     * Mengambil daftar jadwal kosong (Open Shifts) yang tersedia pada cabang
     * khusus untuk posisi/level setingkat karyawan pemohon.
     *
     * @return \Illuminate\Support\Collection<int, Shift>
     */
    public function getAvailableOpenSlots(User $user, ?Carbon $startDate = null, ?Carbon $endDate = null)
    {
        $start = $startDate ? $startDate->copy()->startOfDay() : now('Asia/Jakarta')->startOfDay();
        $end = $endDate ? $endDate->copy()->endOfDay() : now('Asia/Jakarta')->addDays(35)->endOfDay();

        return Shift::with(['branch', 'position'])
            ->whereNull('user_id')
            ->where('branch_id', $user->branch_id)
            ->where('end_at', '>', now('Asia/Jakarta'))
            ->whereBetween('start_at', [$start->toDateTimeString(), $end->toDateTimeString()])
            ->when($user->position_id, function ($q, $posId) {
                $q->where(function ($sub) use ($posId) {
                    $sub->where('position_id', $posId)
                        ->orWhereNull('position_id');
                });
            })
            // Tidak sedang diperebutkan permohonan aktif yang pending
            ->whereDoesntHave('exchanges', function ($q) {
                $q->whereIn('status', ['pending_peer', 'pending_manager']);
            })
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('shift_exchanges')
                    ->whereColumn('shift_exchanges.target_shift_id', 'shifts.id')
                    ->whereIn('shift_exchanges.status', ['pending_peer', 'pending_manager']);
            })
            ->orderBy('start_at')
            ->get();
    }

    /**
     * Mengajukan Ganti Shift atau Ganti Libur ke Jadwal Kosong (Open Slot) untuk level setingkat.
     * Langsung berstatus 'pending_manager' (menunggu persetujuan Super Admin atau HRD).
     */
    public function requestOpenSlotExchange(
        User $actor,
        string $type,
        Shift $targetShift,
        ?Shift $requesterShift = null,
        string $reason = '',
        ?string $offDate = null,
        ?int $userId = null
    ): ShiftExchange {
        abort_unless(in_array($type, ['change_shift', 'change_off_day'], true), 422, 'Tipe permohonan harus ganti shift atau ganti libur.');

        if (! Access::admin() && ! Access::manager()) {
            abort_unless($actor->id === auth()->id(), 403, 'Akses tidak sah untuk mengajukan pergantian atas nama karyawan lain.');
        }

        // Tentukan pemohon aktual (requester)
        // Jika aktor adalah manajer/admin dan terdapat shift asal, pemohon adalah pemilik shift asal
        // Atau jika user_id diberikan secara eksplisit oleh manajer/admin
        $requester = $actor;
        if (Access::admin() || Access::manager() || in_array($actor->role, ['admin', 'manager'], true)) {
            if ($requesterShift && $requesterShift->user) {
                $requester = $requesterShift->user;
            } elseif ($userId) {
                $requester = User::find($userId) ?? $actor;
            }
        }

        abort_unless($targetShift->user_id === null, 422, 'Jadwal yang dipilih bukan merupakan jadwal kosong.');

        if (! Access::admin()) {
            abort_unless($targetShift->branch_id === $requester->branch_id, 422, 'Jadwal kosong harus berada di cabang yang sama.');
        }

        // Validasi level setingkat (posisi yang sama jika ditentukan)
        if ($targetShift->position_id && $requester->position_id) {
            abort_unless($targetShift->position_id === $requester->position_id, 422, 'Jadwal kosong ini diperuntukkan untuk level jabatan yang berbeda dengan Anda.');
        }

        Period::writable($targetShift->branch_id, $targetShift->start_at->toDateString());

        // Overlap check: Pastikan jadwal target tidak bentrok dengan jadwal shift lain milik pemohon
        $conflict = Shift::where('user_id', $requester->id)
            ->when($requesterShift, fn ($q, $rs) => $q->where('id', '!=', $rs->id))
            ->where('start_at', '<', $targetShift->end_at)
            ->where('end_at', '>', $targetShift->start_at)
            ->exists();
        abort_if($conflict, 409, 'Jadwal kosong yang dipilih bertabrakan dengan jadwal shift lain milik Anda.');

        // Cek apakah target shift sedang dalam permohonan aktif
        $hasActiveTarget = ShiftExchange::where('target_shift_id', $targetShift->id)
            ->whereIn('status', ['pending_peer', 'pending_manager'])
            ->exists();
        abort_if($hasActiveTarget, 409, 'Jadwal kosong ini sudah memiliki permohonan yang sedang diproses.');

        // Untuk Ganti Shift, requesterShift wajib diisi
        if ($type === 'change_shift') {
            abort_unless($requesterShift !== null, 422, 'Pilih jadwal shift asal yang ingin diganti.');
        }

        if ($requesterShift) {
            if (! Access::admin() && ! Access::manager()) {
                abort_unless($requesterShift->user_id === $requester->id, 403, 'Shift asal harus milik Anda sendiri.');
            }
            Period::writable($requesterShift->branch_id, $requesterShift->start_at->toDateString());
            abort_if($requesterShift->attendance()->exists(), 409, 'Shift asal sudah memiliki rekaman absensi dan tidak dapat dialihkan.');
            abort_if($requesterShift->exceptions()->exists(), 409, 'Shift asal memiliki kendala presensi aktif.');

            $hasActiveRequester = ShiftExchange::where('requester_shift_id', $requesterShift->id)
                ->whereIn('status', ['pending_peer', 'pending_manager'])
                ->exists();
            abort_if($hasActiveRequester, 409, 'Shift asal sudah memiliki permohonan pergantian yang sedang diproses.');
        }

        $exchange = ShiftExchange::create([
            'type'               => $type,
            'branch_id'          => $targetShift->branch_id,
            'requester_id'       => $requester->id,
            'requester_shift_id' => $requesterShift?->id,
            'replacement_id'     => null, // Tanpa rekan pengganti
            'target_shift_id'    => $targetShift->id,
            'target_date'        => $offDate ?: $targetShift->start_at->toDateString(),
            'reason'             => $reason,
            'status'             => 'pending_manager', // Langsung menunggu persetujuan Super Admin / HRD
        ]);

        Audit::record('shift_exchange', $exchange->id, 'request_' . $type, $reason);

        return $exchange;
    }

    /**
     * Konfirmasi persetujuan dari rekan pengganti (Peer Approval).
     */
    public function peerRespond(ShiftExchange $exchange, User $responder, bool $accept, ?string $note = null): ShiftExchange
    {
        abort_unless($responder->id === $exchange->replacement_id, 403, 'Hanya karyawan pengganti yang dituju yang dapat merespon permohonan ini.');
        abort_unless($exchange->status === 'pending_peer', 409, 'Permohonan ini tidak sedang menunggu persetujuan rekan kerja.');

        if (! $accept) {
            $exchange->update([
                'status'           => 'rejected',
                'replacement_note' => $note,
                'peer_responded_at'=> now(),
            ]);

            Audit::record('shift_exchange', $exchange->id, 'peer_reject', $note);

            return $exchange;
        }

        // Verifikasi ulang anti-konflik
        $shift = $exchange->requesterShift;
        Period::writable($shift->branch_id, $shift->start_at->toDateString());
        abort_if($shift->attendance()->exists(), 409, 'Shift sudah memiliki rekaman absensi.');

        $conflict = Shift::where('user_id', $responder->id)
            ->when($exchange->target_shift_id, fn ($q, $tid) => $q->where('id', '!=', $tid))
            ->where('start_at', '<', $shift->end_at)
            ->where('end_at', '>', $shift->start_at)
            ->exists();
        abort_if($conflict, 409, 'Terdapat jadwal shift lain yang tumpang tindih.');

        $exchange->update([
            'status'           => 'pending_manager',
            'replacement_note' => $note,
            'peer_responded_at'=> now(),
        ]);

        Audit::record('shift_exchange', $exchange->id, 'peer_accept', $note);

        return $exchange;
    }

    /**
     * Persetujuan manajerial / Super Admin (Manager/Admin Approval).
     */
    public function managerReview(ShiftExchange $exchange, User $reviewer, bool $approve, ?string $note = null): ShiftExchange
    {
        if (! Access::admin()) {
            abort_unless(Access::manager(), 403, 'Hanya manajer atau admin yang dapat menyetujui permohonan ini.');
            abort_unless($reviewer->branch_id === $exchange->branch_id, 403, 'Akses ditolak untuk cabang ini.');
        }

        abort_if(in_array($exchange->status, ['approved', 'rejected', 'cancelled'], true), 409, 'Permohonan ini telah diselesaikan sebelumnya.');

        if (in_array($exchange->type, ['replacement', 'swap'], true)) {
            abort_unless($exchange->status === 'pending_manager', 422, 'Permohonan harus disetujui oleh rekan pengganti terlebih dahulu sebelum dapat diproses manajemen.');
        }

        if (! $approve) {
            $exchange->update([
                'status'      => 'rejected',
                'review_note' => $note,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
            ]);

            Audit::record('shift_exchange', $exchange->id, 'manager_reject', $note);

            return $exchange;
        }

        DB::transaction(function () use ($exchange, $reviewer, $note) {
            $reqShift = $exchange->requesterShift;
            if ($reqShift) {
                Period::writable($reqShift->branch_id, $reqShift->start_at->toDateString());
                abort_if($reqShift->attendance()->exists() || $reqShift->exceptions()->exists(), 409, 'Shift pemohon sudah berabsensi atau memiliki kendala.');
            }

            if ($exchange->type === 'replacement') {
                abort_unless($reqShift, 422, 'Shift pemohon tidak ditemukan.');
                // Eksekusi pengalihan shift searah
                $conflict = Shift::where('user_id', $exchange->replacement_id)
                    ->where('id', '!=', $reqShift->id)
                    ->where('start_at', '<', $reqShift->end_at)
                    ->where('end_at', '>', $reqShift->start_at)
                    ->exists();
                abort_if($conflict, 409, 'Karyawan pengganti memiliki jadwal yang bertabrakan.');

                $oldUser = $reqShift->user_id;
                $reqShift->update([
                    'user_id' => $exchange->replacement_id,
                    'version' => $reqShift->version + 1,
                ]);

                Audit::record('shift', $reqShift->id, 'reassigned', "Shift dialihkan dari user #{$oldUser} ke user #{$exchange->replacement_id} melalui permohonan #{$exchange->id}");
            } elseif ($exchange->type === 'swap') {
                abort_unless($reqShift, 422, 'Shift pemohon tidak ditemukan.');
                // Eksekusi pertukaran dua arah
                $targetShift = $exchange->targetShift;
                abort_unless($targetShift, 422, 'Shift target tidak ditemukan.');
                Period::writable($targetShift->branch_id, $targetShift->start_at->toDateString());
                abort_if($targetShift->attendance()->exists() || $targetShift->exceptions()->exists(), 409, 'Shift target sudah berabsensi atau memiliki kendala.');

                $userA = $reqShift->user_id;
                $userB = $targetShift->user_id;

                $reqShift->update([
                    'user_id' => $userB,
                    'version' => $reqShift->version + 1,
                ]);

                $targetShift->update([
                    'user_id' => $userA,
                    'version' => $targetShift->version + 1,
                ]);

                Audit::record('shift', $reqShift->id, 'swapped', "Shift #{$reqShift->id} ditukar ke user #{$userB}");
                Audit::record('shift', $targetShift->id, 'swapped', "Shift #{$targetShift->id} ditukar ke user #{$userA}");
            } elseif (in_array($exchange->type, ['change_shift', 'change_off_day'], true)) {
                // Eksekusi pengalihan ke jadwal kosong
                $targetShift = $exchange->targetShift;
                abort_unless($targetShift, 422, 'Jadwal kosong target tidak ditemukan.');
                abort_unless($targetShift->user_id === null, 409, 'Jadwal kosong target telah diisi oleh karyawan lain.');

                Period::writable($targetShift->branch_id, $targetShift->start_at->toDateString());

                $conflict = Shift::where('user_id', $exchange->requester_id)
                    ->when($exchange->requester_shift_id, fn ($q, $sid) => $q->where('id', '!=', $sid))
                    ->where('start_at', '<', $targetShift->end_at)
                    ->where('end_at', '>', $targetShift->start_at)
                    ->exists();
                abort_if($conflict, 409, 'Karyawan pemohon memiliki jadwal yang bertabrakan dengan jadwal ini.');

                $targetShift->update([
                    'user_id' => $exchange->requester_id,
                    'status'  => 'approved',
                    'version' => $targetShift->version + 1,
                ]);

                if ($reqShift) {
                    $oldId = $reqShift->id;
                    $reqShift->update([
                        'user_id' => null,
                        'status'  => 'approved',
                        'version' => $reqShift->version + 1,
                    ]);
                    Audit::record('shift', $oldId, 'vacated_for_exchange', "Shift #{$oldId} dikosongkan menjadi slot terbuka karena pemohon dialihkan ke jadwal kosong #{$targetShift->id}");
                }

                Audit::record('shift', $targetShift->id, 'claimed_open_slot', "Jadwal kosong #{$targetShift->id} disetujui untuk pemohon #{$exchange->requester_id}");
            }

            $exchange->update([
                'status'      => 'approved',
                'review_note' => $note,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
            ]);

            Audit::record('shift_exchange', $exchange->id, 'manager_approve', $note);
        });

        return $exchange->fresh();
    }

    /**
     * Penugasan pengganti langsung oleh Manajer / Admin (Direct Reassignment).
     */
    public function directReassign(Shift $shift, User $replacement, User $manager, string $reason): ShiftExchange
    {
        if (! Access::admin()) {
            abort_unless(Access::manager(), 403, 'Hanya manajer atau admin yang dapat menugaskan pengganti langsung.');
            abort_unless($manager->branch_id === $shift->branch_id, 403, 'Akses ditolak untuk cabang ini.');
        }

        abort_if($shift->user_id === $replacement->id, 422, 'Karyawan pengganti sama dengan karyawan saat ini.');
        abort_unless($replacement->active, 422, 'Karyawan pengganti tidak aktif.');

        if (! Access::admin()) {
            abort_unless($replacement->branch_id === $shift->branch_id, 422, 'Karyawan pengganti harus di cabang yang sama.');
        }

        Period::writable($shift->branch_id, $shift->start_at->toDateString());
        abort_if($shift->attendance()->exists(), 409, 'Shift sudah memiliki rekaman absensi dan tidak dapat dialihkan.');
        abort_if($shift->exceptions()->exists(), 409, 'Shift memiliki kendala presensi aktif dan tidak dapat dialihkan.');

        $conflict = Shift::where('user_id', $replacement->id)
            ->where('id', '!=', $shift->id)
            ->where('start_at', '<', $shift->end_at)
            ->where('end_at', '>', $shift->start_at)
            ->exists();
        abort_if($conflict, 409, 'Karyawan pengganti memiliki jadwal yang bertabrakan pada jam tersebut.');

        $exchange = DB::transaction(function () use ($shift, $replacement, $manager, $reason) {
            $oldUser = $shift->user_id;

            $record = ShiftExchange::create([
                'type'               => 'replacement',
                'branch_id'          => $shift->branch_id,
                'requester_id'       => $oldUser,
                'requester_shift_id' => $shift->id,
                'replacement_id'     => $replacement->id,
                'target_shift_id'    => null,
                'reason'             => $reason,
                'status'             => 'approved',
                'peer_responded_at'  => now(),
                'reviewed_by'        => $manager->id,
                'reviewed_at'        => now(),
                'review_note'        => 'Penugasan langsung oleh manajemen.',
            ]);

            $shift->update([
                'user_id' => $replacement->id,
                'version' => $shift->version + 1,
            ]);

            Audit::record('shift', $shift->id, 'direct_reassigned', "Shift dialihkan langsung dari user #{$oldUser} ke user #{$replacement->id}: {$reason}");
            Audit::record('shift_exchange', $record->id, 'direct_reassign', $reason);

            return $record;
        });

        return $exchange;
    }
}
