<?php

namespace Tests\Unit\Requests\Tenant;

use App\Http\Requests\Tenant\UpdateTenantRequest;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class UpdateTenantRequestTest extends TestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    private function request(): UpdateTenantRequest
    {
        $request = new UpdateTenantRequest;
        $request->setUserResolver(fn () => $this->user);

        return $request;
    }

    private function validate(array $data): \Illuminate\Validation\Validator
    {
        $request = $this->request();

        return Validator::make($data, $request->rules(), $request->messages());
    }

    public function test_partial_update_fails_validation(): void
    {
        $validator = $this->validate([
            'name' => str_repeat('a', 256),
            'slug' => str_repeat('a', 256),
            'domain' => str_repeat('a', 256),
            'settings' => 'not-an-object',
        ]);

        $this->assertFalse($validator->passes());
        $this->assertEquals([
            'name' => ['The name field must not be greater than 255 characters.'],
            'slug' => ['The slug must not be greater than 255 characters.'],
            'domain' => ['The domain must not be greater than 255 characters.'],
            'settings' => ['The settings field must be an object.'],
        ], $validator->errors()->toArray());
    }

    public function test_slug_and_domain_are_unique_on_update(): void
    {
        Tenant::factory()->create([
            'slug' => 'existing-slug',
            'domain' => 'existing-domain.com',
        ]);

        $validator = $this->validate([
            'slug' => 'existing-slug',
            'domain' => 'existing-domain.com',
        ]);

        $this->assertFalse($validator->passes());
        $this->assertEquals([
            'slug' => ['The slug has already been taken.'],
            'domain' => ['The domain has already been taken.'],
        ], $validator->errors()->toArray());
    }

    public function test_the_users_own_slug_and_domain_are_ignored(): void
    {
        $this->user->tenant->update(['slug' => 'my-slug', 'domain' => 'my-domain.com']);

        $validator = $this->validate([
            'slug' => 'my-slug',
            'domain' => 'my-domain.com',
        ]);

        $this->assertTrue($validator->passes());
    }

    public function test_valid_partial_update(): void
    {
        $validator = $this->validate([
            'name' => 'Updated Tenant',
            'settings' => ['theme' => 'dark'],
        ]);

        $this->assertTrue($validator->passes());
    }
}
