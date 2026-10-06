<?php

namespace App\Services;

use App\Models\Shift;
use App\Models\User;
use App\Support\Audit;
use App\Support\Period;
use Carbon\Carbon;

class ShiftService
{
    public function create(array $data): Shift
    {
        Period::writable($data['branch_id'], $data['date']);

        $start = Carbon::parse($data['date'] . ' ' . $data['start_time'], 'Asia/Jakarta');
        $end = Carbon::parse($data['date'] . ' ' . $data['end_time'], 'Asia/Jakarta');
        if ($end->lte($start)) {
            $end->addDay();
        }

        $shift = Shift::create([
            'user_id' => $data['user_id'],
            'branch_id' => $data['branch_id'],
            'start_at' => $start,
            'end_at' => $end,
            'status' => 'draft',
            'version' => 1,
        ]);

        Audit::record('shift', $shift->id, 'create');

        return $shift;
    }

    public function update(Shift $shift, array $data): Shift
    {
        Period::writable($shift->branch_id, $shift->start_at->toDateString());
        abort_if($shift->status === 'approved', 409, 'Shift sudah disetujui, tidak bisa diedit langsung.');
        if (isset($data['expected_version'])) {
            abort_if((int) $data['expected_version'] !== (int) $shift->version, 409, 'Jadwal telah diubah oleh pengguna lain.');
        }

        $date = $data['date'] ?? $shift->start_at->toDateString();
        $start = Carbon::parse($date . ' ' . $data['start_time'], 'Asia/Jakarta');
        $end = Carbon::parse($date . ' ' . $data['end_time'], 'Asia/Jakarta');
        if ($end->lte($start)) {
            $end->addDay();
        }

        $shift->update([
            'start_at' => $start,
            'end_at' => $end,
            'version' => $shift->version + 1,
        ]);

        Audit::record('shift', $shift->id, 'update');

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
}
