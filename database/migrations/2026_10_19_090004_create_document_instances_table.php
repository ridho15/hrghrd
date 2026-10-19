<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_instances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('onboarding_case_id')->nullable()->constrained('onboarding_cases')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type'); // offering_letter, nda, employment_contract
            $table->foreignId('document_template_version_id')->nullable()->constrained('document_template_versions')->nullOnDelete();
            $table->string('document_number')->unique();
            $table->unsignedInteger('version')->default(1);
            $table->string('status')->default('draft');
            // draft, siap_dikirim, menunggu_tanda_tangan, ditandatangani, ditolak, kedaluwarsa, dibatalkan
            $table->longText('rendered_content');
            $table->string('file_path')->nullable();
            $table->string('file_hash', 64)->nullable(); // SHA-256
            $table->json('data_snapshot');
            $table->json('missing_fields')->nullable();
            $table->json('review_flags')->nullable();
            $table->boolean('ready_for_review')->default(false);
            $table->timestamp('hr_approved_at')->nullable();
            $table->foreignId('hr_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('signed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['type', 'status']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_instances');
    }
};
