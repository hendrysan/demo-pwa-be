<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class JwtMiddleware
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $authHeader = (string) $request->header('Authorization', '');

        if (!str_starts_with($authHeader, 'Bearer ')) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 401);
        }

        $token = trim(substr($authHeader, 7));

        try {
            $payload = JWT::decode($token, new Key($this->jwtSecret(), $this->jwtAlgo()));
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Invalid token',
            ], 401);
        }

        if (!isset($payload->sub)) {
            return response()->json([
                'message' => 'Invalid token payload',
            ], 401);
        }

        $userId = (int) $payload->sub;
        $user = User::query()->find($userId);

        if (!$user) {
            return response()->json([
                'message' => 'User not found',
            ], 401);
        }

        $request->attributes->set('jwt_payload', $payload);
        $request->attributes->set('jwt_user', [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ]);
        $request->attributes->set('jwt_user_model', $user);

        return $next($request);
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
