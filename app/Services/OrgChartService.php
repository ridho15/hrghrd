<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\OrgAssignment;
use App\Models\Position;
use App\Models\User;
use App\Support\Audit;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class OrgChartService
{
    /**
     * Build hierarchical tree data structure for organization chart.
     */
    public function buildTree(?int $branchId = null, ?string $search = null, ?string $asOfDate = null): array
    {
        $query = User::with(['branch', 'position', 'manager', 'subordinates'])
            ->where('active', true)
            ->where('employment_status', '!=', 'candidate');

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhereHas('position', fn ($p) => $p->where('name', 'like', "%{$search}%"));
            });
        }

        $allUsers = $query->orderBy('name')->get();

        if ($allUsers->isEmpty()) {
            return [];
        }

        // Group by manager_id
        $usersById = $allUsers->keyBy('id');
        $groupedByManager = [];

        foreach ($allUsers as $user) {
            $mgrId = $user->manager_id;
            // If manager_id is not in our filtered set, treat as root node
            if (!$mgrId || !$usersById->has($mgrId)) {
                $mgrId = 0;
            }
            $groupedByManager[$mgrId][] = $user;
        }

        // Build tree recursively starting from roots (mgrId = 0)
        $tree = [];
        $roots = $groupedByManager[0] ?? [];

        foreach ($roots as $rootUser) {
            $tree[] = $this->buildNode($rootUser, $groupedByManager);
        }

        return $tree;
    }

    protected function buildNode(User $user, array &$groupedByManager): array
    {
        $children = [];
        if (isset($groupedByManager[$user->id])) {
            foreach ($groupedByManager[$user->id] as $childUser) {
                $children[] = $this->buildNode($childUser, $groupedByManager);
            }
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'position' => $user->position?->name ?: 'Belum Ada Jabatan',
            'branch' => $user->branch?->name ?: 'Pusat',
            'unit' => $user->unit ?: 'Operasional',
            'manager_id' => $user->manager_id,
            'manager_name' => $user->manager?->name,
            'avatar_initials' => strtoupper(substr($user->name, 0, 2)),
            'subordinates_count' => count($children),
            'children' => $children,
        ];
    }

    /**
     * Validate prospective reassignment to prevent self-parenting and circular references.
     */
    public function validateReassignment(int $userId, ?int $newSupervisorId): array
    {
        if ($newSupervisorId === null) {
            return ['valid' => true];
        }

        if ($userId === $newSupervisorId) {
            return [
                'valid' => false,
                'error' => 'Karyawan tidak dapat menjadi atasan langsung untuk dirinya sendiri (self-parenting ditolak).',
            ];
        }

        $supervisor = User::find($newSupervisorId);
        if (!$supervisor) {
            return [
                'valid' => false,
                'error' => 'Atasan yang dipilih tidak ditemukan dalam sistem.',
            ];
        }

        if (!$supervisor->active || $supervisor->employment_status === 'candidate') {
            return [
                'valid' => false,
                'error' => 'Tidak dapat memindahkan bawahan ke karyawan yang berstatus nonaktif atau calon karyawan.',
            ];
        }

        // Cycle detection: ensure $newSupervisorId is not a descendant of $userId
        if ($this->isDescendant($newSupervisorId, $userId)) {
            return [
                'valid' => false,
                'error' => 'Terjadi siklus organisasi (circular reference): Anda tidak dapat memindahkan atasan menjadi bawahan dari bawahannya sendiri.',
            ];
        }

        return ['valid' => true];
    }

    /**
     * Check if potentialChild is a descendant of potentialAncestor.
     */
    public function isDescendant(int $potentialChildId, int $potentialAncestorId): bool
    {
        $currentId = $potentialChildId;
        $visited = [];

        while ($currentId) {
            if ($currentId === $potentialAncestorId) {
                return true;
            }

            if (isset($visited[$currentId])) {
                break; // Break on existing cycle in db
            }
            $visited[$currentId] = true;

            $current = User::find($currentId);
            $currentId = $current?->manager_id;
        }

        return false;
    }

    /**
     * Execute reassignment with audit trail and historical record.
     */
    public function reassign(int $userId, ?int $newSupervisorId, ?string $newUnit, string $effectiveFrom, string $reason, int $actorId): OrgAssignment
    {
        $validation = $this->validateReassignment($userId, $newSupervisorId);
        abort_unless($validation['valid'], 422, $validation['error'] ?? 'Validasi perpindahan struktur organisasi gagal.');

        $user = User::with(['branch', 'position', 'manager'])->findOrFail($userId);
        $effectiveDate = Carbon::parse($effectiveFrom);

        $beforeState = [
            'supervisor_id' => $user->manager_id,
            'supervisor_name' => $user->manager?->name,
            'unit' => $user->unit,
            'branch_id' => $user->branch_id,
        ];

        // Close current active org assignment if exists
        OrgAssignment::where('user_id', $userId)
            ->whereNull('effective_to')
            ->update(['effective_to' => $effectiveDate->copy()->subDay()]);

        // Create new org assignment record
        $assignment = OrgAssignment::create([
            'user_id' => $userId,
            'supervisor_id' => $newSupervisorId,
            'branch_id' => $user->branch_id,
            'position_id' => $user->position_id,
            'unit' => $newUnit ?: $user->unit,
            'effective_from' => $effectiveDate,
            'reason' => $reason,
            'changed_by_id' => $actorId,
        ]);

        // Update User active reference
        $user->update([
            'manager_id' => $newSupervisorId,
            'unit' => $newUnit ?: $user->unit,
        ]);

        $afterState = [
            'supervisor_id' => $newSupervisorId,
            'supervisor_name' => $newSupervisorId ? User::find($newSupervisorId)?->name : 'Pimpinan Puncak / Akar',
            'unit' => $user->unit,
            'effective_from' => $effectiveDate->format('Y-m-d'),
            'reason' => $reason,
        ];

        Audit::record('user', $userId, 'org_reassign', "Perubahan atasan organisasi untuk {$user->name}. Alasan: {$reason}", $beforeState, $afterState);

        return $assignment;
    }
}
