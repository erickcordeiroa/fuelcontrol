<?php

namespace Tests\Feature\Auth;

use App\Livewire\Layout\UserMenu;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Livewire\Volt\Volt;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response
            ->assertOk()
            ->assertSeeVolt('pages.auth.login');
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->admin()->create();

        $component = Volt::test('pages.auth.login')
            ->set('form.email', $user->email)
            ->set('form.password', 'password');

        $component->call('login');

        $component
            ->assertHasNoErrors()
            ->assertRedirect(route('reports', absolute: false));

        $this->assertAuthenticated();
    }

    public function test_drivers_cannot_log_in_on_the_web(): void
    {
        $driver = User::factory()->driverRole()->create();

        Volt::test('pages.auth.login')
            ->set('form.email', $driver->email)
            ->set('form.password', 'password')
            ->call('login')
            ->assertHasErrors(['form.email'])
            ->assertNoRedirect();

        $this->assertGuest();
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $component = Volt::test('pages.auth.login')
            ->set('form.email', $user->email)
            ->set('form.password', 'wrong-password');

        $component->call('login');

        $component
            ->assertHasErrors()
            ->assertNoRedirect();

        $this->assertGuest();
    }

    public function test_old_dashboard_url_redirects_to_reports(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $this->get('/dashboard')->assertRedirect('/relatorios');
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $component = Livewire::test(UserMenu::class);

        $component->call('logout');

        $component
            ->assertHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
    }
}
