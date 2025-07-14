<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginLogoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    /** @test */
    public function login_with_valid_admin_credentials()
    {
        $user = User::factory()->create([
            'email' => 'test-admin@example.com',
            'password' => bcrypt('password'),
            'role' => User::ROLE_ADMIN
        ]);

        $response = $this->post('/login', [
            'email' => 'test-admin@example.com',
            'password' => 'password'
        ]);

        $response->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
    }

    /** @test */
    public function login_with_valid_regular_user_credentials()
    {
        $user = User::factory()->create([
            'email' => 'test-user@example.com',
            'password' => bcrypt('password'),
            'role' => User::ROLE_USER
        ]);

        $response = $this->post('/login', [
            'email' => 'test-user@example.com',
            'password' => 'password'
        ]);

        $response->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
    }

    /** @test */
    public function login_with_invalid_email()
    {
        $response = $this->post('/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'password',
            'submit' => true
        ]);

        $response->assertViewIs('auth.login');
        $response->assertViewHas('error', 'Invalid email or password.');
        $this->assertGuest();
    }

    /** @test */
    public function login_with_invalid_password()
    {
        User::factory()->create([
            'email' => 'test-admin2@example.com',
            'password' => bcrypt('password')
        ]);

        $response = $this->post('/login', [
            'email' => 'test-admin2@example.com',
            'password' => 'wrongpassword',
            'submit' => true
        ]);

        $response->assertViewIs('auth.login');
        $response->assertViewHas('error', 'Invalid email or password.');
        $this->assertGuest();
    }

    /** @test */
    public function login_with_empty_credentials()
    {
        $response = $this->post('/login', [
            'email' => '',
            'password' => '',
            'submit' => true
        ]);

        $response->assertViewIs('auth.login');
        $response->assertViewHas('error', 'Invalid email or password.');
        $this->assertGuest();
    }

    /** @test */
    public function logout_functionality()
    {
        $user = User::factory()->create();
        
        $this->actingAs($user);
        $this->assertAuthenticated();

        $response = $this->get('/logout');

        $response->assertRedirect('/login');
        $this->assertGuest();
    }

    /** @test */
    public function session_persistence_after_login()
    {
        $user = User::factory()->create([
            'email' => 'test-admin3@example.com',
            'password' => bcrypt('password')
        ]);

        $this->post('/login', [
            'email' => 'test-admin3@example.com',
            'password' => 'password'
        ]);

        $this->assertAuthenticated();
        
        // Make another request to verify session persists
        $response = $this->get('/');
        $response->assertOk();
        $this->assertAuthenticated();
    }

    /** @test */
    public function redirect_to_intended_page_after_login()
    {
        $user = User::factory()->create([
            'email' => 'test-admin4@example.com',
            'password' => bcrypt('password')
        ]);

        // Try to access protected page while unauthenticated
        $this->get('/activity');
        
        // Login
        $response = $this->post('/login', [
            'email' => 'test-admin4@example.com',
            'password' => 'password'
        ]);

        // Should redirect to intended page (activity) or default (/)
        $response->assertRedirect('/activity');
    }

    /** @test */
    public function unauthenticated_user_redirected_to_login()
    {
        $response = $this->get('/activity');
        
        $response->assertRedirect('/login');
        $this->assertGuest();
    }
}