<?php

namespace Tests\Unit\Requests\Tenant;

use App\Http\Requests\Tenant\UpdateTenantRequest;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Illuminate\Routing\Route as RoutingRoute;
use Tests\TestCase;

class UpdateTenantRequestTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that partial update fails validation with proper error messages.
     */
    public function testPartialUpdateFailsValidation(): void
    {
        $data = [
            'name' => str_repeat('a', 256), // Exceeds 255 characters
            'slug' => str_repeat('a', 256), // Exceeds 255 characters
            'domain' => str_repeat('a', 256), // Exceeds 255 characters
            'settings' => 'invalid-json', // Invalid JSON format
        ];

        $request = new UpdateTenantRequest();
        $validator = Validator::make($data, $request->rules(), $request->messages());

        $this->assertFalse($validator->passes());

        $this->assertEquals([
            'name' => ['The name field must not be greater than 255 characters.'],
            'slug' => ['The slug must not be greater than 255 characters.'],
            'domain' => ['The domain must not be greater than 255 characters.'],
            'settings' => ['The settings field must be a valid JSON string.'],
        ], $validator->errors()->toArray());
    }

    /**
     * Test that uniqueness rules for slug and domain are correctly enforced during update.
     */
    public function testSlugAndDomainAreUniqueOnUpdate(): void
    {
        // Create an existing tenant to simulate duplicates
        $existingTenant = \App\Models\Tenant::factory()->create([
            'slug' => 'existing-slug',
            'domain' => 'existing-domain.com',
        ]);

        // Create another tenant to simulate the one being updated
        $updatingTenant = \App\Models\Tenant::factory()->create([
            'slug' => 'updating-slug',
            'domain' => 'updating-domain.com',
        ]);

        // Data being submitted in the request
        $data = [
            'slug' => 'existing-slug', // Attempting to use a duplicate slug
            'domain' => 'existing-domain.com', // Attempting to use a duplicate domain
        ];

        // Mock the route to resolve the 'tenant' parameter to $updatingTenant
        $request = new UpdateTenantRequest();
        $request->setRouteResolver(function () use ($updatingTenant) {
            return new class($updatingTenant) {
                private $tenant;

                public function __construct($tenant)
                {
                    $this->tenant = $tenant;
                }

                public function parameter($key)
                {
                    if ($key === 'tenant') {
                        return $this->tenant;
                    }
                    return null;
                }
            };
        });

        // Perform validation
        $validator = Validator::make($data, $request->rules(), $request->messages());

        // Assert that validation fails due to duplicate slug and domain
        $this->assertFalse($validator->passes());
        $this->assertEquals([
            'slug' => ['The slug has already been taken.'],
            'domain' => ['The domain has already been taken.'],
        ], $validator->errors()->toArray());
    }
    /**
     * Test that valid partial update data passes validation.
     */
    public function testValidPartialUpdate(): void
    {
        $data = [
            'name' => 'Updated Tenant Name',
            'slug' => 'updated-slug',
            'domain' => 'new-example.com',
            'settings' => json_encode(['key' => 'value']), // Valid JSON
        ];

        $request = new UpdateTenantRequest();
        $validator = Validator::make($data, $request->rules(), $request->messages());

        $this->assertTrue($validator->passes());
    }
}
