<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Usuario;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
class AuthController extends Controller
{
public function register(Request $request)
{
    $request->validate([
        'nombre' => 'required|string|max:255',
        'correo' => 'required|string|email|unique:usuarios,correo',
        'contrasena' => 'required|string|min:6',
        'rol' => 'required|in:admin,lector'
    ]);

  
    $usuario = Usuario::create([
        'nombre' => $request->nombre,
        'correo' => $request->correo,
        'contrasena' => $request->contrasena, 
        'rol' => $request->rol,
    ]);

    $token = JWTAuth::fromUser($usuario);

    return response()->json([
        'access_token' => $token,
        'user' => $usuario,
        'rol' => $usuario->rol,
        'token_type' => 'bearer'
    ], 201);
}

public function login(Request $request)
{
    $validated = $request->validate([
        'correo' => 'required|email',
        'contrasena' => 'required|string',
    ]);

    $correo = strtolower(trim($validated['correo']));
    $usuario = Usuario::where('correo', $correo)->first();
    if (!$usuario || !Hash::check($validated['contrasena'], $usuario->contrasena)) {
        return response()->json(['message' => 'Credenciales inválidas'], 401);
    }
    if (!$usuario->activo) {
        return response()->json(['message' => 'Usuario inactivo'], 403);
    }

    try {
        $token = JWTAuth::fromUser($usuario);
    } catch (\Exception $e) {
        return response()->json(['message' => 'Error generando token'], 500);
    }

    return response()->json([
        'access_token' => $token,
        'token_type' => 'bearer',
        'rol' => $usuario->rol,
        'user' => $usuario
    ]);
}
 public function logout(Request $request)
    {
        try {
            $token = JWTAuth::getToken();

            if (!$token) {
                return response()->json([
                    'message' => 'Token no proporcionado'
                ], 400);
            }

            JWTAuth::invalidate($token);

            return response()->json([
                'message' => 'Sesión cerrada correctamente'
            ], 200);

        } catch (JWTException $e) {
            return response()->json([
                'message' => 'Token inválido o expirado',
                'error'   => $e->getMessage(),
            ], 400);
        }
    }
    public function me()
    {
        return response()->json(auth()->user());
    }
}