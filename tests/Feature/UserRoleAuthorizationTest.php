<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserRoleAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function admin_access_to_admin_only_routes()
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        
        $this->actingAs($admin);

        // Test admin routes access
        $response = $this->get('/admin/projects');
        $response->assertOk();

        $response = $this->get('/admin/users');
        $response->assertOk();
    }

    /** @test */
    public function regular_user_blocked_from_admin_routes()
    {
        $user = User::factory()->create(['role' => User::ROLE_USER]);
        
        $this->actingAs($user);

        // Test admin routes are blocked
        $response = $this->get('/admin/projects');
        $response->assertStatus(403);

        $response = $this->get('/admin/users');
        $response->assertStatus(403);
    }

    /** @test */
    public function unauthenticated_user_redirected_to_login_from_protected_routes()
    {
        // Test protected routes redirect to login
        $response = $this->get('/activity');
        $response->assertRedirect('/login');

        $response = $this->get('/send_test');
        $response->assertRedirect('/login');

        $response = $this->get('/edit_profile');
        $response->assertRedirect('/login');
    }

    /** @test */
    public function unauthenticated_user_redirected_to_login_from_admin_routes()
    {
        // Test admin routes redirect to login
        $response = $this->get('/admin/projects');
        $response->assertRedirect('/login');

        $response = $this->get('/admin/users');
        $response->assertRedirect('/login');
    }

    /** @test */
    public function user_accessing_their_own_profile()
    {
        $user = User::factory()->create(['role' => User::ROLE_USER]);
        
        $this->actingAs($user);

        $response = $this->get('/edit_profile');
        $response->assertOk();
    }

    /** @test */
    public function authenticated_users_can_access_general_routes()
    {
        $user = User::factory()->create(['role' => User::ROLE_USER]);
        
        $this->actingAs($user);

        // Test general authenticated routes
        $response = $this->get('/');
        $response->assertOk();

        $response = $this->get('/activity');
        $response->assertOk();

        $response = $this->get('/send_test');
        $response->assertOk();

        $response = $this->get('/logout');
        $response->assertRedirect('/login');
    }

    /** @test */
    public function admin_users_can_access_all_routes()
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        
        $this->actingAs($admin);

        // Test admin can access general routes
        $response = $this->get('/');
        $response->assertOk();

        $response = $this->get('/activity');
        $response->assertOk();

        $response = $this->get('/send_test');
        $response->assertOk();

        // Test admin can access admin routes
        $response = $this->get('/admin/projects');
        $response->assertOk();

        $response = $this->get('/admin/users');
        $response->assertOk();
    }
}