<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('document_template_versions');
        Schema::dropIfExists('document_templates');

        Schema::create('document_templates', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // offering_letter, nda, employment_contract
            $table->string('title');
            $table->string('code')->unique();
            $table->string('entity_name')->nullable();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('position_id')->nullable()->constrained('positions')->nullOnDelete();
            $table->unsignedBigInteger('active_version_id')->nullable();
            $table->timestamps();

            $table->index(['type', 'entity_name']);
        });

        Schema::create('document_template_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_template_id')->constrained('document_templates')->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('status')->default('draft'); // draft, in_review, approved, published, archived
            $table->longText('content');
            $table->json('clauses')->nullable();
            $table->json('required_variables')->nullable();
            $table->json('required_clauses')->nullable();
            $table->text('change_summary')->nullable();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['document_template_id', 'version_number'], 'doc_tpl_ver_unique');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_template_versions');
        Schema::dropIfExists('document_templates');
    }
};
