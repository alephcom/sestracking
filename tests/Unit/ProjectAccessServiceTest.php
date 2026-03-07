<?php

namespace Tests\Unit;

use App\Models\Project;
use App\Models\User;
use App\Services\ProjectAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectAccessServiceTest extends TestCase
{
    use RefreshDatabase;

    private ProjectAccessService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ProjectAccessService();
    }

    /** @test */
    public function get_accessible_projects_returns_all_projects_for_super_admin(): void
    {
        $superAdmin = User::factory()->create(['super_admin' => true]);
        Project::factory()->count(3)->create();

        $result = $this->service->getAccessibleProjects($superAdmin);

        $this->assertCount(3, $result);
        $this->assertEqualsCanonicalizing(Project::pluck('id')->toArray(), $result->pluck('id')->toArray());
    }

    /** @test */
    public function get_accessible_projects_returns_only_assigned_projects_for_regular_user(): void
    {
        $user = User::factory()->create(['super_admin' => false]);
        $project1 = Project::factory()->create();
        $project2 = Project::factory()->create();
        $project3 = Project::factory()->create();

        $user->projects()->attach([$project1->id, $project3->id], ['role' => 'user']);

        $result = $this->service->getAccessibleProjects($user);

        $this->assertCount(2, $result);
        $this->assertTrue($result->pluck('id')->contains($project1->id));
        $this->assertTrue($result->pluck('id')->contains($project3->id));
        $this->assertFalse($result->pluck('id')->contains($project2->id));
    }

    /** @test */
    public function get_admin_projects_returns_only_projects_where_user_is_admin(): void
    {
        $user = User::factory()->create(['super_admin' => false]);
        $project1 = Project::factory()->create();
        $project2 = Project::factory()->create();
        $project3 = Project::factory()->create();

        $user->projects()->attach($project1->id, ['role' => User::ROLE_ADMIN]);
        $user->projects()->attach($project2->id, ['role' => User::ROLE_USER]);
        $user->projects()->attach($project3->id, ['role' => User::ROLE_ADMIN]);

        $result = $this->service->getAdminProjects($user);

        $this->assertCount(2, $result);
        $this->assertTrue($result->pluck('id')->contains($project1->id));
        $this->assertTrue($result->pluck('id')->contains($project3->id));
        $this->assertFalse($result->pluck('id')->contains($project2->id));
    }

    /** @test */
    public function get_admin_projects_returns_empty_for_super_admin_with_no_project_pivot(): void
    {
        $superAdmin = User::factory()->create(['super_admin' => true]);
        Project::factory()->count(2)->create();

        $result = $this->service->getAdminProjects($superAdmin);

        $this->assertCount(0, $result);
    }

    /** @test */
    public function get_accessible_project_ids_returns_correct_id_array_for_super_admin(): void
    {
        $superAdmin = User::factory()->create(['super_admin' => true]);
        $projects = Project::factory()->count(2)->create();

        $ids = $this->service->getAccessibleProjectIds($superAdmin);

        $this->assertEqualsCanonicalizing($projects->pluck('id')->toArray(), $ids);
    }

    /** @test */
    public function get_accessible_project_ids_returns_only_assigned_ids_for_regular_user(): void
    {
        $user = User::factory()->create(['super_admin' => false]);
        $project1 = Project::factory()->create();
        $project2 = Project::factory()->create();

        $user->projects()->attach($project1->id, ['role' => 'user']);

        $ids = $this->service->getAccessibleProjectIds($user);

        $this->assertEquals([$project1->id], $ids);
        $this->assertNotContains($project2->id, $ids);
    }

    /** @test */
    public function has_access_to_project_id_returns_true_when_user_has_access(): void
    {
        $user = User::factory()->create(['super_admin' => false]);
        $project = Project::factory()->create();
        $user->projects()->attach($project->id, ['role' => 'user']);

        $this->assertTrue($this->service->hasAccessToProjectId($user, $project->id));
    }

    /** @test */
    public function has_access_to_project_id_returns_true_for_super_admin(): void
    {
        $superAdmin = User::factory()->create(['super_admin' => true]);
        $project = Project::factory()->create();

        $this->assertTrue($this->service->hasAccessToProjectId($superAdmin, $project->id));
    }

    /** @test */
    public function has_access_to_project_id_returns_false_when_user_lacks_access(): void
    {
        $user = User::factory()->create(['super_admin' => false]);
        $project1 = Project::factory()->create();
        $project2 = Project::factory()->create();
        $user->projects()->attach($project1->id, ['role' => 'user']);

        $this->assertFalse($this->service->hasAccessToProjectId($user, $project2->id));
    }
}
