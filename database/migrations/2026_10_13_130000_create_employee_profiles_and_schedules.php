<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->timestamps();
        });

        Schema::create('employee_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('employee_id', 32)->unique();
            $table->string('department', 100)->nullable()->index();
            $table->string('position', 100)->nullable();
            $table->date('hire_date')->nullable();
            $table->foreignId('schedule_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('requires_password_setup')->default(false);
            $table->index(['role', 'is_active']);
        });

        // Keep existing accounts; do not infer missing employment details.
        DB::table('users')->where('role', 'employee')->orderBy('id')->chunkById(500, function ($users) {
            $profiles = $users->map(fn ($user) => [
                'user_id' => $user->id,
                'employee_id' => 'EMP-'.str_pad((string) $user->id, 6, '0', STR_PAD_LEFT),
                'created_at' => now(),
                'updated_at' => now(),
            ])->all();

            DB::table('employee_profiles')->insert($profiles);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_profiles');
        Schema::dropIfExists('schedules');
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role', 'is_active']);
            $table->dropColumn('requires_password_setup');
        });
    }
};
