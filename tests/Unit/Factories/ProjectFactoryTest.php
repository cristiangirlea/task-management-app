<?php

namespace Tests\Unit\Requests\Project\Factories;

use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectFactoryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test if the Project factory creates valid data.
     */
    public function test_project_factory_creates_valid_project()
    {
        // Create a project using the factory
        $project = Project::factory()->create();

        // Assert that the project exists in the database
        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'name' => $project->name,
            'description' => $project->description, // Check description field
        ]);

        // Optional: Assert that the project has all expected fields populated
        $this->assertNotNull($project->created_at);
        $this->assertNotNull($project->updated_at);
    }

    /**
     * Test if the Project factory can create multiple projects.
     */
    public function test_project_factory_creates_multiple_projects()
    {
        // Create 10 projects using the factory
        $projects = Project::factory()->count(10)->create();

        // Assert the correct number of projects were created
        $this->assertCount(10, $projects);

        // Assert that all projects exist in the database
        foreach ($projects as $project) {
            $this->assertDatabaseHas('projects', [
                'id' => $project->id,
                'name' => $project->name,
                'description' => $project->description, // Check description field
            ]);
        }
    }

    /**
     * Test that the factory handles the description correctly.
     */
    public function test_project_factory_creates_with_description()
    {
        // Create a project with a specific description
        $description = 'This is a test description for a project.';
        $project = Project::factory()->create(['description' => $description]);

        // Assert the specific description is in the database
        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'description' => $description,
        ]);
    }
}
