<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $admin = User::create([
            'id' => 1,
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => Hash::make('password123'),
            'super_admin' => true,
        ]);
        $this->enrollAppTwoFactor($admin);

        $user = User::create([
            'name' => 'Regular User',
            'email' => 'user@example.com',
            'password' => Hash::make('password123'),
            'super_admin' => false,
        ]);
        $this->enrollAppTwoFactor($user);
    }

    public function test_login_with_valid_admin_credentials()
    {
        $this->post('/login', [
            'email' => 'admin@example.com',
            'password' => 'password123',
            'submit' => true,
        ]);

        $this->assertGuest();
        $this->assertTrue(session()->has('login.two_factor_pending_user_id'));

        $admin = User::where('email', 'admin@example.com')->first();
        $this->submitTwoFactorChallenge($admin);

        $this->assertAuthenticated();
        $this->assertEquals('admin@example.com', auth()->user()->email);
        $this->assertTrue(auth()->user()->isSuperAdmin());
    }

    public function test_login_with_valid_regular_user_credentials()
    {
        $this->post('/login', [
            'email' => 'user@example.com',
            'password' => 'password123',
            'submit' => true,
        ]);

        $this->assertGuest();
        $user = User::where('email', 'user@example.com')->first();
        $this->submitTwoFactorChallenge($user);

        $this->assertAuthenticated();
        $this->assertEquals('user@example.com', auth()->user()->email);
        $this->assertFalse(auth()->user()->isSuperAdmin());
    }

    public function test_login_with_invalid_email()
    {
        $response = $this->post('/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'password123',
            'submit' => true,
        ]);

        $response->assertOk();
        $response->assertViewIs('auth.login');
        $response->assertViewHas('error', 'Invalid email or password.');
        $this->assertGuest();
    }

    public function test_login_with_invalid_password()
    {
        $response = $this->post('/login', [
            'email' => 'admin@example.com',
            'password' => 'wrongpassword',
            'submit' => true,
        ]);

        $response->assertOk();
        $response->assertViewIs('auth.login');
        $response->assertViewHas('error', 'Invalid email or password.');
        $this->assertGuest();
    }

    public function test_login_with_empty_credentials()
    {
        $response = $this->post('/login', [
            'email' => '',
            'password' => '',
            'submit' => true,
        ]);

        $response->assertOk();
        $response->assertViewIs('auth.login');
        $response->assertViewHas('error', 'Invalid email or password.');
        $this->assertGuest();
    }

    public function test_logout_functionality()
    {
        $user = User::where('email', 'admin@example.com')->first();

        $this->actingAs($user);
        $this->assertAuthenticated();

        $response = $this->get('/logout');

        $response->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_session_persistence_after_login()
    {
        User::create([
            'name' => 'Session User',
            'email' => 'session@example.com',
            'password' => Hash::make('password123'),
            'super_admin' => false,
        ]);

        $this->post('/login', [
            'email' => 'session@example.com',
            'password' => 'password123',
            'submit' => true,
        ]);

        $this->assertAuthenticated();

        $dashboardResponse = $this->get('/');
        $dashboardResponse->assertOk();
        $this->assertAuthenticated();
    }

    public function test_redirect_to_intended_page_after_login()
    {
        $this->get('/activity');
        $this->post('/login', [
            'email' => 'admin@example.com',
            'password' => 'password123',
            'submit' => true,
        ]);

        $admin = User::where('email', 'admin@example.com')->first();
        $this->submitTwoFactorChallenge($admin);

        $this->get('/activity')->assertOk();
    }

    public function test_unauthenticated_user_redirected_to_login()
    {
        $protectedRoutes = [
            '/dashboard',
            '/dashboard/api',
            '/activity',
            '/activity/list/api',
            '/activity/details/api',
            '/activity/export',
            '/send_test',
            '/edit_profile',
        ];

        foreach ($protectedRoutes as $route) {
            $response = $this->get($route);
            $response->assertRedirect('/login');
        }
    }

    public function test_authenticated_user_can_access_protected_routes()
    {
        $user = User::where('email', 'admin@example.com')->first();
        $this->actingAs($user);

        $response = $this->get('/dashboard');
        $response->assertOk();

        $response = $this->get('/activity');
        $response->assertOk();

        $response = $this->get('/send_test');
        $response->assertOk();
    }
}
