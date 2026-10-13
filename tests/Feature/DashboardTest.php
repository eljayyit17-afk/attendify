<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_employees_are_redirected_to_their_dashboard(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('employee.dashboard'));
    }

    public function test_admins_see_real_employee_counts_and_recent_accounts(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        User::factory()->create(['name' => 'Active employee', 'role' => 'employee', 'is_active' => true]);
        User::factory()->create(['name' => 'Inactive employee', 'role' => 'employee', 'is_active' => false]);

        $this->actingAs($admin)->get(route('dashboard'))->assertRedirect(route('admin.dashboard'));
        $this->get(route('admin.dashboard'))->assertOk()
            ->assertSee('Workforce overview')->assertSee('Active employee')->assertSee('Inactive employee')
            ->assertSee('No attendance records yet');

        Livewire::test('admin.dashboard')
            ->assertViewHas('totalEmployees', 2)->assertViewHas('activeEmployees', 1);
    }

    public function test_admin_pages_are_protected_from_employees_and_inactive_admins(): void
    {
        foreach ([['role' => 'employee'], ['role' => 'admin', 'is_active' => false]] as $attributes) {
            $this->actingAs(User::factory()->create($attributes));

            foreach (['dashboard', 'employees', 'attendance', 'schedules', 'reports'] as $page) {
                $this->get(route('admin.'.$page))->assertForbidden();
            }
        }
    }

    public function test_employee_search_excludes_admins_and_resets_pagination(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin', 'name' => 'Administrator']));
        User::factory()->count(16)->create(['role' => 'employee']);
        User::factory()->create(['role' => 'employee', 'name' => 'Morgan Example']);

        Livewire::test('admin.employees')
            ->call('setPage', 2)->set('search', 'Morgan Example')
            ->assertSee('Morgan Example')->assertDontSee('Administrator')
            ->assertViewHas('employees', fn ($employees) => $employees->total() === 1 && $employees->currentPage() === 1);
    }

    public function test_admin_workspace_pages_and_logout_work(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        foreach (['employees', 'attendance', 'schedules', 'reports'] as $page) {
            $this->get(route('admin.'.$page))->assertOk();
        }

        $this->post(route('logout'))->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_livewire_updates_recheck_admin_access(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);
        $component = Livewire::test('admin.employees');
        $admin->forceFill(['is_active' => false])->save();

        $component->set('search', 'employee')->assertForbidden();
    }
}
