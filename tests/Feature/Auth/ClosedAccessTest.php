<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Xoloff is a two-user system with no public registration (SPEC §2). These
 * tests guard that boundary rather than any individual feature.
 */
class ClosedAccessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{string}>
     */
    public static function guardedRoutes(): array
    {
        return [
            'dashboard' => ['dashboard'],
            'customers index' => ['customers.index'],
            'customers create' => ['customers.create'],
            'products index' => ['products.index'],
            'products create' => ['products.create'],
            'categories index' => ['product-categories.index'],
            'categories create' => ['product-categories.create'],
            'tax classes index' => ['tax-classes.index'],
            'tax classes create' => ['tax-classes.create'],
            'profile settings' => ['profile.edit'],
        ];
    }

    #[DataProvider('guardedRoutes')]
    public function test_guests_are_redirected_to_login(string $routeName)
    {
        $this->get(route($routeName))->assertRedirect(route('login'));
    }

    public function test_there_is_no_public_registration_route()
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register', [
            'name' => 'Intruder',
            'email' => 'intruder@example.test',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertNotFound();

        $this->assertDatabaseMissing('users', ['email' => 'intruder@example.test']);
    }

    public function test_an_unknown_email_cannot_authenticate()
    {
        $this->post(route('login.store'), [
            'email' => 'stranger@example.test',
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_a_known_user_with_the_wrong_password_cannot_authenticate()
    {
        $user = User::factory()->create();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'not-the-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_the_seeder_creates_exactly_two_users()
    {
        $this->seed(UserSeeder::class);

        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseHas('users', ['email' => 'jasper@emailjasper.com']);
        $this->assertDatabaseHas('users', ['email' => 'stephan@xolution.nl']);
    }

    public function test_the_seeder_is_idempotent()
    {
        $this->seed(UserSeeder::class);
        $this->seed(UserSeeder::class);

        $this->assertDatabaseCount('users', 2);
    }

    public function test_a_seeded_user_can_authenticate()
    {
        $this->seed(UserSeeder::class);

        $this->post(route('login.store'), [
            'email' => 'stephan@xolution.nl',
            'password' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();
    }
}
