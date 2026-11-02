<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminProjectUnsignedWebhooksSettingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->withTwoFactorEnrolled()->create();
        $this->project = Project::factory()->create();
        $this->admin->projects()->attach($this->project->id, ['role' => User::ROLE_ADMIN]);
    }

    private function update(string $allowUnsigned): void
    {
        $this->actingAs($this->admin)->put(route('admin.projects.update', $this->project), [
            'name' => $this->project->name,
            'admins' => [(string) $this->admin->id],
            'allow_unsigned_webhooks' => $allowUnsigned,
        ])->assertRedirect(route('admin.projects.index'));
    }

    /** @test */
    public function new_projects_require_signed_webhooks_by_default(): void
    {
        $this->assertFalse($this->project->fresh()->allow_unsigned_webhooks);
    }

    /** @test */
    public function project_admin_can_turn_unsigned_webhooks_on_and_off(): void
    {
        $this->update('1');
        $this->assertTrue($this->project->fresh()->allow_unsigned_webhooks);

        $this->update('0');
        $this->assertFalse($this->project->fresh()->allow_unsigned_webhooks);
    }

    /** @test */
    public function edit_page_shows_the_unsigned_webhooks_setting(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.projects.edit', $this->project))
            ->assertOk()
            ->assertSee('Accept webhook requests that are not signed by Amazon SNS');
    }
}
