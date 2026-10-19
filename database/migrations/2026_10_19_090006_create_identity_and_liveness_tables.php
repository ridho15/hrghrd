<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('identity_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('onboarding_case_id')->constrained('onboarding_cases')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('id_number_encrypted')->nullable();
            $table->string('id_number_masked', 30);
            $table->string('full_name');
            $table->string('file_path');
            $table->string('file_hash', 64); // SHA-256
            $table->string('mime_type', 50);
            $table->unsignedInteger('file_size');
            $table->string('status')->default('pending'); // pending, approved, rejected, revision_requested
            $table->text('rejection_reason')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['onboarding_case_id', 'status']);
        });

        Schema::create('liveness_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('onboarding_case_id')->constrained('onboarding_cases')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('provider')->default('system_biometric_adapter');
            $table->string('selfie_path');
            $table->string('selfie_hash', 64); // SHA-256
            $table->decimal('liveness_score', 5, 2)->default(0.00);
            $table->string('result')->default('perlu_tinjau'); // lulus, gagal, perlu_tinjau
            $table->boolean('spoof_detected')->default(false);
            $table->json('anti_spoofing_metrics')->nullable();
            $table->string('status')->default('pending'); // pending, passed, failed, manual_review
            $table->text('review_notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['onboarding_case_id', 'result']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('liveness_checks');
        Schema::dropIfExists('identity_submissions');
    }
};
