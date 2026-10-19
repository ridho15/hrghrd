<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('org_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('supervisor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('position_id')->nullable()->constrained('positions')->nullOnDelete();
            $table->string('unit')->nullable();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('reason')->nullable();
            $table->foreignId('changed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'effective_from', 'effective_to'], 'org_assign_effective_idx');
            $table->index('supervisor_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('org_assignments');
    }
};
