<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private User $projectAdmin;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->withTwoFactorEnrolled()->create(['super_admin' => true]);
        $this->projectAdmin = User::factory()->withTwoFactorEnrolled()->create(['super_admin' => false]);
        $this->project = Project::factory()->create();
        $this->project->users()->attach($this->projectAdmin->id, ['role' => 'admin']);
    }

    /** @test */
    public function project_admin_cannot_open_user_management_pages()
    {
        $this->actingAs($this->projectAdmin);

        $this->get('/admin/users')->assertForbidden();
        $this->get('/admin/users/create')->assertForbidden();
        $this->get("/admin/users/{$this->superAdmin->id}/edit")->assertForbidden();
    }

    /** @test */
    public function project_admin_cannot_grant_themselves_super_admin()
    {
        $this->actingAs($this->projectAdmin);

        $this->put("/admin/users/{$this->projectAdmin->id}", [
            'name' => $this->projectAdmin->name,
            'email' => $this->projectAdmin->email,
            'super_admin' => '1',
        ])->assertForbidden();

        $this->assertFalse($this->projectAdmin->fresh()->isSuperAdmin());
    }

    /** @test */
    public function project_admin_cannot_change_another_users_credentials()
    {
        $this->actingAs($this->projectAdmin);
        $originalEmail = $this->superAdmin->email;

        $this->put("/admin/users/{$this->superAdmin->id}", [
            'name' => 'Hijacked',
            'email' => 'attacker@example.com',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertForbidden();

        $this->assertSame($originalEmail, $this->superAdmin->fresh()->email);
    }

    /** @test */
    public function project_admin_cannot_create_invite_or_delete_users()
    {
        $this->actingAs($this->projectAdmin);
        $victim = User::factory()->create();

        $this->post('/admin/users', [
            'name' => 'New Super',
            'email' => 'new-super@example.com',
            'password' => 'password-123',
            'password_confirmation' => 'password-123',
            'super_admin' => '1',
        ])->assertForbidden();

        $this->post('/admin/users/invite', [
            'name' => 'Invited Super',
            'email' => 'invited-super@example.com',
            'super_admin' => '1',
        ])->assertForbidden();

        $this->delete("/admin/users/{$victim->id}")->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'new-super@example.com']);
        $this->assertDatabaseMissing('users', ['email' => 'invited-super@example.com']);
        $this->assertDatabaseHas('users', ['id' => $victim->id]);
    }

    /** @test */
    public function project_admin_keeps_project_management_but_sees_no_users_link()
    {
        $this->actingAs($this->projectAdmin);

        $this->get('/admin/projects')->assertOk()->assertDontSee('Manage Users');
        $this->get("/admin/projects/{$this->project->id}/edit")->assertOk();
    }

    /** @test */
    public function super_admin_can_still_manage_users()
    {
        $this->actingAs($this->superAdmin);
        $user = User::factory()->create();

        $this->get('/admin/users')->assertOk()->assertSee('Manage Users');

        $this->put("/admin/users/{$user->id}", [
            'name' => 'Renamed',
            'email' => $user->email,
        ])->assertRedirect('/admin/users');

        $this->assertSame('Renamed', $user->fresh()->name);
    }
}
