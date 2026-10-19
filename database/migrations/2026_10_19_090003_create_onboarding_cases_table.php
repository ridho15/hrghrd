<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('onboarding_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('position_id')->constrained('positions')->cascadeOnDelete();
            $table->foreignId('supervisor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('draft');
            // draft, menunggu_persetujuan_hr, menunggu_tanda_tangan_offer, menunggu_tanda_tangan_nda, menunggu_ktp, menunggu_liveness, perlu_verifikasi, selesai, ditolak, kedaluwarsa, dibatalkan
            $table->unsignedBigInteger('salary_offered')->default(0);
            $table->json('benefits')->nullable();
            $table->date('start_date');
            $table->date('offer_valid_until');
            $table->string('token', 64)->unique();
            $table->timestamp('token_expires_at');
            $table->text('correction_notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('hr_approver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_by_hr_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'branch_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding_cases');
    }
};
