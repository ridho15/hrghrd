<?php

namespace App\Services;

use App\Models\LeaveDay;
use App\Models\LeaveRequest;
use App\Models\Shift;
use App\Models\User;
use App\Services\ShiftExchangeService;
use App\Support\Access;
use App\Support\Audit;
use App\Support\Period;
use App\Support\Rules;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class LeaveService
{
    public function submit(User $actor, array $data, ?UploadedFile $certificate = null): LeaveRequest
    {
        $userId = (int) ($data['user_id'] ?? $actor->id);
        $type = $data['type'] ?? 'leave';
        $start = Carbon::parse($data['start_date'], 'Asia/Jakarta');
        $end = Carbon::parse($data['end_date'], 'Asia/Jakarta');

        abort_if(strlen(trim((string) ($data['reason'] ?? ''))) < 10, 422, 'Alasan pengajuan izin/cuti minimal 10 karakter.');

        $employee = User::findOrFail($userId);
        foreach ($start->copy()->daysUntil($end) as $date) {
            Period::writable($employee->branch_id, $date->toDateString());
        }

        // Toleransi izin sakit darurat mundur maksimal 3 hari ke belakang
        if ($type === 'sick') {
            $minSickDate = now('Asia/Jakarta')->subDays(3)->startOfDay();
            abort_if($start->lt($minSickDate), 422, 'Pengajuan izin sakit maksimal diajukan untuk 3 hari ke belakang.');
        }

        $overlap = LeaveDay::whereHas('leaveRequest', fn ($q) => $q->where('user_id', $userId))
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->whereIn('status', ['pending', 'approved'])
            ->exists();

        abort_if($overlap, 409, 'Tanggal pengajuan bertumpang tindih dengan permohonan lain.');

        $originalLeaveId = null;
        if ($type === 'leave') {
            $requestedDays = (int) $start->copy()->daysUntil($end)->count();
            $remaining = $employee->remainingLeaveDays($start->year);
            $msg = $employee->role === 'employee'
                ? 'Pengajuan cuti tahunan tidak dapat diproses karena melebihi batas yang diizinkan perusahaan. Silakan koordinasikan dengan atasan atau HRD.'
                : "Sisa kuota cuti tahunan tidak mencukupi (tersisa {$remaining} hari, diajukan {$requestedDays} hari).";
            abort_if($requestedDays > $remaining, 422, $msg);
        } elseif ($type === 'leave_exchange') {
            abort_unless(! empty($data['original_leave_id']), 422, 'Permohonan pergantian cuti wajib memilih pengajuan cuti awal.');
            $origLeave = LeaveRequest::findOrFail($data['original_leave_id']);
            abort_unless((int) $origLeave->user_id === $userId, 403, 'Tidak dapat mengganti cuti milik karyawan lain.');
            abort_unless(in_array($origLeave->status, ['approved', 'pending'], true), 422, 'Hanya permohonan cuti aktif yang dapat diganti.');

            $originalLeaveId = $origLeave->id;
            $requestedDays = (int) $start->copy()->daysUntil($end)->count();
            $oldApprovedDays = (int) $origLeave->days()->where('status', 'approved')->count();
            if ($oldApprovedDays === 0) {
                $oldApprovedDays = (int) $origLeave->days()->count();
            }
            $available = $employee->remainingLeaveDays($start->year) + $oldApprovedDays;
            abort_if($requestedDays > $available, 422, "Sisa kuota cuti tahunan tidak mencukupi untuk pergantian cuti ini (tersedia {$available} hari, diajukan {$requestedDays} hari).");
        }
        // Catatan: type === 'day_off' tidak memotong annual_leave_quota

        $path = null;
        if ($certificate) {
            $path = $certificate->store('certificates', 'local');
        }

        return DB::transaction(function () use ($userId, $actor, $data, $type, $start, $end, $path, $certificate, $originalLeaveId) {
            $leave = LeaveRequest::create([
                'user_id'           => $userId,
                'original_leave_id' => $originalLeaveId,
                'created_by'        => $actor->id,
                'type'              => $type,
                'start_date'        => $start->toDateString(),
                'end_date'          => $end->toDateString(),
                'reason'            => $data['reason'],
                'certificate_path'  => $path,
                'certificate_name'  => $path ? basename($certificate->getClientOriginalName()) : null,
                'status'            => 'pending',
                'vacate_shifts'     => false,
            ]);

            $days = [];
            foreach ($start->copy()->daysUntil($end) as $date) {
                $days[] = [
                    'leave_request_id' => $leave->id,
                    'date'             => $date->toDateString(),
                    'status'           => 'pending',
                    'paid'             => $type === 'day_off',
                ];
            }
            LeaveDay::insert($days);

            Audit::record('leave_request', $leave->id, 'create', $data['reason']);

            return $leave;
        });
    }

    public function review(LeaveRequest $leave, User $reviewer, array $approvedDates, array $paidDates, string $note, array $replacements = [], bool $vacateShifts = false): void
    {
        abort_unless($leave->status === 'pending', 409);
        $employee = $leave->user;
        abort_unless(Access::branch($employee->branch_id), 403);
        abort_if(((int) $leave->created_by === $reviewer->id || (int) $leave->user_id === $reviewer->id) && ! Access::admin(), 403, 'Tidak boleh menyetujui pengajuan cuti/izin sendiri.');

        $all = $leave->days()->orderBy('date')->get();
        foreach ($all as $day) {
            Period::writable($employee->branch_id, (string) $day->date);
        }

        $allDates = $all->map(fn ($d) => (string) $d->date)->all();
        abort_if(array_diff($approvedDates, $allDates), 422, 'Tanggal persetujuan tidak valid.');

        if (in_array($leave->type, ['leave', 'leave_exchange'], true) && count($approvedDates) > 0) {
            $year = Carbon::parse($approvedDates[0], 'Asia/Jakarta')->year;
            $alreadyUsed = (int) LeaveDay::whereHas('leaveRequest', function ($q) use ($employee, $leave) {
                $q->where('user_id', $employee->id)
                    ->whereIn('type', ['leave', 'leave_exchange'])
                    ->where('id', '!=', $leave->id)
                    ->when($leave->original_leave_id, fn ($sub, $origId) => $sub->where('id', '!=', $origId));
            })->whereYear('date', $year)->where('status', 'approved')->count();

            $quota = $employee->annual_leave_quota ?? 12;
            $remaining = max(0, $quota - $alreadyUsed);
            abort_if(count($approvedDates) > $remaining, 422, "Persetujuan melebihi sisa kuota cuti tahunan karyawan (tersisa {$remaining} hari, disetujui " . count($approvedDates) . " hari).");
        }

        DB::transaction(function () use ($leave, $reviewer, $all, $approvedDates, $paidDates, $note, $replacements, $vacateShifts, $employee) {
            $paidSick = 0;
            foreach ($all as $day) {
                $dayDate = (string) $day->date;
                $isApproved = in_array($dayDate, $approvedDates, true);
                $isPaid = false;
                if ($isApproved) {
                    if ($leave->type === 'sick') {
                        if (! empty($paidDates)) {
                            $isPaid = in_array($dayDate, $paidDates, true);
                        } elseif ($leave->certificate_path) {
                            $isPaid = true;
                        } else {
                            $paidSickLimit = Rules::int('sick_paid_days_per_case');
                            $isPaid = $paidSick++ < $paidSickLimit;
                        }
                    } elseif ($leave->type === 'day_off') {
                        $isPaid = true;
                    } elseif (in_array($leave->type, ['leave', 'leave_exchange'], true)) {
                        $isPaid = in_array($dayDate, $paidDates, true);
                    } elseif ($leave->type === 'unpaid_leave') {
                        $isPaid = false;
                    }
                }
                $day->update(['status' => $isApproved ? 'approved' : 'rejected', 'paid' => $isPaid]);
            }

            $status = count($approvedDates) === 0 ? 'rejected' : (count($approvedDates) === $all->count() ? 'approved' : 'partial');
            $leave->update([
                'status'        => $status,
                'reviewed_by'   => $reviewer->id,
                'review_note'   => $note,
                'reviewed_at'   => now('Asia/Jakarta'),
                'vacate_shifts' => $vacateShifts,
            ]);

            // Jika permohonan pergantian cuti disetujui, batalkan cuti awal agar tidak bertumpuk
            if ($leave->type === 'leave_exchange' && $leave->original_leave_id && count($approvedDates) > 0) {
                $orig = LeaveRequest::find($leave->original_leave_id);
                if ($orig) {
                    $orig->days()->update(['status' => 'cancelled']);
                    $orig->update([
                        'status'      => 'cancelled',
                        'review_note' => "Digantikan oleh permohonan pergantian cuti #{$leave->id}"
                    ]);
                    Audit::record('leave_request', $orig->id, 'replaced_by_exchange', "Digantikan oleh permohonan #{$leave->id}");
                }
            }

            // Penugasan Staf Pengganti Langsung jika ada
            $reassignedShiftIds = [];
            if (! empty($replacements) && count($approvedDates) > 0) {
                $exchangeService = app(ShiftExchangeService::class);
                foreach ($replacements as $shiftId => $replacementUserId) {
                    if (! empty($replacementUserId)) {
                        $shift = Shift::find($shiftId);
                        $replacementUser = User::find($replacementUserId);
                        if ($shift && $replacementUser && (int) $shift->user_id === (int) $leave->user_id) {
                            $shiftDate = $shift->start_at->toDateString();
                            if (in_array($shiftDate, $approvedDates, true)) {
                                $exchangeService->directReassign(
                                    $shift,
                                    $replacementUser,
                                    $reviewer,
                                    "Penugasan lembur pengganti karena izin karyawan #{$leave->id}"
                                );
                                $reassignedShiftIds[] = [
                                    'shift_id'       => $shift->id,
                                    'replacement_id' => $replacementUser->id,
                                ];
                            }
                        }
                    }
                }
            }

            // Otomatisasi Pelepasan Shift Menjadi Slot Kosong Terbuka Setingkat
            $vacatedShiftIds = [];
            if ($vacateShifts && count($approvedDates) > 0) {
                $shiftsToVacate = Shift::where('user_id', $leave->user_id)
                    ->whereDoesntHave('attendance')
                    ->where('end_at', '>', now('Asia/Jakarta'))
                    ->where(function ($q) use ($approvedDates) {
                        foreach ($approvedDates as $ad) {
                            $q->orWhereDate('start_at', $ad);
                        }
                    })
                    ->get();

                $alreadyReassignedIds = collect($reassignedShiftIds)->pluck('shift_id')->all();
                foreach ($shiftsToVacate as $sh) {
                    if (! in_array($sh->id, $alreadyReassignedIds, true)) {
                        $sh->update([
                            'user_id'     => null,
                            'position_id' => $employee->position_id,
                            'status'      => 'approved',
                            'version'     => $sh->version + 1,
                        ]);
                        $vacatedShiftIds[] = $sh->id;
                        Audit::record('shift', $sh->id, 'vacated_for_leave', "Shift #{$sh->id} dikosongkan menjadi slot kosong terbuka setingkat ({$employee->position_name}) karena permohonan {$leave->type} #{$leave->id} disetujui");
                    }
                }
            }

            $actualPaidDates = $all->where('paid', true)->pluck('date')->map(fn ($d) => (string) $d)->values()->all();

            Audit::record('leave_request', $leave->id, 'review', $note, null, [
                'status'            => $status,
                'approved_dates'    => $approvedDates,
                'paid_dates'        => $actualPaidDates,
                'reassigned_shifts' => $reassignedShiftIds,
                'vacated_shifts'    => $vacatedShiftIds,
            ]);
        });
    }
}
