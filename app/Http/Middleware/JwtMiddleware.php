<?php

namespace App\Http\Middleware;

use Closure;
use Exception;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Http\Middleware\BaseMiddleware;

class JwtMiddleware extends BaseMiddleware
{
    public function handle($request, Closure $next)
    {
        try {

            $user = JWTAuth::parseToken()->authenticate();
            auth()->setUser($user);

        } catch (Exception $e) {
            return response()->json(['error' => 'Token no válido o expirado'], 401);
        }

        if (!$user || !$user->activo) {
            return response()->json(['error' => 'Usuario inactivo'], 403);
        }

        return $next($request);
    }
}
