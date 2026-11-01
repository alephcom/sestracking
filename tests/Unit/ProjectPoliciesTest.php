<?php

namespace Tests\Unit;

use App\Models\Project;
use App\Models\ProjectRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class ProjectPoliciesTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;
    private User $superAdmin;
    private User $projectAdmin;
    private User $projectUser;
    private User $outsider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = Project::factory()->create();
        $this->superAdmin = User::factory()->create(['super_admin' => true]);
        $this->projectAdmin = User::factory()->create(['super_admin' => false]);
        $this->projectUser = User::factory()->create(['super_admin' => false]);
        $this->outsider = User::factory()->create(['super_admin' => false]);

        $this->projectAdmin->projects()->attach($this->project->id, ['role' => User::ROLE_ADMIN]);
        $this->projectUser->projects()->attach($this->project->id, ['role' => User::ROLE_USER]);
    }

    /** @test */
    public function super_admin_passes_all_project_policy_checks(): void
    {
        $gate = Gate::forUser($this->superAdmin);

        $this->assertTrue($gate->allows('viewAny', Project::class));
        $this->assertTrue($gate->allows('create', Project::class));
        $this->assertTrue($gate->allows('view', $this->project));
        $this->assertTrue($gate->allows('update', $this->project));
        $this->assertTrue($gate->allows('delete', $this->project));
        $this->assertTrue($gate->allows('restore', $this->project));
        $this->assertTrue($gate->allows('forceDelete', $this->project));
    }

    /** @test */
    public function project_admin_can_manage_their_project_but_not_create_projects(): void
    {
        $gate = Gate::forUser($this->projectAdmin);

        $this->assertTrue($gate->allows('viewAny', Project::class));
        $this->assertTrue($gate->allows('view', $this->project));
        $this->assertTrue($gate->allows('update', $this->project));
        $this->assertTrue($gate->allows('delete', $this->project));
        $this->assertFalse($gate->allows('create', Project::class));
        $this->assertFalse($gate->allows('restore', $this->project));
        $this->assertFalse($gate->allows('forceDelete', $this->project));
    }

    /** @test */
    public function project_user_can_view_but_not_manage_their_project(): void
    {
        $gate = Gate::forUser($this->projectUser);

        $this->assertTrue($gate->allows('view', $this->project));
        $this->assertFalse($gate->allows('viewAny', Project::class));
        $this->assertFalse($gate->allows('update', $this->project));
        $this->assertFalse($gate->allows('delete', $this->project));
        $this->assertFalse($gate->allows('create', Project::class));
    }

    /** @test */
    public function unassigned_user_cannot_view_or_manage_a_project(): void
    {
        $gate = Gate::forUser($this->outsider);

        $this->assertFalse($gate->allows('view', $this->project));
        $this->assertFalse($gate->allows('update', $this->project));
    }

    /** @test */
    public function only_super_admins_pass_project_request_policy_checks(): void
    {
        $projectRequest = ProjectRequest::create([
            'requested_by' => $this->projectUser->id,
            'name' => 'New project',
            'status' => 'pending',
        ]);

        foreach (['view', 'approve', 'reject'] as $ability) {
            $this->assertTrue(Gate::forUser($this->superAdmin)->allows($ability, $projectRequest), $ability);
            $this->assertFalse(Gate::forUser($this->projectAdmin)->allows($ability, $projectRequest), $ability);
            $this->assertFalse(Gate::forUser($this->projectUser)->allows($ability, $projectRequest), $ability);
        }

        $this->assertTrue(Gate::forUser($this->superAdmin)->allows('viewAny', ProjectRequest::class));
        $this->assertFalse(Gate::forUser($this->projectAdmin)->allows('viewAny', ProjectRequest::class));
    }
}
