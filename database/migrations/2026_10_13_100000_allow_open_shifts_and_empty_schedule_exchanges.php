<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
            $table->foreignId('position_id')->nullable()->after('branch_id')->constrained('positions')->nullOnDelete();
        });

        Schema::table('shift_exchanges', function (Blueprint $table) {
            $table->foreignId('replacement_id')->nullable()->change();
            $table->foreignId('requester_shift_id')->nullable()->change();
            $table->date('target_date')->nullable()->after('target_shift_id');
        });
    }

    public function down(): void
    {
        Schema::table('shift_exchanges', function (Blueprint $table) {
            $table->dropColumn('target_date');
            $table->foreignId('requester_shift_id')->nullable(false)->change();
            $table->foreignId('replacement_id')->nullable(false)->change();
        });

        Schema::table('shifts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('position_id');
            $table->foreignId('user_id')->nullable(false)->change();
        });
    }
};
