<?php

namespace App\Http\Controllers;

use App\Models\Movimiento;

class MovimientoController extends Controller
{
    public function index()
    {
        return response()->json(
            Movimiento::orderBy('fecha', 'desc')->get()
        );
    }
}
