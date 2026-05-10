<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminProjectUpdateSesCredentialsTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function update_preserves_stored_ses_access_key_and_region_when_those_fields_are_absent_from_the_request(): void
    {
        $user = User::factory()->withTwoFactorEnrolled()->create();
        $project = Project::factory()->create([
            'name' => 'Original',
            'ses_aws_access_key_id' => 'AKIAKEEPME123',
            'ses_aws_secret_access_key' => 'secret-for-suppression-test',
            'ses_aws_default_region' => 'us-west-2',
            'ses_suppression_auto_push_enabled' => false,
        ]);
        $user->projects()->attach($project->id, ['role' => User::ROLE_ADMIN]);

        $response = $this->actingAs($user)->put(route('admin.projects.update', $project), [
            'name' => 'Renamed project',
            'admins' => [(string) $user->id],
            'ses_suppression_auto_push_enabled' => '0',
            'ses_suppression_push_complaints' => '1',
            'ses_suppression_push_soft_bounces' => '0',
        ]);

        $response->assertRedirect(route('admin.projects.index'));

        $project->refresh();
        $this->assertSame('Renamed project', $project->name);
        $this->assertSame('AKIAKEEPME123', $project->ses_aws_access_key_id);
        $this->assertSame('us-west-2', $project->ses_aws_default_region);
        $this->assertSame('secret-for-suppression-test', $project->ses_aws_secret_access_key);
    }

    /** @test */
    public function edit_page_shows_saved_access_key_and_region_when_flashed_old_input_omits_those_keys(): void
    {
        $user = User::factory()->withTwoFactorEnrolled()->create();
        $project = Project::factory()->create([
            'ses_aws_access_key_id' => 'AKIASHOW123',
            'ses_aws_secret_access_key' => 'secret',
            'ses_aws_default_region' => 'eu-west-1',
        ]);
        $user->projects()->attach($project->id, ['role' => User::ROLE_ADMIN]);

        $response = $this->actingAs($user)
            ->withSession(['_old_input' => [
                'name' => $project->name,
            ]])
            ->get(route('admin.projects.edit', $project));

        $response->assertOk();
        $response->assertSee('value="AKIASHOW123"', false);
        $response->assertSee('value="eu-west-1"', false);
    }
}
