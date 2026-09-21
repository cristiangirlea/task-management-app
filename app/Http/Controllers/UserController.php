<?php

namespace App\Http\Controllers;

use App\Http\Requests\User\LoginUserRequest;
use App\Http\Requests\User\RegisterUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

class UserController extends ApiBaseController
{
    public function __construct(protected TenantService $tenantService) {}

    /**
     * Register a user together with a fresh workspace (tenant).
     */
    public function register(RegisterUserRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = DB::transaction(function () use ($data): User {
            $tenant = $this->tenantService->createTenant([
                'name' => $data['workspace_name'] ?? "{$data['name']}'s Workspace",
            ]);

            return $tenant->users()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'role' => User::ROLE_OWNER,
            ]);
        });

        event(new Registered($user));

        return $this->respondApiSuccess(null, $this->authPayload($user), __('auth.register.success'), 201);
    }

    public function login(LoginUserRequest $request): JsonResponse
    {
        $user = User::where('email', $request->input('email'))->first();

        if (! $user || ! Hash::check($request->input('password'), $user->password)) {
            return $this->respondApiError(__('auth.login.error'), 401);
        }

        return $this->respondApiSuccess(null, $this->authPayload($user), __('auth.login.success'));
    }

    public function getUser(Request $request): JsonResponse
    {
        return $this->respondApiSuccess(UserResource::class, $request->user()->load('tenant'), 'User data retrieved successfully');
    }

    public function updateUser(UpdateUserRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->fill($request->validated())->save();

        return $this->respondApiSuccess(UserResource::class, $user->load('tenant'), 'User updated successfully');
    }

    public function deleteUser(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->tokens()->delete();
        $user->delete();

        return $this->respondApiSuccess(null, null, 'User deleted successfully', 204);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        return $this->respondApiSuccess(null, null, __('auth.logout.success'));
    }

    /**
     * @return array{user: UserResource, token: string}
     */
    private function authPayload(User $user): array
    {
        return [
            'user' => new UserResource($user->load('tenant')),
            'token' => $user->createToken('auth_token')->plainTextToken,
        ];
    }
}
