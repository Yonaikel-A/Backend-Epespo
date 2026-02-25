<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Usuario;
use App\Notifications\ResetPasswordCodeNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class PasswordResetController extends Controller
{
    public function forgot(Request $request)
    {
        $validated = $request->validate([
            'correo' => ['required', 'email', 'exists:usuarios,correo'],
        ], [
            'correo.exists' => 'Ese correo no está registrado.',
        ]);

        $correo = $validated['correo'];

        $user = Usuario::where('correo', $correo)->first(); // ya existe

        $code = (string) random_int(100000, 999999);

        DB::table('password_reset_codes')->updateOrInsert(
            ['correo' => $correo],
            [
                'code_hash'  => Hash::make($code),
                'expires_at' => now()->addMinutes(10),
                'attempts'   => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $user->notify(new ResetPasswordCodeNotification($code));

        return response()->json([
            'message' => 'Código enviado al correo.',
            'expires_in_minutes' => 10,
        ]);
    }

    public function reset(Request $request)
    {
        $validated = $request->validate([
            'correo' => ['required', 'email', 'exists:usuarios,correo'],
            'code'   => ['required', 'digits:6'],
            'contrasena' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'correo.exists' => 'Ese correo no está registrado.',
            'contrasena.confirmed' => 'La confirmación de contraseña no coincide.',
        ]);

        $correo = $validated['correo'];
        $code = $validated['code'];

        $row = DB::table('password_reset_codes')->where('correo', $correo)->first();

        if (!$row) {
            return response()->json(['message' => 'No hay solicitud de recuperación para este correo.'], 400);
        }

        if (now()->gt($row->expires_at)) {
            DB::table('password_reset_codes')->where('correo', $correo)->delete();
            return response()->json(['message' => 'El código expiró. Solicita uno nuevo.'], 410);
        }

        if ((int)$row->attempts >= 5) {
            return response()->json(['message' => 'Demasiados intentos. Solicita un nuevo código.'], 429);
        }

        if (!Hash::check($code, $row->code_hash)) {
            DB::table('password_reset_codes')->where('correo', $correo)->increment('attempts');
            return response()->json(['message' => 'Código incorrecto.'], 422);
        }

        $user = Usuario::where('correo', $correo)->first();
        if (!$user) {
            return response()->json(['message' => 'Usuario no encontrado.'], 404);
        }

        $user->contrasena = $validated['contrasena'];
        $user->save();

        DB::table('password_reset_codes')->where('correo', $correo)->delete();

        return response()->json(['message' => 'Contraseña actualizada correctamente.']);
    }
}
