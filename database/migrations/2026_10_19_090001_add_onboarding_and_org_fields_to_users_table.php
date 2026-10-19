<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('manager_id')->nullable()->after('position_id')->constrained('users')->nullOnDelete();
            $table->string('unit')->nullable()->after('manager_id');
            $table->string('employment_status')->default('active')->after('role');
            $table->string('entity_name')->default('PT HR Group Indonesia')->after('employment_status');
            $table->text('nik_encrypted')->nullable()->after('entity_name');
            $table->string('nik_masked', 30)->nullable()->after('nik_encrypted');
            $table->string('phone', 30)->nullable()->after('nik_masked');
            $table->text('address')->nullable()->after('phone');

            $table->index('employment_status');
            $table->index('manager_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['manager_id']);
            $table->dropColumn([
                'manager_id',
                'unit',
                'employment_status',
                'entity_name',
                'nik_encrypted',
                'nik_masked',
                'phone',
                'address',
            ]);
        });
    }
};
