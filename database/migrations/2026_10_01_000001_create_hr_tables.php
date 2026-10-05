<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $t) {
            $t->id(); $t->string('code')->unique(); $t->string('name');
            $t->decimal('latitude', 10, 7)->nullable(); $t->decimal('longitude', 10, 7)->nullable();
            $t->unsignedInteger('radius_m')->default(100); $t->string('qr_secret');
            $t->boolean('active')->default(true); $t->timestamps();
        });
        Schema::create('positions', function (Blueprint $t) {
            $t->id(); $t->string('name')->unique(); $t->timestamps();
        });
        Schema::create('settings', function (Blueprint $t) {
            $t->string('key')->primary(); $t->string('value'); $t->timestamps();
        });
        Schema::create('shifts', function (Blueprint $t) {
            $t->id(); $t->foreignId('user_id')->index(); $t->foreignId('branch_id')->index();
            $t->dateTime('start_at'); $t->dateTime('end_at');
            $t->string('status')->default('draft'); $t->unsignedInteger('version')->default(1);
            $t->foreignId('approved_by')->nullable(); $t->dateTime('approved_at')->nullable();
            $t->timestamps(); $t->index(['user_id','start_at']);
        });
        Schema::create('attendances', function (Blueprint $t) {
            $t->id(); $t->foreignId('shift_id')->unique(); $t->foreignId('user_id')->index();
            $t->dateTime('checkin_at')->nullable(); $t->dateTime('checkout_at')->nullable();
            $t->string('status')->default('present'); $t->unsignedInteger('late_minutes')->default(0);
            $t->unsignedInteger('late_units')->default(0); $t->unsignedInteger('overtime_minutes')->default(0);
            $t->foreignId('overtime_approved_by')->nullable();
            $t->text('checkin_evidence')->nullable(); $t->text('checkout_evidence')->nullable();
            $t->text('flags')->nullable(); $t->timestamps();
        });
        Schema::create('attendance_attempts', function (Blueprint $t) {
            $t->id(); $t->foreignId('user_id')->index(); $t->foreignId('shift_id')->nullable();
            $t->string('action'); $t->string('result'); $t->string('reason')->nullable();
            $t->text('evidence')->nullable(); $t->dateTime('server_at');
        });
        Schema::create('attendance_exceptions', function (Blueprint $t) {
            $t->id(); $t->foreignId('user_id'); $t->foreignId('shift_id'); $t->string('action');
            $t->text('reason'); $t->string('status')->default('pending');
            $t->foreignId('reviewed_by')->nullable(); $t->text('review_note')->nullable();
            $t->dateTime('reviewed_at')->nullable(); $t->timestamps();
        });
        Schema::create('leave_requests', function (Blueprint $t) {
            $t->id(); $t->foreignId('user_id')->index(); $t->foreignId('created_by'); $t->string('type');
            $t->date('start_date'); $t->date('end_date'); $t->text('reason');
            $t->string('certificate_path')->nullable(); $t->string('certificate_name')->nullable();
            $t->string('status')->default('pending'); $t->foreignId('reviewed_by')->nullable();
            $t->text('review_note')->nullable(); $t->dateTime('reviewed_at')->nullable(); $t->timestamps();
        });
        Schema::create('leave_days', function (Blueprint $t) {
            $t->id(); $t->foreignId('leave_request_id')->index(); $t->date('date');
            $t->string('status')->default('pending'); $t->boolean('paid')->default(false);
            $t->unique(['leave_request_id','date']);
        });
        Schema::create('payroll_adjustments', function (Blueprint $t) {
            $t->id(); $t->foreignId('user_id')->index(); $t->string('month',7);
            $t->bigInteger('amount'); $t->text('reason'); $t->foreignId('created_by'); $t->timestamps();
        });
        Schema::create('payroll_runs', function (Blueprint $t) {
            $t->id(); $t->foreignId('branch_id'); $t->string('month',7);
            $t->string('status')->default('draft'); $t->foreignId('approved_by')->nullable();
            $t->dateTime('approved_at')->nullable(); $t->foreignId('locked_by')->nullable();
            $t->dateTime('locked_at')->nullable(); $t->timestamps(); $t->unique(['branch_id','month']);
        });
        Schema::create('payroll_lines', function (Blueprint $t) {
            $t->id(); $t->foreignId('payroll_run_id'); $t->foreignId('user_id');
            $t->text('breakdown'); $t->bigInteger('net'); $t->timestamps();
            $t->unique(['payroll_run_id','user_id']);
        });
        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id(); $t->foreignId('actor_id')->nullable(); $t->string('subject_type');
            $t->unsignedBigInteger('subject_id'); $t->string('action'); $t->text('before')->nullable();
            $t->text('after')->nullable(); $t->text('reason')->nullable();
            $t->string('ip',45)->nullable(); $t->dateTime('created_at');
            $t->index(['subject_type','subject_id']);
        });
    }

    public function down(): void
    {
        foreach (['audit_logs','payroll_lines','payroll_runs','payroll_adjustments','leave_days',
            'leave_requests','attendance_exceptions','attendance_attempts','attendances','shifts',
            'settings','positions','branches'] as $table) Schema::dropIfExists($table);
    }
};
