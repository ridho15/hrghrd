<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->foreignId('original_leave_id')
                ->nullable()
                ->after('user_id')
                ->constrained('leave_requests')
                ->nullOnDelete();

            $table->boolean('vacate_shifts')
                ->default(false)
                ->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->dropForeign(['original_leave_id']);
            $table->dropColumn(['original_leave_id', 'vacate_shifts']);
        });
    }
};
