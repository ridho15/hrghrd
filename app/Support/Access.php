<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

final class Access
{
    public static function admin(): bool { return auth()->user()?->role === 'admin'; }
    public static function manager(): bool { return in_array(auth()->user()?->role, ['admin','manager'], true); }
    public static function branch(int $branchId): bool
    {
        return self::admin() || (self::manager() && (int) auth()->user()->branch_id === $branchId);
    }
    public static function employee(int $userId): bool
    {
        if (self::admin() || auth()->id() === $userId) return true;
        return self::manager() && (int) DB::table('users')->where('id',$userId)->value('branch_id') === (int) auth()->user()->branch_id;
    }
}
