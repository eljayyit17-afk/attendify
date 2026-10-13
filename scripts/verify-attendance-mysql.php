<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (DB::getDriverName() !== 'mysql') {
    throw new RuntimeException('This verification requires the MySQL connection.');
}

// This connection-local table disappears when the connection closes.
// No accounts, schedules, or attendance records are added to application tables.
DB::statement('CREATE TEMPORARY TABLE attendify_attendance_probe LIKE attendance_sessions');
$data = [
    'user_id' => 999999, 'work_date' => '2026-10-13',
    'schedule_name' => 'Constraint verification', 'schedule_timezone' => 'Asia/Manila',
    'scheduled_start_at' => '2026-10-13 01:00:00', 'scheduled_end_at' => '2026-10-13 09:00:00',
    'clock_in_at' => '2026-10-13 01:00:00',
];
$mustReject = function (Closure $operation, int $code): void {
    try {
        $operation();
    } catch (QueryException $exception) {
        if (($exception->errorInfo[1] ?? null) === $code) {
            return;
        }
        throw $exception;
    }
    throw new RuntimeException('An attendance constraint did not reject an invalid write.');
};

DB::table('attendify_attendance_probe')->insert($data);
$mustReject(fn () => DB::table('attendify_attendance_probe')->insert(array_replace($data, ['work_date' => '2026-10-14'])), 1062);
DB::table('attendify_attendance_probe')->update(['clock_out_at' => '2026-10-13 09:00:00']);
$mustReject(fn () => DB::table('attendify_attendance_probe')->insert(array_replace($data, ['clock_out_at' => '2026-10-13 09:00:00'])), 1062);
$mustReject(fn () => DB::table('attendify_attendance_probe')->insert(array_replace($data, ['work_date' => '2026-10-14', 'clock_out_at' => '2026-10-13 00:00:00'])), 3819);
$triggers = DB::table('information_schema.TRIGGERS')->whereRaw('TRIGGER_SCHEMA = DATABASE()')
    ->whereIn('TRIGGER_NAME', ['attendance_corrections_no_update', 'attendance_corrections_no_delete'])->count();
if ($triggers !== 2) {
    throw new RuntimeException('Attendance audit protection triggers are missing.');
}
DB::statement('CREATE TEMPORARY TABLE attendify_leave_probe LIKE leave_requests');
$leave = ['user_id' => 999999, 'employee_id' => 'EMP-PROBE', 'leave_type' => 'VL', 'start_date' => '2026-10-13', 'end_date' => '2026-10-14', 'reason' => 'Temporary constraint verification', 'status' => 'pending'];
$mustReject(fn () => DB::table('attendify_leave_probe')->insert(array_replace($leave, ['leave_type' => 'XX'])), 3819);
$mustReject(fn () => DB::table('attendify_leave_probe')->insert(array_replace($leave, ['end_date' => '2026-10-12'])), 3819);
DB::statement('CREATE TEMPORARY TABLE attendify_leave_days_probe LIKE leave_reservations');
$reservation = ['leave_request_id' => 999999, 'user_id' => 999999, 'work_date' => '2026-10-13'];
DB::table('attendify_leave_days_probe')->insert($reservation);
$mustReject(fn () => DB::table('attendify_leave_days_probe')->insert(array_replace($reservation, ['leave_request_id' => 999998])), 1062);
$leaveTriggers = DB::table('information_schema.TRIGGERS')->whereRaw('TRIGGER_SCHEMA = DATABASE()')
    ->whereIn('TRIGGER_NAME', ['leave_audit_no_UPDATE', 'leave_audit_no_DELETE'])->count();
if ($leaveTriggers !== 2) {
    throw new RuntimeException('Leave audit protection triggers are missing.');
}
echo json_encode(['single_active_session' => true, 'unique_work_date' => true, 'clock_order' => true, 'audit_triggers_installed' => true,
    'leave_values_and_dates' => true, 'leave_overlap_reservations' => true, 'leave_audit_triggers_installed' => true], JSON_THROW_ON_ERROR).PHP_EOL;
