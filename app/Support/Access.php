<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

final class Access
{
    public static function admin(): bool
    {
        return auth()->user()?->role === 'admin';
    }

    public static function hr(): bool
    {
        return in_array(auth()->user()?->role, ['admin', 'hr'], true);
    }

    public static function approver(): bool
    {
        return in_array(auth()->user()?->role, ['admin', 'hr', 'approver'], true);
    }

    public static function auditor(): bool
    {
        return in_array(auth()->user()?->role, ['admin', 'auditor'], true);
    }

    public static function manager(): bool
    {
        return in_array(auth()->user()?->role, ['admin', 'hr', 'manager'], true);
    }

    public static function branch(int $branchId): bool
    {
        return self::admin() || self::hr() || (self::manager() && (int) auth()->user()?->branch_id === $branchId);
    }

    public static function employee(int $userId): bool
    {
        if (self::admin() || self::hr() || auth()->id() === $userId) {
            return true;
        }

        return self::manager() && (int) DB::table('users')->where('id', $userId)->value('branch_id') === (int) auth()->user()?->branch_id;
    }

    public static function canViewSensitiveDocuments(int $userId): bool
    {
        // KTP, data biometrik/foto wajah, dan data sensitif hanya boleh dibuka oleh Admin, HR, Auditor, atau pemilik akun
        if (self::admin() || self::hr() || self::auditor()) {
            return true;
        }

        return auth()->id() === $userId;
    }

    public static function canManageTemplates(): bool
    {
        return self::admin() || self::hr();
    }

    public static function canApproveTemplates(): bool
    {
        return self::admin() || in_array(auth()->user()?->role, ['approver', 'hr'], true);
    }

    public static function canPublishTemplates(): bool
    {
        return self::admin() || auth()->user()?->role === 'approver';
    }

    public static function canManageOrgChart(): bool
    {
        return self::admin() || self::hr();
    }
}
