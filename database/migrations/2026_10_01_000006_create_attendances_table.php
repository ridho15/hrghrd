<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_id')->unique()->constrained('shifts')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->dateTime('checkin_at')->nullable();
            $table->dateTime('checkout_at')->nullable();
            $table->string('status')->default('present');
            $table->unsignedInteger('late_minutes')->default(0);
            $table->unsignedInteger('late_units')->default(0);
            $table->unsignedInteger('overtime_minutes')->default(0);
            $table->foreignId('overtime_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('checkin_evidence')->nullable();
            $table->text('checkout_evidence')->nullable();
            $table->text('flags')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
