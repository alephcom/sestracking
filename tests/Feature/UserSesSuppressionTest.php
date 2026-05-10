<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\SesSuppressedDestination;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class UserSesSuppressionTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function guest_is_redirected_from_suppression_chooser(): void
    {
        $this->get(route('ses-suppression.chooser'))
            ->assertRedirect(route('login'));
    }

    /** @test */
    public function guest_is_redirected_from_user_suppression_list(): void
    {
        $project = Project::factory()->create();

        $this->get(route('ses-suppression.index', $project))
            ->assertRedirect(route('login'));
    }

    /** @test */
    public function project_member_with_user_role_can_view_user_suppression_list(): void
    {
        $user = User::factory()->withTwoFactorEnrolled()->create();
        $project = Project::factory()->create([
            'ses_aws_access_key_id' => 'AKIATESTUSER',
            'ses_aws_secret_access_key' => 'secret-for-user-suppression-test',
            'ses_aws_default_region' => 'us-east-1',
        ]);
        $user->projects()->attach($project->id, ['role' => User::ROLE_USER]);

        SesSuppressedDestination::query()->create([
            'project_id' => $project->id,
            'email' => 'blocked@example.com',
            'reason' => 'BOUNCE',
            'last_update_time' => now(),
            'synced_at' => now(),
        ]);

        $response = $this->actingAs($user)
            ->get(route('ses-suppression.index', $project));

        $response->assertOk();
        $response->assertSee('blocked@example.com', false);
    }

    /** @test */
    public function user_without_project_access_cannot_view_user_suppression_list(): void
    {
        $user = User::factory()->withTwoFactorEnrolled()->create();
        $otherProject = Project::factory()->create();
        $user->projects()->attach($otherProject->id, ['role' => User::ROLE_USER]);

        $lockedProject = Project::factory()->create();

        $this->actingAs($user)
            ->get(route('ses-suppression.index', $lockedProject))
            ->assertForbidden();
    }

    /** @test */
    public function suppression_chooser_lists_accessible_projects(): void
    {
        $user = User::factory()->withTwoFactorEnrolled()->create();
        $project = Project::factory()->create(['name' => 'Alpha Tracking']);
        $user->projects()->attach($project->id, ['role' => User::ROLE_USER]);

        $response = $this->actingAs($user)
            ->get(route('ses-suppression.chooser'));

        $response->assertOk();
        $response->assertSee('Alpha Tracking', false);
        $response->assertSee('Open suppression list', false);
    }

    /** @test */
    public function suppression_list_search_filters_by_email_substring(): void
    {
        $user = User::factory()->withTwoFactorEnrolled()->create();
        $project = Project::factory()->create([
            'ses_aws_access_key_id' => 'AKIASEARCH1',
            'ses_aws_secret_access_key' => 'secret-search-test',
            'ses_aws_default_region' => 'us-east-1',
        ]);
        $user->projects()->attach($project->id, ['role' => User::ROLE_USER]);

        SesSuppressedDestination::query()->create([
            'project_id' => $project->id,
            'email' => 'alpha-match@example.com',
            'reason' => 'BOUNCE',
            'last_update_time' => now(),
            'synced_at' => now(),
        ]);
        SesSuppressedDestination::query()->create([
            'project_id' => $project->id,
            'email' => 'other@example.com',
            'reason' => 'COMPLAINT',
            'last_update_time' => now(),
            'synced_at' => now(),
        ]);

        $response = $this->actingAs($user)
            ->get(route('ses-suppression.index', ['project' => $project, 'q' => 'alpha-match']));

        $response->assertOk();
        $response->assertSee('alpha-match@example.com', false);
        $response->assertDontSee('other@example.com', false);
    }

    /** @test */
    public function suppression_list_sort_orders_emails(): void
    {
        $user = User::factory()->withTwoFactorEnrolled()->create();
        $project = Project::factory()->create([
            'ses_aws_access_key_id' => 'AKIASORT1',
            'ses_aws_secret_access_key' => 'secret-sort-test',
            'ses_aws_default_region' => 'us-east-1',
        ]);
        $user->projects()->attach($project->id, ['role' => User::ROLE_USER]);

        SesSuppressedDestination::query()->create([
            'project_id' => $project->id,
            'email' => 'zebra@example.com',
            'reason' => 'BOUNCE',
            'last_update_time' => now(),
            'synced_at' => now(),
        ]);
        SesSuppressedDestination::query()->create([
            'project_id' => $project->id,
            'email' => 'apple@example.com',
            'reason' => 'BOUNCE',
            'last_update_time' => now(),
            'synced_at' => now(),
        ]);

        $response = $this->actingAs($user)
            ->get(route('ses-suppression.index', [
                'project' => $project,
                'sort' => 'email',
                'direction' => 'asc',
            ]));

        $response->assertOk();
        $content = $response->getContent();
        $this->assertLessThan(
            strpos($content, 'zebra@example.com'),
            strpos($content, 'apple@example.com'),
            'Expected ascending email sort (apple before zebra) in HTML output'
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
