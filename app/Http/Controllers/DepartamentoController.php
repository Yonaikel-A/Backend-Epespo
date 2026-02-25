<?php

namespace App\Http\Controllers;

use App\Models\Departamento;
use Illuminate\Http\Request;

class DepartamentoController extends Controller
{
    public function index()
    {
        $departamentos = Departamento::with('responsable')->get();
        return response()->json($departamentos);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:100',
            'ubicacion' => 'required|string|max:150',
            'responsable_id' => 'nullable|exists:responsables,id',
        ]);

        $departamento = Departamento::create($validated);

        registrarMovimiento(
            'Creación de departamento',
            "Se creó el departamento '{$departamento->nombre}' en el ubicacion '{$departamento->ubicacion}'."
        );

        return response()->json($departamento, 201);
    }

    public function show($id)
    {
        $departamento = Departamento::with('responsable')->findOrFail($id);
        return response()->json($departamento);
    }

    public function update(Request $request, $id)
    {
        $departamento = Departamento::findOrFail($id);

        $validated = $request->validate([
            'nombre' => 'required|string|max:100',
            'ubicacion' => 'required|string|max:150',
            'responsable_id' => 'nullable|exists:responsables,id',
        ]);

        $departamento->update($validated);

        registrarMovimiento(
            'Actualización de departamento',
            "Se actualizó el departamento '{$departamento->nombre}'."
        );

        return response()->json($departamento);
    }

   public function destroy($id)
{
    $departamento = Departamento::findOrFail($id);
    $tieneProductos = \App\Models\Producto::where('ubicacion_id', $id)->exists();

    if ($tieneProductos) {
        return response()->json([
            'message' => 'No se puede eliminar este departamento porque tiene productos asociados.'
        ], 409); 
    }

    $nombre = $departamento->nombre;
    $departamento->delete();

    registrarMovimiento(
        'Eliminación de departamento',
        "Se eliminó el departamento '{$nombre}'."
    );

    return response()->json(['message' => 'Departamento eliminado correctamente ']);
}

}
