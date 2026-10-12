<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Hanya jalankan modifikasi constraint foreign key jika bukan driver sqlite
        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('attendances', function (Blueprint $table) {
                $table->dropForeign(['shift_id']);
                $table->foreign('shift_id')->references('id')->on('shifts')->restrictOnDelete();
            });

            Schema::table('attendance_exceptions', function (Blueprint $table) {
                $table->dropForeign(['shift_id']);
                $table->foreign('shift_id')->references('id')->on('shifts')->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('attendances', function (Blueprint $table) {
                $table->dropForeign(['shift_id']);
                $table->foreign('shift_id')->references('id')->on('shifts')->cascadeOnDelete();
            });

            Schema::table('attendance_exceptions', function (Blueprint $table) {
                $table->dropForeign(['shift_id']);
                $table->foreign('shift_id')->references('id')->on('shifts')->cascadeOnDelete();
            });
        }
    }
};
