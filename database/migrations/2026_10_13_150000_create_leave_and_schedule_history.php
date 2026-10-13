<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('name', 100);
            $table->string('timezone', 64);
            $table->time('start_time');
            $table->time('end_time');
            $table->json('working_days');
            $table->dateTime('effective_from');
            $table->dateTime('effective_until')->nullable();
            $table->index(['user_id', 'effective_from']);
        });
        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->date('holiday_date');
            $table->string('name', 100);
            $table->string('timezone', 64)->default('Asia/Manila');
            $table->timestamps();
            $table->unique(['holiday_date', 'timezone']);
        });
        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('employee_id', 32);
            $table->string('leave_type', 2);
            $table->date('start_date');
            $table->date('end_date');
            $table->string('reason', 500);
            $table->string('status', 10)->default('pending');
            $table->foreignId('approver_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('decided_at')->nullable();
            $table->string('remarks', 500)->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status', 'start_date']);
            $table->index(['status', 'start_date']);
        });
        Schema::create('leave_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leave_request_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->date('work_date');
            $table->unique(['user_id', 'work_date']);
        });
        Schema::create('leave_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leave_request_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('from_status', 10);
            $table->string('to_status', 10);
            $table->string('remarks', 500)->nullable();
            $table->dateTime('created_at');
        });
        foreach (DB::table('employee_profiles')->join('users', 'users.id', '=', 'employee_profiles.user_id')
            ->join('schedules', 'schedules.id', '=', 'employee_profiles.schedule_id')
            ->where('users.role', 'employee')->where('users.is_active', true)->where('users.requires_password_setup', false)
            ->whereNotNull('schedules.start_time')->whereNotNull('schedules.end_time')->whereNotNull('schedules.working_days')
            ->select(['employee_profiles.user_id', 'schedules.name', 'schedules.timezone', 'schedules.start_time', 'schedules.end_time', 'schedules.working_days'])->get() as $assignment) {
            DB::table('schedule_assignments')->insert([...get_object_vars($assignment), 'effective_from' => now('UTC')->format('Y-m-d H:i:s')]);
        }
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE leave_requests ADD CONSTRAINT leave_valid_values CHECK (start_date <= end_date AND leave_type IN ('VL','SL') AND status IN ('pending','approved','rejected','cancelled'))");
            foreach (['UPDATE', 'DELETE'] as $operation) {
                DB::unprepared("CREATE TRIGGER leave_audit_no_{$operation} BEFORE {$operation} ON leave_audits FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Leave audit records are append-only'");
            }
        } else {
            foreach (['UPDATE', 'DELETE'] as $operation) {
                DB::unprepared("CREATE TRIGGER leave_audit_no_{$operation} BEFORE {$operation} ON leave_audits BEGIN SELECT RAISE(ABORT, 'Leave audit records are append-only'); END");
            }
        }
    }

    public function down(): void
    {
        foreach (['leave_audits', 'leave_reservations', 'leave_requests', 'holidays', 'schedule_assignments'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
