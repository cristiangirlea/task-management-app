<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginUserRequest;
use App\Http\Requests\RegisterUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class UserController extends ApiBaseController
{
    // Register a new user
    public function register(RegisterUserRequest $request)
    {
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        return $this->respondApiSuccess(UserResource::class, $user, 'User registered successfully', 201);
    }

    // Login a user and generate a token
    public function login(LoginUserRequest $request)
    {
        if (!Auth::attempt($request->only('email', 'password'))) {
            return $this->respondApiError('Invalid credentials', 401);
        }

        $user = $request->user();
        $token = $user->createToken('auth_token')->plainTextToken;

        return $this->respondApiSuccess(
            UserResource::class,
            [
                'user' => $user,
                'token' => $token,
            ],
            'Login successful'
        );
    }

    // Get authenticated user data
    public function getUser(Request $request)
    {
        return $this->respondApiSuccess(UserResource::class, $request->user(), 'User data retrieved successfully');
    }

    // Update authenticated user data
    public function updateUser(UpdateUserRequest $request)
    {
        $user = $request->user();

        if ($request->name) $user->name = $request->name;
        if ($request->email) $user->email = $request->email;
        if ($request->password) $user->password = Hash::make($request->password);

        $user->save();

        return $this->respondApiSuccess(UserResource::class, $user, 'User updated successfully');
    }

    // Delete authenticated user
    public function deleteUser(Request $request)
    {
        $user = $request->user();
        $user->tokens()->delete(); // Revoke all tokens
        $user->delete();

        return $this->respondApiSuccess(null, null, 'User deleted successfully', 204);
    }

    // Logout the authenticated user
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return $this->respondApiSuccess(null, null, 'Logged out successfully');
    }
}
