<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->string('timezone', 64)->default('Asia/Manila');
            $table->json('working_days')->nullable();
        });

        Schema::create('attendance_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->date('work_date');
            $table->string('schedule_name', 100);
            $table->string('schedule_timezone', 64);
            $table->dateTime('scheduled_start_at');
            $table->dateTime('scheduled_end_at');
            $table->dateTime('clock_in_at');
            $table->dateTime('clock_out_at')->nullable();
            $table->unsignedBigInteger('active_user_id')->nullable()
                ->storedAs('CASE WHEN clock_out_at IS NULL THEN user_id ELSE NULL END')->unique();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->unique(['user_id', 'work_date']);
            $table->index('work_date');
        });

        Schema::create('attendance_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_session_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->text('reason');
            $table->json('before_values');
            $table->json('after_values');
            $table->dateTime('created_at');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE attendance_sessions ADD CONSTRAINT attendance_clock_order CHECK (clock_out_at IS NULL OR clock_out_at >= clock_in_at)');
            DB::unprepared("CREATE TRIGGER attendance_corrections_no_update BEFORE UPDATE ON attendance_corrections FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Attendance audit records are append-only'");
            DB::unprepared("CREATE TRIGGER attendance_corrections_no_delete BEFORE DELETE ON attendance_corrections FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Attendance audit records are append-only'");
        } elseif (DB::getDriverName() === 'sqlite') {
            foreach (['INSERT', 'UPDATE'] as $operation) {
                DB::unprepared("CREATE TRIGGER attendance_order_{$operation} BEFORE {$operation} ON attendance_sessions WHEN NEW.clock_out_at IS NOT NULL AND NEW.clock_out_at < NEW.clock_in_at BEGIN SELECT RAISE(ABORT, 'Invalid attendance clock order'); END");
            }
            foreach (['UPDATE', 'DELETE'] as $operation) {
                DB::unprepared("CREATE TRIGGER attendance_audit_{$operation} BEFORE {$operation} ON attendance_corrections BEGIN SELECT RAISE(ABORT, 'Attendance audit records are append-only'); END");
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_corrections');
        Schema::dropIfExists('attendance_sessions');
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropColumn(['start_time', 'end_time', 'timezone', 'working_days']);
        });
    }
};
