<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Enums\UserRole;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use App\Http\Resources\UserResource;
use App\Http\Resources\WebTourRequestResource;
use App\Models\WebTourRequest;
use Google_Client;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $fields = $request->validate([
            'name' => 'required|string',
            'email' => 'required|string|unique:users,email',
            'password' => 'required|string',
        ]);

        /** @var User $user */
        $user = User::query()->create([
            'name' => $fields['name'],
            'email' => $fields['email'],
            'password' => Hash::make($fields['password']),
            'role' => UserRole::User,
        ]);

        $token = $user->createToken('authToken')->plainTextToken;
        return response()->json(['token' => $token], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->only('email', 'password');
        if (auth()->attempt($credentials)) {
            $token = auth()->user()->createToken('authToken')->plainTextToken;
            return response()->json(['token' => $token]);
        }
        return response()->json(['message' => 'Wrong credentials'], 400);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user('sanctum')->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(new UserResource($request->user('sanctum')));
    }

    public function updateMe(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user('sanctum');

        $validated = $request->validate([
            'name' => 'required|string',
            'email' => 'required|string|email|unique:users,email,' . $user->id,
            'phone' => 'nullable|string',
            'birthday' => 'nullable|date',
            'gender' => 'nullable|in:1,2',
            'current_password' => 'nullable|string',
            'password' => 'nullable|string|confirmed',
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ]);

        if (isset($validated['current_password']) && !Hash::check($validated['current_password'], $user->password)) {
            return response()->json(['message' => 'Current password is incorrect'], 400);
        }

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        if ($request->hasFile('avatar')) {
            $avatarPath = $request->file('avatar')->store('avatars', 'public');
            $validated['avatar'] = $avatarPath;
        }

        $user->update($validated);

        return response()->json([
            'message' => 'Profile updated successfully',
            'data' => new UserResource($user->fresh())
        ]);
    }

    public function googleAuth(Request $request): JsonResponse
    {
        $request->validate([
            'id_token' => 'required|string',
        ]);

        try {
            $client = new Google_Client(['client_id' => config('services.google.client_id')]);
            $payload = $client->verifyIdToken($request->id_token);

            if (!$payload) {
                return response()->json(['message' => 'Invalid Google ID token'], 401);
            }

            $email = $payload['email'];
            $name = $payload['name'];
            $googleId = $payload['sub'];
            $avatar = $payload['picture'] ?? null;

            $user = User::where('email', $email)->first();

            if (!$user) {
                $user = User::create([
                    'name' => $name,
                    'email' => $email,
                    'google_id' => $googleId,
                    'avatar' => $avatar,
                    'role' => UserRole::User,
                ]);
            } else {
                $user->update([
                    'google_id' => $googleId,
                    'avatar' => $avatar
                ]);
            }

            $token = $user->createToken('googleAuthToken')->plainTextToken;

            return response()->json([
                'token' => $token,
                'user' => new UserResource($user)
            ]);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Authentication failed: ' . $e->getMessage()], 401);
        }
    }

    public function getWebTours(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user('sanctum');

        $webTours = WebTourRequest::with(['tour'])
            ->where('user_id', $user->id)
            ->latest()
            ->get();

        return response()->json([
            'data' => WebTourRequestResource::collection($webTours)
        ]);
    }
}
