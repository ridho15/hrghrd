<?php

namespace App\Services\HrGroup;

use App\Models\HrGroupLink;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AccountResolver
{
    /**
     * Resolusi klaim OIDC Keycloak ke user lokal di database HRIS
     *
     * @param array $claims
     * @return User
     * @throws Exception
     */
    public function resolve(array $claims): User
    {
        $sub = (string) ($claims['sub'] ?? '');
        if ($sub === '') {
            throw new Exception('Data identitas Keycloak (sub) tidak valid.');
        }

        $user = null;

        // 1. Tautan langsung di tabel hr_group_links
        $link = HrGroupLink::where('hrg_sub', $sub)->first();
        if ($link) {
            $user = User::find($link->user_id);
        }

        // 2. Pencocokan berdasarkan Email jika belum ketemu
        if (!$user) {
            $email = trim((string) ($claims['email'] ?? ''));
            if ($email !== '') {
                $user = User::where('email', $email)->first();
                if ($user) {
                    $this->linkSub($user, $sub);
                }
            }
        }

        // 3. Pencocokan berdasarkan Username jika belum ketemu
        if (!$user) {
            $username = trim((string) ($claims['preferred_username'] ?? ''));
            if ($username !== '') {
                $user = User::where('username', $username)->first();
                if ($user) {
                    $this->linkSub($user, $sub);
                }
            }
        }

        // 4. JIT Auto-Provisioning jika Karyawan Baru
        if (!$user) {
            $user = $this->createFromClaims($claims, $sub);
            $this->linkSub($user, $sub);
        }

        if (!$user->active) {
            throw new Exception('Akun karyawan Anda telah dinonaktifkan oleh administrator.');
        }

        // 5. Sinkronisasi role dan capabilities dari Keycloak token claims ke model User lokal
        $this->syncRolesAndCapabilities($user, $claims);

        return $user;
    }

    private function linkSub(User $user, string $sub): void
    {
        HrGroupLink::updateOrCreate(
            ['user_id' => $user->id],
            ['hrg_sub' => $sub]
        );
    }

    /**
     * Sinkronisasi role dan capabilities dari klaim Keycloak ke model User lokal
     */
    private function syncRolesAndCapabilities(User $user, array $claims): void
    {
        $roles = $claims['realm_access']['roles'] ?? [];
        $clientRoles = $claims['resource_access']['hrg-hrd']['roles'] ?? [];

        // 1. Tentukan base app role (admin, manager, employee)
        $appRole = 'employee';
        if (in_array('hrd-admin', $clientRoles, true) || in_array('hrgroup-admin', $roles, true)) {
            $appRole = 'admin';
        } elseif (in_array('hrd-manager', $clientRoles, true)) {
            $appRole = 'manager';
        }

        // 2. Ekstrak granular capabilities (misal: 'payroll:manage', 'employee:manage', 'attendance:review')
        $capabilities = array_values(array_filter($clientRoles, function ($r) {
            return is_string($r) && str_contains($r, ':');
        }));

        $updated = false;
        if ($user->role !== $appRole) {
            $user->role = $appRole;
            $updated = true;
        }

        sort($capabilities);
        $currentCaps = is_array($user->capabilities) ? $user->capabilities : [];
        sort($currentCaps);

        if ($currentCaps !== $capabilities) {
            $user->capabilities = $capabilities;
            $updated = true;
        }

        if ($updated) {
            $user->save();
        }
    }

    private function createFromClaims(array $claims, string $sub): User
    {
        $roles = $claims['realm_access']['roles'] ?? [];
        $clientRoles = $claims['resource_access']['hrg-hrd']['roles'] ?? [];

        $appRole = 'employee';
        if (in_array('hrd-admin', $clientRoles, true) || in_array('hrgroup-admin', $roles, true)) {
            $appRole = 'admin';
        } elseif (in_array('hrd-manager', $clientRoles, true)) {
            $appRole = 'manager';
        }

        $email = trim((string) ($claims['email'] ?? ''));
        $username = trim((string) ($claims['preferred_username'] ?? ''));
        $name = trim((string) ($claims['name'] ?? $username ?: 'Karyawan HR Group'));

        if ($email === '') {
            $email = ($username ?: Str::random(8)) . '@hrgroup.local';
        }

        $capabilities = array_values(array_filter($clientRoles, function ($r) {
            return is_string($r) && str_contains($r, ':');
        }));

        return User::create([
            'name' => $name,
            'username' => $username ?: null,
            'email' => $email,
            'password' => bcrypt(Str::random(32)),
            'role' => $appRole,
            'capabilities' => $capabilities,
            'active' => true,
            'must_change_password' => false,
            'employment_status' => 'permanent',
            'entity_name' => 'LRM Corporation',
            'base_salary' => 3500000,
            'allow_all_branches' => $appRole === 'admin',
        ]);
    }
}
