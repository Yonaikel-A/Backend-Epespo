<?php

use App\Models\Movimiento;

function registrarMovimiento($accion, $descripcion)
{
    $user = auth()->user();

    if (!$user) {
        return; 
    }

    Movimiento::create([
        'accion' => $accion,
        'descripcion' => $descripcion,
        'usuario_id' => $user->id,
        'fecha' => now(),
    ]);
}
