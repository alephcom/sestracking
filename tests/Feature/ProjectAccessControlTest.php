<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Project;
use App\Models\Email;
use App\Models\EmailRecipient;
use App\Services\ProjectAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectAccessControlTest extends TestCase
{
    use RefreshDatabase;

    protected ProjectAccessService $projectService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->projectService = new ProjectAccessService();
    }

    /** @test */
    public function admin_sees_all_projects_data()
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $project1 = Project::factory()->create(['name' => 'Project 1']);
        $project2 = Project::factory()->create(['name' => 'Project 2']);
        
        // Create emails for both projects
        $email1 = Email::factory()->create(['project_id' => $project1->id]);
        EmailRecipient::factory()->create(['email_id' => $email1->id]);
        
        $email2 = Email::factory()->create(['project_id' => $project2->id]);
        EmailRecipient::factory()->create(['email_id' => $email2->id]);
        
        $this->actingAs($admin);

        // Test activity API includes all projects (skip dashboard API due to SQLite/MySQL compatibility)
        $response = $this->get('/activity/list/api');
        $response->assertOk();
        $data = $response->json();
        $this->assertEquals(2, $data['totalRows']);
    }

    /** @test */
    public function regular_user_sees_only_assigned_projects_data()
    {
        $user = User::factory()->create(['role' => User::ROLE_USER]);
        $project1 = Project::factory()->create(['name' => 'Project 1']);
        $project2 = Project::factory()->create(['name' => 'Project 2']);
        
        // Assign user to only project 1
        $user->projects()->attach($project1->id);
        
        // Create emails for both projects
        $email1 = Email::factory()->create(['project_id' => $project1->id]);
        EmailRecipient::factory()->create(['email_id' => $email1->id]);
        
        $email2 = Email::factory()->create(['project_id' => $project2->id]);
        EmailRecipient::factory()->create(['email_id' => $email2->id]);
        
        $this->actingAs($user);

        // Test activity API shows only assigned project emails
        $response = $this->get('/activity/list/api');
        $response->assertOk();
        $data = $response->json();
        $this->assertEquals(1, $data['totalRows']);
        
        // Verify it's the correct project
        $emailRow = $data['rows'][0];
        $this->assertEquals($project1->id, $emailRow['project_id']);
    }

    /** @test */
    public function user_with_no_projects_sees_appropriate_message()
    {
        $user = User::factory()->create(['role' => User::ROLE_USER]);
        // Don't assign any projects
        
        $this->actingAs($user);

        // Test activity API returns empty result
        $response = $this->get('/activity/list/api');
        $response->assertOk();
        $data = $response->json();
        $this->assertEquals(0, $data['totalRows']);
        $this->assertEmpty($data['rows']);
    }

    /** @test */
    public function project_dropdown_shows_only_accessible_projects()
    {
        $user = User::factory()->create(['role' => User::ROLE_USER]);
        $project1 = Project::factory()->create(['name' => 'Accessible Project']);
        $project2 = Project::factory()->create(['name' => 'Inaccessible Project']);
        
        // Assign user to only project 1
        $user->projects()->attach($project1->id);
        
        $this->actingAs($user);

        // Test activity page shows only accessible projects
        $response = $this->get('/activity');
        $response->assertOk();
        $response->assertViewHas('accessibleProjects');
        
        $accessibleProjects = $response->viewData('accessibleProjects');
        $this->assertCount(1, $accessibleProjects);
        $this->assertEquals('Accessible Project', $accessibleProjects->first()->name);
    }

    /** @test */
    public function api_requests_validate_project_access()
    {
        $user = User::factory()->create(['role' => User::ROLE_USER]);
        $project1 = Project::factory()->create(['name' => 'Accessible Project']);
        $project2 = Project::factory()->create(['name' => 'Inaccessible Project']);
        
        // Assign user to only project 1
        $user->projects()->attach($project1->id);
        
        $this->actingAs($user);

        // Test activity API with accessible project
        $response = $this->get('/activity/list/api?project_id=' . $project1->id);
        $response->assertOk();

        // Test activity API with inaccessible project
        $response = $this->get('/activity/list/api?project_id=' . $project2->id);
        $response->assertStatus(403);
        $response->assertJson(['error' => 'Unauthorized access to project']);
    }

    /** @test */
    public function admin_can_view_emails_from_all_projects()
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $project1 = Project::factory()->create();
        $project2 = Project::factory()->create();
        
        $email1 = Email::factory()->create(['project_id' => $project1->id]);
        EmailRecipient::factory()->create(['email_id' => $email1->id]);
        
        $email2 = Email::factory()->create(['project_id' => $project2->id]);
        EmailRecipient::factory()->create(['email_id' => $email2->id]);
        
        $this->actingAs($admin);

        // Test specific project access
        $response = $this->get('/activity/list/api?project_id=' . $project1->id);
        $response->assertOk();
        
        $response = $this->get('/activity/list/api?project_id=' . $project2->id);
        $response->assertOk();
    }

    /** @test */
    public function regular_user_can_view_only_assigned_project_emails()
    {
        $user = User::factory()->create(['role' => User::ROLE_USER]);
        $project1 = Project::factory()->create();
        $project2 = Project::factory()->create();
        
        // Assign user to only project 1
        $user->projects()->attach($project1->id);
        
        $email1 = Email::factory()->create(['project_id' => $project1->id]);
        EmailRecipient::factory()->create(['email_id' => $email1->id]);
        
        $email2 = Email::factory()->create(['project_id' => $project2->id]);
        EmailRecipient::factory()->create(['email_id' => $email2->id]);
        
        $this->actingAs($user);

        // Can access assigned project
        $response = $this->get('/activity/list/api?project_id=' . $project1->id);
        $response->assertOk();
        
        // Cannot access unassigned project
        $response = $this->get('/activity/list/api?project_id=' . $project2->id);
        $response->assertStatus(403);
    }

    /** @test */
    public function project_filtering_respects_user_permissions()
    {
        $user = User::factory()->create(['role' => User::ROLE_USER]);
        $project1 = Project::factory()->create();
        $project2 = Project::factory()->create();
        
        // Assign user to only project 1
        $user->projects()->attach($project1->id);
        
        $email1 = Email::factory()->create(['project_id' => $project1->id]);
        EmailRecipient::factory()->create(['email_id' => $email1->id]);
        
        $email2 = Email::factory()->create(['project_id' => $project2->id]);
        EmailRecipient::factory()->create(['email_id' => $email2->id]);
        
        $this->actingAs($user);

        // Test "all" shows only accessible projects
        $response = $this->get('/activity/list/api?project_id=all');
        $response->assertOk();
        $data = $response->json();
        $this->assertEquals(1, $data['totalRows']);
        $this->assertEquals($project1->id, $data['rows'][0]['project_id']);
    }

    /** @test */
    public function email_details_api_validates_project_access()
    {
        $user = User::factory()->create(['role' => User::ROLE_USER]);
        $project1 = Project::factory()->create();
        $project2 = Project::factory()->create();
        
        // Assign user to only project 1
        $user->projects()->attach($project1->id);
        
        $email1 = Email::factory()->create(['project_id' => $project1->id]);
        EmailRecipient::factory()->create(['email_id' => $email1->id]);
        
        $email2 = Email::factory()->create(['project_id' => $project2->id]);
        EmailRecipient::factory()->create(['email_id' => $email2->id]);
        
        $this->actingAs($user);

        // Can access email from assigned project
        $response = $this->get('/activity/details/api?id=' . $email1->id);
        $response->assertOk();
        
        // Cannot access email from unassigned project
        $response = $this->get('/activity/details/api?id=' . $email2->id);
        $response->assertStatus(404);
        $response->assertJson(['error' => 'Email not found or access denied']);
    }

    /** @test */
    public function admin_can_export_from_all_projects()
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $project1 = Project::factory()->create();
        $project2 = Project::factory()->create();
        
        $this->actingAs($admin);

        // Test export with specific project
        $response = $this->get('/activity/export?format=csv&project_id=' . $project1->id);
        $response->assertOk();
        
        $response = $this->get('/activity/export?format=csv&project_id=' . $project2->id);
        $response->assertOk();
        
        // Test export with all projects
        $response = $this->get('/activity/export?format=csv&project_id=all');
        $response->assertOk();
    }

    /** @test */
    public function regular_user_can_export_only_assigned_projects()
    {
        $user = User::factory()->create(['role' => User::ROLE_USER]);
        $project1 = Project::factory()->create();
        $project2 = Project::factory()->create();
        
        // Assign user to only project 1
        $user->projects()->attach($project1->id);
        
        $this->actingAs($user);

        // Can export from assigned project
        $response = $this->get('/activity/export?format=csv&project_id=' . $project1->id);
        $response->assertOk();
        
        // Cannot export from unassigned project
        $response = $this->get('/activity/export?format=csv&project_id=' . $project2->id);
        $response->assertStatus(403);
    }

    /** @test */
    public function export_with_invalid_project_id_returns_403()
    {
        $user = User::factory()->create(['role' => User::ROLE_USER]);
        $project1 = Project::factory()->create();
        
        // Assign user to project 1
        $user->projects()->attach($project1->id);
        
        $this->actingAs($user);

        // Try to export with non-existent project ID
        $response = $this->get('/activity/export?format=csv&project_id=999999');
        $response->assertStatus(403);
    }

    /** @test */
    public function export_respects_project_filtering()
    {
        $user = User::factory()->create(['role' => User::ROLE_USER]);
        $project1 = Project::factory()->create();
        $project2 = Project::factory()->create();
        
        // Assign user to both projects
        $user->projects()->attach([$project1->id, $project2->id]);
        
        $this->actingAs($user);

        // Test export with "all" shows both assigned projects
        $response = $this->get('/activity/export?format=csv&project_id=all');
        $response->assertOk();
        
        // Test export with specific project
        $response = $this->get('/activity/export?format=csv&project_id=' . $project1->id);
        $response->assertOk();
    }
}