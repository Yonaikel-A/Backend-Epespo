<?php

namespace App\Http\Controllers;

use App\Models\Usuario;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;

class UsuarioController extends Controller
{
    public function index()
    {
        $usuarios = Usuario::select('id', 'nombre', 'correo', 'rol', 'activo', 'created_at')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($usuarios);
    }
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:100',
            'correo' => 'required|email|unique:usuarios,correo',
            'contrasena' => 'required|string|min:6',
            'rol' => 'required|in:admin,lector',
        ]);

        $correo = strtolower(trim($validated['correo']));

        $usuario = new Usuario();
        $usuario->nombre = $validated['nombre'];
        $usuario->correo = $correo;
        $usuario->contrasena = $validated['contrasena']; 
        $usuario->rol = $validated['rol'];
        $usuario->activo = true;
        $usuario->save();

        return response()->json([
            'message' => 'Usuario creado correctamente',
            'usuario' => $usuario
        ], 201);
    }
    public function update(Request $request, $id)
    {
        $usuario = Usuario::find($id);

        if (!$usuario) {
            return response()->json(['error' => 'Usuario no encontrado'], 404);
        }

        $validated = $request->validate([
            'nombre' => 'sometimes|string|max:100',
            'correo' => 'sometimes|email|unique:usuarios,correo,' . $usuario->id,
            'contrasena' => 'nullable|string|min:6',
            'rol' => 'sometimes|in:admin,lector',
            'activo' => 'sometimes|boolean',
        ]);
        if (isset($validated['correo'])) {
            $validated['correo'] = strtolower(trim($validated['correo']));
        }
        if (empty($validated['contrasena'])) {
            unset($validated['contrasena']);
        }
        $usuario->update($validated);
        return response()->json([
            'message' => 'Usuario actualizado correctamente',
            'usuario' => $usuario
        ]);
    }
    public function destroy($id)
    {
        $usuario = Usuario::find($id);

        if (!$usuario) {
            return response()->json(['error' => 'Usuario no encontrado'], 404);
        }

        $authUser = auth()->user();
        if (!$authUser) {
            try { $authUser = JWTAuth::parseToken()->authenticate(); } catch (\Exception $e) { $authUser = null; }
        }
        if ($authUser && (int)$authUser->id === (int)$usuario->id) {
            return response()->json(['error' => 'No puedes desactivar tu propio usuario'], 403);
        }

        $usuario->activo = false;
        $usuario->save();

        return response()->json(['message' => 'Usuario desactivado correctamente', 'usuario' => $usuario]);
    }
    public function toggleActivo($id)
    {
        $usuario = Usuario::find($id);

        if (!$usuario) {
            return response()->json(['error' => 'Usuario no encontrado'], 404);
        }

        $authUser = auth()->user();
        if (!$authUser) {
            try { $authUser = JWTAuth::parseToken()->authenticate(); } catch (\Exception $e) { $authUser = null; }
        }

        if ($authUser && (int)$authUser->id === (int)$usuario->id) {
            return response()->json(['error' => 'No puedes cambiar tu propio estado'], 403);
        }

        $usuario->activo = !$usuario->activo;
        $usuario->save();

        return response()->json([
            'message' => $usuario->activo ? 'Usuario activado ' : 'Usuario desactivado ',
            'usuario' => $usuario
        ]);
    }
}
