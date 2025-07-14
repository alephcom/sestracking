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
        
        // Create test users
        User::create([
            'id' => 1,
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => Hash::make('password123'),
            'role' => User::ROLE_ADMIN,
        ]);

        User::create([
            'name' => 'Regular User',
            'email' => 'user@example.com',
            'password' => Hash::make('password123'),
            'role' => User::ROLE_USER,
        ]);
    }

    public function test_login_with_valid_admin_credentials()
    {
        $response = $this->post('/login', [
            'email' => 'admin@example.com',
            'password' => 'password123',
        ]);

        $response->assertRedirect('/');
        $this->assertAuthenticated();
        $this->assertEquals('admin@example.com', auth()->user()->email);
        $this->assertTrue(auth()->user()->isAdmin());
    }

    public function test_login_with_valid_regular_user_credentials()
    {
        $response = $this->post('/login', [
            'email' => 'user@example.com',
            'password' => 'password123',
        ]);

        $response->assertRedirect('/');
        $this->assertAuthenticated();
        $this->assertEquals('user@example.com', auth()->user()->email);
        $this->assertFalse(auth()->user()->isAdmin());
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
        $response = $this->post('/login', [
            'email' => 'admin@example.com',
            'password' => 'password123',
        ]);

        $this->assertAuthenticated();
        
        // Make another request to verify session persists
        $dashboardResponse = $this->get('/');
        $dashboardResponse->assertOk();
        $this->assertAuthenticated();
    }

    public function test_redirect_to_intended_page_after_login()
    {
        // Try to access protected page while unauthenticated
        $response = $this->get('/activity');
        $response->assertRedirect('/login');

        // Login and should be redirected to intended page (activity)
        $response = $this->post('/login', [
            'email' => 'admin@example.com',
            'password' => 'password123',
        ]);

        $response->assertRedirect('/activity');
    }

    public function test_unauthenticated_user_redirected_to_login()
    {
        $protectedRoutes = [
            '/',
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

        $response = $this->get('/');
        $response->assertOk();

        $response = $this->get('/activity');
        $response->assertOk();

        $response = $this->get('/send_test');
        $response->assertOk();
    }
}