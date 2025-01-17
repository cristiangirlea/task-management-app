<?php

namespace Tests\Unit\Models;

use App\Models\User;
use App\Models\Task;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that a user has many tasks.
     */
    public function test_user_has_many_tasks()
    {
        $user = User::factory()->create();
        Task::factory()->count(3)->create(['user_id' => $user->id]);

        $this->assertCount(3, $user->tasks);
        $this->assertInstanceOf(Task::class, $user->tasks->first());
    }

    /**
     * Test that a user has many projects.
     */
    public function test_user_has_many_projects()
    {
        $user = User::factory()->create();
        Project::factory()->count(2)->create(['user_id' => $user->id]);

        $this->assertCount(2, $user->projects);
        $this->assertInstanceOf(Project::class, $user->projects->first());
    }

    /**
     * Test fillable attributes for the user.
     */
    public function test_user_fillable_attributes()
    {
        $data = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ];

        $user = User::create($data);

        $this->assertEquals($data['name'], $user->name);
        $this->assertEquals($data['email'], $user->email);
        $this->assertTrue(password_verify('password', $user->password));
    }

    /**
     * Test scopeByEmailDomain.
     */
    public function test_user_scope_by_email_domain()
    {
        User::factory()->create(['email' => 'user1@example.com']);
        User::factory()->create(['email' => 'user2@test.com']);

        $exampleUsers = User::byEmailDomain('example.com')->get();

        $this->assertCount(1, $exampleUsers);
        $this->assertEquals('example.com', substr($exampleUsers->first()->email, strpos($exampleUsers->first()->email, '@') + 1));
    }

    /**
     * Test API token generation for the user.
     */
    public function test_user_generate_api_token()
    {
        $user = User::factory()->create();

        $token = $user->generateApiToken();

        $this->assertNotEmpty($token);
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'tokenable_type' => User::class,
        ]);
    }

    /**
     * Test that the password is hashed.
     */
    public function test_user_password_is_hashed()
    {
        $user = User::factory()->create(['password' => 'plain-text-password']);

        $this->assertNotEquals('plain-text-password', $user->password);
        $this->assertTrue(password_verify('plain-text-password', $user->password));
    }
}
