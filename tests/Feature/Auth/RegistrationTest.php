<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response
            ->assertOk()
            ->assertSeeVolt('pages.auth.register');
    }

    public function test_new_companies_can_register(): void
    {
        $component = Volt::test('pages.auth.register')
            ->set('company_name', 'Transportes Silva Ltda')
            ->set('name', 'Maria Silva')
            ->set('email', 'maria@transportes-silva.test')
            ->set('password', 'password')
            ->set('password_confirmation', 'password');

        $component->call('register');

        $component
            ->assertHasNoErrors()
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();

        $this->assertDatabaseHas('users', [
            'email' => 'maria@transportes-silva.test',
            'company_name' => 'Transportes Silva Ltda',
            'name' => 'Maria Silva',
            'role' => UserRole::Admin->value,
        ]);
    }

    public function test_registration_requires_company_name(): void
    {
        Volt::test('pages.auth.register')
            ->set('company_name', '')
            ->set('name', 'Test')
            ->set('email', 'test@example.com')
            ->set('password', 'password')
            ->set('password_confirmation', 'password')
            ->call('register')
            ->assertHasErrors(['company_name']);
    }
}
