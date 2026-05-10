<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\SesSuppressedDestination;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ProjectSesSuppressionControllerTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function guest_is_redirected_from_suppression_list(): void
    {
        $project = Project::factory()->create();

        $this->get(route('admin.projects.ses-suppression.index', $project))
            ->assertRedirect(route('login'));
    }

    /** @test */
    public function project_admin_can_view_suppression_list_page(): void
    {
        $user = User::factory()->withTwoFactorEnrolled()->create();
        $project = Project::factory()->create([
            'ses_aws_access_key_id' => 'AKIATESTADMIN',
            'ses_aws_secret_access_key' => 'secret-for-admin-suppression-test',
            'ses_aws_default_region' => 'us-east-1',
        ]);
        $user->projects()->attach($project->id, ['role' => User::ROLE_ADMIN]);

        SesSuppressedDestination::query()->create([
            'project_id' => $project->id,
            'email' => 'blocked@example.com',
            'reason' => 'BOUNCE',
            'last_update_time' => now(),
            'synced_at' => now(),
        ]);

        $response = $this->actingAs($user)
            ->get(route('admin.projects.ses-suppression.index', $project));

        $response->assertOk();
        $response->assertSee('blocked@example.com', false);
    }

    /** @test */
    public function suppression_index_shows_configuration_message_when_project_keys_missing(): void
    {
        $user = User::factory()->withTwoFactorEnrolled()->create();
        $project = Project::factory()->create([
            'ses_aws_access_key_id' => null,
            'ses_aws_secret_access_key' => null,
        ]);
        $user->projects()->attach($project->id, ['role' => User::ROLE_ADMIN]);

        $response = $this->actingAs($user)
            ->get(route('admin.projects.ses-suppression.index', $project));

        $response->assertOk();
        $response->assertSee('Per-project AWS Access Key ID', false);
    }

    /** @test */
    public function user_who_is_not_admin_for_project_cannot_view_suppression_list(): void
    {
        $user = User::factory()->withTwoFactorEnrolled()->create();
        $otherProject = Project::factory()->create();
        $user->projects()->attach($otherProject->id, ['role' => User::ROLE_ADMIN]);

        $lockedProject = Project::factory()->create();

        $response = $this->actingAs($user)
            ->get(route('admin.projects.ses-suppression.index', $lockedProject));

        $response->assertForbidden();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
