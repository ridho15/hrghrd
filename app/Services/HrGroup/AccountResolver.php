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

        // 1. Tautan langsung di tabel hr_group_links
        $link = HrGroupLink::where('hrg_sub', $sub)->first();
        if ($link) {
            $user = User::find($link->user_id);
            if ($user && $user->active) {
                return $user;
            }
            if ($user && !$user->active) {
                throw new Exception('Akun karyawan Anda telah dinonaktifkan oleh administrator.');
            }
        }

        // 2. Pencocokan berdasarkan Email
        $email = trim((string) ($claims['email'] ?? ''));
        if ($email !== '') {
            $user = User::where('email', $email)->first();
            if ($user) {
                $this->linkSub($user, $sub);
                return $user;
            }
        }

        // 3. Pencocokan berdasarkan Username
        $username = trim((string) ($claims['preferred_username'] ?? ''));
        if ($username !== '') {
            $user = User::where('username', $username)->first();
            if ($user) {
                $this->linkSub($user, $sub);
                return $user;
            }
        }

        // 4. JIT Auto-Provisioning jika Karyawan Baru
        $user = $this->createFromClaims($claims, $sub);
        $this->linkSub($user, $sub);

        return $user;
    }

    private function linkSub(User $user, string $sub): void
    {
        HrGroupLink::updateOrCreate(
            ['user_id' => $user->id],
            ['hrg_sub' => $sub]
        );
    }

    private function createFromClaims(array $claims, string $sub): User
    {
        $roles = $claims['realm_access']['roles'] ?? [];
        $clientRoles = $claims['resource_access']['hrg-hrd']['roles'] ?? [];

        $appRole = 'employee';
        if (in_array('hrd-admin', $clientRoles) || in_array('hrgroup-admin', $roles)) {
            $appRole = 'admin';
        } elseif (in_array('hrd-manager', $clientRoles)) {
            $appRole = 'manager';
        }

        $email = trim((string) ($claims['email'] ?? ''));
        $username = trim((string) ($claims['preferred_username'] ?? ''));
        $name = trim((string) ($claims['name'] ?? $username ?: 'Karyawan HR Group'));

        if ($email === '') {
            $email = ($username ?: Str::random(8)) . '@hrgroup.local';
        }

        return User::create([
            'name' => $name,
            'username' => $username ?: null,
            'email' => $email,
            'password' => bcrypt(Str::random(32)),
            'role' => $appRole,
            'active' => true,
            'must_change_password' => false,
            'employment_status' => 'permanent',
            'entity_name' => 'LRM Corporation',
            'base_salary' => 3500000,
            'allow_all_branches' => $appRole === 'admin',
        ]);
    }
}
