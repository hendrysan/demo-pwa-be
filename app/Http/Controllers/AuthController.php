<?php

namespace App\Http\Controllers;

use App\Models\User;
use Firebase\JWT\JWT;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = User::query()->create([
            'name' => (string) $request->input('name'),
            'email' => (string) $request->input('email'),
            'password' => Hash::make((string) $request->input('password')),
        ]);

        $accessToken = $this->issueAccessToken($user);
        $refreshToken = $this->issueRefreshToken($user);

        return response()->json([
            'user' => $this->userPayload($user),
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'token_type' => 'Bearer',
            'expires_in' => (int) env('JWT_TTL', 3600),
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = User::query()->where('email', (string) $request->input('email'))->first();

        if (!$user || !Hash::check((string) $request->input('password'), $user->password)) {
            return response()->json([
                'message' => 'Invalid credentials',
            ], 401);
        }

        $accessToken = $this->issueAccessToken($user);
        $refreshToken = $this->issueRefreshToken($user);

        return response()->json([
            'user' => $this->userPayload($user),
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'token_type' => 'Bearer',
            'expires_in' => (int) env('JWT_TTL', 3600),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->attributes->get('jwt_user');

        return response()->json([
            'user' => $user,
        ]);
    }

    public function refresh(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'refresh_token' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $payload = JWT::decode((string) $request->input('refresh_token'), new \Firebase\JWT\Key($this->jwtSecret(), $this->jwtAlgo()));
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Invalid refresh token',
            ], 401);
        }

        if (!isset($payload->sub) || !isset($payload->typ) || $payload->typ !== 'refresh') {
            return response()->json([
                'message' => 'Invalid refresh token payload',
            ], 401);
        }

        $user = User::query()->find((int) $payload->sub);

        if (!$user) {
            return response()->json([
                'message' => 'User not found',
            ], 401);
        }

        $accessToken = $this->issueAccessToken($user);

        return response()->json([
            'access_token' => $accessToken,
            'token_type' => 'Bearer',
            'expires_in' => (int) env('JWT_TTL', 3600),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        return response()->json([
            'message' => 'Logged out',
        ]);
    }

    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ];
    }

    private function issueAccessToken(User $user): string
    {
        $now = time();
        $ttl = (int) env('JWT_TTL', 3600);

        $payload = [
            'iss' => env('APP_URL', 'http://localhost'),
            'sub' => (string) $user->id,
            'iat' => $now,
            'exp' => $now + $ttl,
            'typ' => 'access',
        ];

        return JWT::encode($payload, $this->jwtSecret(), $this->jwtAlgo());
    }

    private function issueRefreshToken(User $user): string
    {
        $now = time();
        $ttl = (int) env('JWT_REFRESH_TTL', 1209600);

        $payload = [
            'iss' => env('APP_URL', 'http://localhost'),
            'sub' => (string) $user->id,
            'iat' => $now,
            'exp' => $now + $ttl,
            'typ' => 'refresh',
        ];

        return JWT::encode($payload, $this->jwtSecret(), $this->jwtAlgo());
    }

    private function jwtSecret(): string
    {
        return (string) env('JWT_SECRET', env('APP_KEY'));
    }

    private function jwtAlgo(): string
    {
        return (string) env('JWT_ALGO', 'HS256');
    }
}
