<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = $request->user() ?: auth()->user();

        if (!$user || !in_array($user->rol, $roles)) {
            return response()->json(['error' => 'Acceso denegado'], 403);
        }

        return $next($request);
    }
}
