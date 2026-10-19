<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('signing_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_instance_id')->constrained('document_instances')->cascadeOnDelete();
            $table->string('provider')->default('internal_esign');
            $table->unsignedInteger('signing_order')->default(1);
            $table->string('signer_role'); // candidate, company
            $table->string('signer_name');
            $table->string('signer_email');
            $table->string('signer_phone')->nullable();
            $table->string('token', 64)->unique();
            $table->string('status')->default('pending'); // pending, signed, rejected, expired, cancelled
            $table->timestamp('signed_at')->nullable();
            $table->longText('signature_data')->nullable();
            $table->string('auth_method')->default('token_consent');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('device_metadata')->nullable();
            $table->string('evidence_pack_path')->nullable();
            $table->timestamps();

            $table->index(['document_instance_id', 'status']);
        });

        Schema::create('signature_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('signing_request_id')->constrained('signing_requests')->cascadeOnDelete();
            $table->string('event_type'); // sent, opened, consent_agreed, signed, verified, rejected, expired
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('payload')->nullable();
            $table->dateTime('created_at');

            $table->index('signing_request_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signature_events');
        Schema::dropIfExists('signing_requests');
    }
};
