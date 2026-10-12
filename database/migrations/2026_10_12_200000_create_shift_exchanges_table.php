<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_exchanges', function (Blueprint $table) {
            $table->id();
            $table->string('type')->default('replacement'); // replacement | swap
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('requester_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('requester_shift_id')->constrained('shifts')->restrictOnDelete();
            $table->foreignId('replacement_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('target_shift_id')->nullable()->constrained('shifts')->restrictOnDelete();
            $table->text('reason');
            $table->text('replacement_note')->nullable();
            $table->text('review_note')->nullable();
            $table->string('status')->default('pending_peer'); // pending_peer, pending_manager, approved, rejected, cancelled
            $table->dateTime('peer_responded_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'status']);
            $table->index(['requester_id', 'status']);
            $table->index(['replacement_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_exchanges');
    }
};
