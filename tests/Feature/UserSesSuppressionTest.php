<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use App\Services\SesSuppressionService;
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
        $project = Project::factory()->create();
        $user->projects()->attach($project->id, ['role' => User::ROLE_USER]);

        $this->mock(SesSuppressionService::class, function ($mock): void {
            $mock->shouldReceive('listSuppressedDestinations')
                ->once()
                ->andReturn([
                    'summaries' => [
                        [
                            'email' => 'blocked@example.com',
                            'reason' => 'BOUNCE',
                            'last_update_time' => '2025-06-01T12:00:00.000Z',
                        ],
                    ],
                    'next_token' => null,
                ]);
        });

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

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
