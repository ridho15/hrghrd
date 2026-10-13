<?php

namespace App\Services;

use App\Models\Shift;
use App\Models\User;
use App\Support\Access;
use App\Support\Audit;
use App\Support\Period;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ShiftService
{
    public function create(array $data, ?User $actor = null): Shift
    {
        Period::writable($data['branch_id'], $data['date']);

        $start = Carbon::parse($data['date'] . ' ' . $data['start_time'], 'Asia/Jakarta');
        $end = Carbon::parse($data['date'] . ' ' . $data['end_time'], 'Asia/Jakarta');
        if ($end->lte($start)) {
            $end->addDay();
        }

        $isAutoApprove = ! empty($data['auto_approve']) && (($actor?->role === 'admin') || Access::admin());

        $userId = ! empty($data['user_id']) ? (int) $data['user_id'] : null;
        $user = $userId ? User::find($userId) : null;
        $positionId = ! empty($data['position_id']) ? (int) $data['position_id'] : ($user?->position_id);

        $shift = Shift::create([
            'user_id' => $userId,
            'branch_id' => $data['branch_id'],
            'position_id' => $positionId,
            'start_at' => $start,
            'end_at' => $end,
            'status' => $isAutoApprove ? 'approved' : 'draft',
            'version' => 1,
            'approved_by' => $isAutoApprove ? ($actor?->id ?: auth()->id()) : null,
            'approved_at' => $isAutoApprove ? now('Asia/Jakarta') : null,
        ]);

        Audit::record('shift', $shift->id, $isAutoApprove ? 'create_approved' : 'create');

        return $shift;
    }

    public function update(Shift $shift, array $data): Shift
    {
        Period::writable($shift->branch_id, $shift->start_at->toDateString());

        if ($shift->status === 'approved') {
            abort_unless(Access::admin(), 409, 'Shift sudah disetujui, hanya Super Admin yang dapat merevisi jadwal ini.');
        }

        abort_if($shift->attendance()->exists(), 409, 'Shift yang sudah memiliki rekaman absensi tidak dapat diubah langsung.');

        if (isset($data['expected_version'])) {
            abort_if((int) $data['expected_version'] !== (int) $shift->version, 409, 'Jadwal telah diubah oleh pengguna lain.');
        }

        $date = $data['date'] ?? $shift->start_at->toDateString();
        $start = Carbon::parse($date . ' ' . $data['start_time'], 'Asia/Jakarta');
        $end = Carbon::parse($date . ' ' . $data['end_time'], 'Asia/Jakarta');
        if ($end->lte($start)) {
            $end->addDay();
        }

        $updateData = [
            'start_at' => $start,
            'end_at' => $end,
            'version' => $shift->version + 1,
        ];

        if ($shift->status === 'approved' && Access::admin()) {
            $updateData['approved_by'] = auth()->id() ?: $shift->approved_by;
            $updateData['approved_at'] = now('Asia/Jakarta');
        }

        $shift->update($updateData);

        Audit::record('shift', $shift->id, $shift->status === 'approved' ? 'admin_override_update' : 'update');

        return $shift;
    }

    public function approve(Shift $shift, User $approver): Shift
    {
        Period::writable($shift->branch_id, $shift->start_at->toDateString());

        $shift->update([
            'status' => 'approved',
            'approved_by' => $approver->id,
            'approved_at' => now(),
        ]);

        Audit::record('shift', $shift->id, 'approve');

        return $shift;
    }

    /**
     * Membuat jadwal shift secara massal (berulang / rentang tanggal)
     * dengan proteksi periode dan pencegahan tumpang tindih cerdas.
     *
     * @return array{created_count: int, skipped_count: int, shifts: \Illuminate\Support\Collection}
     */
    public function createBatch(array $data, ?User $actor = null): array
    {
        $branchId = (int) $data['branch_id'];

        if (! empty($data['select_all_branch'])) {
            $userIds = User::where('branch_id', $branchId)
                ->where('active', true)
                ->whereIn('role', ['employee', 'manager'])
                ->pluck('id')
                ->all();
        } else {
            $userIds = array_map('intval', (array) ($data['user_ids'] ?? [$data['user_id'] ?? null]));
            $userIds = array_values(array_filter($userIds));
        }

        abort_if(empty($userIds), 422, 'Pilih setidaknya satu karyawan aktif.');

        $startDate = Carbon::parse($data['start_date'], 'Asia/Jakarta');
        $endDate = Carbon::parse($data['end_date'], 'Asia/Jakarta');
        $daysOfWeek = array_map('intval', (array) ($data['days_of_week'] ?? []));

        $isAutoApprove = ! empty($data['auto_approve']) && ($actor?->role === 'admin' || Access::admin());
        $status = $isAutoApprove ? 'approved' : 'draft';
        $approvedBy = $isAutoApprove ? ($actor?->id ?? auth()->id()) : null;
        $approvedAt = $isAutoApprove ? now('Asia/Jakarta') : null;

        $createdShifts = collect();
        $skippedCount = 0;

        $userPositionMap = User::whereIn('id', $userIds)->pluck('position_id', 'id')->all();

        DB::transaction(function () use (
            $startDate, $endDate, $daysOfWeek, $userIds, $data, $branchId,
            $status, $approvedBy, $approvedAt, $userPositionMap, &$createdShifts, &$skippedCount
        ) {
            $currentDate = $startDate->copy();
            while ($currentDate->lte($endDate)) {
                $dayNum = $currentDate->dayOfWeekIso; // 1 (Senin) .. 7 (Minggu)
                $dateStr = $currentDate->toDateString();

                if (in_array($dayNum, $daysOfWeek, true)) {
                    Period::writable($branchId, $dateStr);

                    $start = Carbon::parse($dateStr . ' ' . $data['start_time'], 'Asia/Jakarta');
                    $end = Carbon::parse($dateStr . ' ' . $data['end_time'], 'Asia/Jakarta');
                    if ($end->lte($start)) {
                        $end->addDay();
                    }
                    abort_if($end->diffInHours($start) > 24, 422, 'Durasi shift tidak valid.');

                    foreach ($userIds as $userId) {
                        $conflict = Shift::where('user_id', $userId)
                            ->where('start_at', '<', $end)
                            ->where('end_at', '>', $start)
                            ->exists();

                        if ($conflict) {
                            $skippedCount++;
                            continue;
                        }

                        $shift = Shift::create([
                            'user_id'     => $userId,
                            'branch_id'   => $branchId,
                            'position_id' => $userPositionMap[$userId] ?? null,
                            'start_at'    => $start,
                            'end_at'      => $end,
                            'status'      => $status,
                            'version'     => 1,
                            'approved_by' => $approvedBy,
                            'approved_at' => $approvedAt,
                        ]);

                        Audit::record('shift', $shift->id, 'create_batch', 'Shift dibuat melalui generator massal/rutin.');
                        $createdShifts->push($shift);
                    }
                }
                $currentDate->addDay();
            }
        });

        return [
            'created_count' => $createdShifts->count(),
            'skipped_count' => $skippedCount,
            'shifts'        => $createdShifts,
        ];
    }
}
