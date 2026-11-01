<?php

namespace Tests\Feature;

use App\Models\ProjectRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectRequestAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private ProjectRequest $projectRequest;

    protected function setUp(): void
    {
        parent::setUp();

        $requester = User::factory()->create(['super_admin' => false]);
        $this->projectRequest = ProjectRequest::create([
            'requested_by' => $requester->id,
            'name' => 'Requested project',
            'status' => 'pending',
        ]);
    }

    /** @test */
    public function non_super_admin_cannot_view_approve_or_reject_project_requests(): void
    {
        $user = User::factory()->withTwoFactorEnrolled()->create(['super_admin' => false]);

        $this->actingAs($user)->get(route('project-requests.index'))->assertForbidden();
        $this->actingAs($user)->get(route('project-requests.show', $this->projectRequest))->assertForbidden();
        $this->actingAs($user)->post(route('project-requests.approve', $this->projectRequest))->assertForbidden();
        $this->actingAs($user)->post(route('project-requests.reject', $this->projectRequest))->assertForbidden();

        $this->assertSame('pending', $this->projectRequest->fresh()->status);
    }

    /** @test */
    public function super_admin_can_approve_a_project_request(): void
    {
        $superAdmin = User::factory()->withTwoFactorEnrolled()->create(['super_admin' => true]);

        $this->actingAs($superAdmin)
            ->post(route('project-requests.approve', $this->projectRequest))
            ->assertRedirect(route('project-requests.index'));

        $this->projectRequest->refresh();
        $this->assertSame('approved', $this->projectRequest->status);
        $this->assertNotNull($this->projectRequest->project_id);
    }

    /** @test */
    public function super_admin_can_reject_a_project_request(): void
    {
        $superAdmin = User::factory()->withTwoFactorEnrolled()->create(['super_admin' => true]);

        $this->actingAs($superAdmin)
            ->post(route('project-requests.reject', $this->projectRequest), ['rejection_reason' => 'Duplicate'])
            ->assertRedirect(route('project-requests.index'));

        $this->assertSame('rejected', $this->projectRequest->fresh()->status);
    }
}
