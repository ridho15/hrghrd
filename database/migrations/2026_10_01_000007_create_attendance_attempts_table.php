<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained('shifts')->nullOnDelete();
            $table->string('action');
            $table->string('result');
            $table->string('reason')->nullable();
            $table->text('evidence')->nullable();
            $table->dateTime('server_at');

            $table->index(['user_id', 'server_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_attempts');
    }
};
