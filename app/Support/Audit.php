<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

final class Audit
{
    public static function record(string $type, int $id, string $action, ?string $reason = null, mixed $before = null, mixed $after = null): void
    {
        DB::table('audit_logs')->insert([
            'actor_id' => auth()->id(), 'subject_type' => $type, 'subject_id' => $id,
            'action' => $action, 'before' => $before === null ? null : json_encode($before),
            'after' => $after === null ? null : json_encode($after), 'reason' => $reason,
            'ip' => request()->ip(), 'created_at' => now(),
        ]);
    }
}
