<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Attendify - Web Routes
|--------------------------------------------------------------------------
|
| Admin and Employee authentication routes.
| Authentication is handled by Laravel Fortify.
|
*/

// Homepage
Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->route('dashboard');
    }

    return redirect()->route('login');
})->name('home');

// Main Dashboard - Redirect Based on Role
Route::middleware(['auth'])->group(function () {

    Route::get('/dashboard', function () {

        $user = Auth::user();

        if (! $user->is_active) {
            abort(403, 'Your account is inactive.');
        }

        if ($user->role === 'admin') {
            return redirect()->route('admin.dashboard');
        }

        if ($user->role === 'employee') {
            return redirect()->route('employee.dashboard');
        }

        abort(403, 'Invalid user role.');

    })->name('dashboard');

});

// Admin Dashboard
Route::middleware(['auth', 'role:admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {

        Route::livewire('/dashboard', 'admin.dashboard')
            ->name('dashboard');

        Route::livewire('/employees', 'admin.employees')->name('employees');
        Route::livewire('/attendance', 'admin.attendance')->name('attendance');
        Route::livewire('/schedules', 'admin.schedules')->name('schedules');
        Route::livewire('/leave', 'admin.leave')->name('leave');
        Route::livewire('/holidays', 'admin.holidays')->name('holidays');
        Route::livewire('/reports', 'admin.reports')->name('reports');
        Route::livewire('/workdays', 'admin.reports')->name('workdays');

    });

// Employee Dashboard
Route::middleware(['auth', 'role:employee'])
    ->prefix('employee')
    ->name('employee.')
    ->group(function () {

        Route::livewire('/dashboard', 'employee.dashboard')->name('dashboard');
        Route::livewire('/attendance', 'employee.attendance')->name('attendance');
        Route::livewire('/profile', 'employee.profile')->name('profile');
        Route::livewire('/schedule', 'employee.schedule')->name('schedule');
        Route::livewire('/leave', 'employee.leave')->name('leave');
        Route::livewire('/calendar', 'employee.calendar')->name('calendar');

    });

// Settings Routes (Laravel Starter Kit)
require __DIR__.'/settings.php';
Route::get('/debug-test', function () {
    file_put_contents(
        storage_path('logs/debug-test.txt'),
        'ROUTE EXECUTED'
    );

    return response('LARAVEL ROUTING WORKS', 200);
});
