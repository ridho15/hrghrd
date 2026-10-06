<?php

namespace App\Services;

use App\Models\LeaveDay;
use App\Models\LeaveRequest;
use App\Models\User;
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
        $start = Carbon::parse($data['start_date'], 'Asia/Jakarta');
        $end = Carbon::parse($data['end_date'], 'Asia/Jakarta');

        $employee = User::findOrFail($userId);
        foreach ($start->copy()->daysUntil($end) as $date) {
            Period::writable($employee->branch_id, $date->toDateString());
        }

        $overlap = LeaveDay::whereHas('leaveRequest', fn ($q) => $q->where('user_id', $userId))
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->whereIn('status', ['pending', 'approved'])
            ->exists();

        abort_if($overlap, 409, 'Tanggal pengajuan bertumpang tindih.');

        $path = null;
        if ($certificate) {
            $path = $certificate->store('certificates', 'local');
        }

        return DB::transaction(function () use ($userId, $actor, $data, $start, $end, $path, $certificate) {
            $leave = LeaveRequest::create([
                'user_id' => $userId,
                'created_by' => $actor->id,
                'type' => $data['type'],
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'reason' => $data['reason'],
                'certificate_path' => $path,
                'certificate_name' => $path ? basename($certificate->getClientOriginalName()) : null,
                'status' => 'pending',
            ]);

            $days = [];
            foreach ($start->copy()->daysUntil($end) as $date) {
                $days[] = [
                    'leave_request_id' => $leave->id,
                    'date' => $date->toDateString(),
                    'status' => 'pending',
                    'paid' => false,
                ];
            }
            LeaveDay::insert($days);

            Audit::record('leave_request', $leave->id, 'create', $data['reason']);

            return $leave;
        });
    }

    public function review(LeaveRequest $leave, User $reviewer, array $approvedDates, array $paidDates, string $note): void
    {
        abort_unless($leave->status === 'pending', 409);
        $employee = $leave->user;
        abort_unless(Access::branch($employee->branch_id), 403);
        abort_if((int) $leave->created_by === $reviewer->id, 403, 'Pembuat pengajuan tidak boleh menyetujui sendiri.');

        $all = $leave->days()->orderBy('date')->get();
        foreach ($all as $day) {
            Period::writable($employee->branch_id, (string) $day->date);
        }

        $allDates = $all->map(fn ($d) => (string) $d->date)->all();
        abort_if(array_diff($approvedDates, $allDates), 422, 'Tanggal persetujuan tidak valid.');

        DB::transaction(function () use ($leave, $reviewer, $all, $approvedDates, $paidDates, $note) {
            $paidSick = 0;
            foreach ($all as $day) {
                $dayDate = (string) $day->date;
                $isApproved = in_array($dayDate, $approvedDates, true);
                $isPaid = false;
                if ($isApproved && $leave->type === 'sick') {
                    $paidSickLimit = Rules::int('sick_paid_days_per_case');
                    $isPaid = $paidSick++ < $paidSickLimit;
                }
                if ($isApproved && $leave->type === 'leave') {
                    $isPaid = in_array($dayDate, $paidDates, true);
                }
                $day->update(['status' => $isApproved ? 'approved' : 'rejected', 'paid' => $isPaid]);
            }

            $status = count($approvedDates) === 0 ? 'rejected' : (count($approvedDates) === $all->count() ? 'approved' : 'partial');
            $leave->update([
                'status' => $status,
                'reviewed_by' => $reviewer->id,
                'review_note' => $note,
                'reviewed_at' => now('Asia/Jakarta'),
            ]);

            Audit::record('leave_request', $leave->id, 'review', $note, null, [
                'status' => $status,
                'approved_dates' => $approvedDates,
                'paid_dates' => $paidDates,
            ]);
        });
    }
}
