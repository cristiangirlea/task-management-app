<?php

namespace Tests\Unit\Requests\Tenant;

use App\Http\Requests\Tenant\StoreTenantRequest;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class StoreTenantRequestTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that the validation rules are enforced for valid input.
     */
    public function testValidDataPassesValidation(): void
    {
        $data = [
            'name' => 'Valid Tenant',
            'slug' => 'valid-tenant',
            'domain' => 'example.com',
            'settings' => json_encode(['key' => 'value']),
        ];

        $request = new StoreTenantRequest();
        $validator = Validator::make($data, $request->rules(), $request->messages());

        $this->assertTrue($validator->passes());
    }

    /**
     * Test that invalid data fails validation with custom error messages.
     */
    public function testInvalidDataFailsValidationWithCustomMessages(): void
    {
        $data = [
            'name' => '',
            'slug' => null,
            'domain' => str_repeat('a', 300), // Domain exceeds max length
            'settings' => 'invalid-json', // Invalid JSON
        ];

        $request = new StoreTenantRequest();
        $validator = Validator::make($data, $request->rules(), $request->messages());

        $this->assertFalse($validator->passes());

        // Assert that validation error messages are correctly returned
        $this->assertEquals([
            'name' => ['The name field is required.'],
            'slug' => ['The slug field is required.'],
            'domain' => ['The domain must not be greater than 255 characters.'],
            'settings' => ['The settings field must be a valid JSON string.'],
        ], $validator->errors()->toArray());
    }

    /**
     * Test that uniqueness rules for the slug and domain are applied correctly.
     */
    public function testSlugAndDomainAreUnique(): void
    {
        // Simulate existing tenant entries
        Tenant::factory()->create([
            'slug' => 'existing-tenant',
            'domain' => 'existing.com',
        ]);

        $data = [
            'name' => 'Duplicate Tenant',
            'slug' => 'existing-tenant', // Duplicate slug
            'domain' => 'existing.com', // Duplicate domain
        ];

        $request = new StoreTenantRequest();
        $validator = Validator::make($data, $request->rules(), $request->messages());

        $this->assertFalse($validator->passes());

        // Assert that validation fails for both duplicate slug and domain
        $this->assertTrue($validator->errors()->has('slug'));
        $this->assertTrue($validator->errors()->has('domain'));

        // Assert the correct custom messages
        $this->assertEquals(
            'The slug has already been taken.',
            $validator->errors()->first('slug')
        );
        $this->assertEquals(
            'The domain has already been taken.',
            $validator->errors()->first('domain')
        );
    }
}
