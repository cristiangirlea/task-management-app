<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ProjectControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function getValidHeaders(): array
    {
        $timestamp = time();
        $nonce = uniqid();
        $apiKey = config('app.api_secret_key', 'test-secret');
        $signature = hash_hmac('sha256', $timestamp . $nonce, $apiKey);

        return [
            'X-TIMESTAMP' => $timestamp,
            'X-SIGNATURE' => $signature,
            'X-NONCE' => $nonce,
        ];
    }

    protected function getInvalidHeaders(): array
    {
        return [
            'X-TIMESTAMP' => time(),
            'X-SIGNATURE' => 'invalid-signature',
            'X-NONCE' => uniqid(),
        ];
    }

    /**
     * Test middleware with valid headers.
     */
    public function test_middleware_allows_request_with_valid_headers()
    {
        $response = $this->getJson('/api/projects', $this->getValidHeaders());

        $response->assertStatus(200); // Assuming no projects exist, should still return 200
    }

    /**
     * Test middleware blocks request with invalid headers.
     */
    public function test_middleware_blocks_request_with_invalid_headers()
    {
        $response = $this->getJson('/api/projects', $this->getInvalidHeaders());

        $response->assertStatus(401); // Unauthorized
    }

    /**
     * Test listing all projects.
     */
    public function test_can_list_projects()
    {
        // Arrange: Create projects
        Project::factory()->count(3)->create();

        // Act: Send GET request to /api/projects with valid headers
        $response = $this->getJson('/api/projects', $this->getValidHeaders());

        // Assert: Verify response contains the projects
        $response->assertStatus(200)
            ->assertJsonStructure([
                '*' => ['id', 'name', 'description', 'created_at', 'updated_at'],
            ])
            ->assertJsonCount(3); // Ensure 3 projects are returned
    }

    /**
     * Test creating a new project.
     */
    public function test_can_create_project()
    {
        // Arrange: Create request payload
        $data = [
            'name' => 'New Project',
            'description' => 'This is a test project description.',
        ];

        // Act: Send POST request to /api/projects with valid headers
        $response = $this->postJson('/api/projects', $data, $this->getValidHeaders());

        // Assert: Verify project is created successfully
        $response->assertStatus(201)
            ->assertJsonFragment($data);

        $this->assertDatabaseHas('projects', $data); // Verify in database
    }

    /**
     * Test validation error when creating a project.
     */
    public function test_validation_error_on_create_project()
    {
        // Act: Send POST request with invalid data and valid headers
        $response = $this->postJson('/api/projects', ['description' => 'No name provided.'], $this->getValidHeaders());

        // Assert: Verify validation error for 'name'
        $response->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }

    /**
     * Test updating a project.
     */
    public function test_can_update_project()
    {
        // Arrange: Create a project
        $project = Project::factory()->create();

        // Prepare update payload
        $data = [
            'name' => 'Updated Project Name',
            'description' => 'Updated description.',
        ];

        // Act: Send PUT request to /api/projects/{id} with valid headers
        $response = $this->putJson("/api/projects/{$project->id}", $data, $this->getValidHeaders());

        // Assert: Verify project is updated successfully
        $response->assertStatus(200)
            ->assertJsonFragment($data);

        $this->assertDatabaseHas('projects', $data); // Verify in database
    }

    /**
     * Test validation error when updating a project.
     */
    public function test_validation_error_on_update_project()
    {
        // Arrange: Create a project
        $project = Project::factory()->create();

        // Act: Send PUT request with invalid data and valid headers
        $response = $this->putJson("/api/projects/{$project->id}", ['description' => 'Invalid update.'], $this->getValidHeaders());

        // Assert: Verify validation error for 'name'
        $response->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }

    /**
     * Test deleting a project.
     */
    public function test_can_delete_project()
    {
        // Arrange: Create a project
        $project = Project::factory()->create();

        // Act: Send DELETE request to /api/projects/{id} with valid headers
        $response = $this->deleteJson("/api/projects/{$project->id}", [], $this->getValidHeaders());

        // Assert: Verify project is deleted
        $response->assertStatus(204);

        $this->assertDatabaseMissing('projects', ['id' => $project->id]); // Verify in database
    }
}
