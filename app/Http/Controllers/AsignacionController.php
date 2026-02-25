<?php

namespace App\Http\Controllers;

use App\Models\Asignacion;
use App\Models\Producto;
use App\Models\ProductoAsignacionActual;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AsignacionController extends Controller
{
    public function index(Request $request)
    {
        // el front puede pedir explícitamente las anuladas con ?mostrarAnuladas=true
        $qry = Asignacion::with(['responsable', 'area', 'productos']);

        if (! $request->boolean('mostrarAnuladas')) {
            $qry->where('activo', true);
        }

        $asignaciones = $qry->orderBy('fecha_asignacion', 'desc')->get();

        return response()->json($asignaciones);
    }

    private function validarProductosParaAsignar(array $productoIds, string $categoria): void
    {
        // 1) No permitir productos inactivos
        $inactivos = Producto::whereIn('id', $productoIds)
            ->where('estado', '!=', 'Activo')
            ->get(['id','codigo','nombre','estado']);

        if ($inactivos->isNotEmpty()) {
            $lista = $inactivos
                ->map(fn ($p) => ($p->codigo ?? "ID {$p->id}") . " - " . ($p->nombre ?? '') . " ({$p->estado})")
                ->implode(' | ');

            throw ValidationException::withMessages([
                'productos' => ["No se puede asignar: hay bienes Inactivos. {$lista}"]
            ]);
        }

        // 2) No permitir mezclar categorías (consistencia)
        $fueraCategoria = Producto::whereIn('id', $productoIds)
            ->where('categoria', '!=', $categoria)
            ->get(['id','codigo','nombre','categoria']);

        if ($fueraCategoria->isNotEmpty()) {
            $lista = $fueraCategoria
                ->map(fn ($p) => ($p->codigo ?? "ID {$p->id}") . " - " . ($p->nombre ?? '') . " (Cat: {$p->categoria})")
                ->implode(' | ');

            throw ValidationException::withMessages([
                'productos' => ["No se puede asignar: hay bienes de otra categoría distinta a '{$categoria}'. {$lista}"]
            ]);
        }
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'responsable_id'    => 'required|exists:responsables,id',
            'area_id'           => 'required|exists:departamentos,id',
            'fecha_asignacion'  => 'required|date',
            'categoria'         => 'required|string|max:255',
            'productos'         => 'required|array|min:1',
            'productos.*'       => 'exists:productos,id',
        ]);

        $productoIds = array_values(array_unique(array_map('intval', $data['productos'])));

        return DB::transaction(function () use ($data, $productoIds) {

            // ✅ Lock real
            Producto::whereIn('id', $productoIds)->lockForUpdate()->get();

            // ✅ Reglas de negocio
            $this->validarProductosParaAsignar($productoIds, $data['categoria']);

            // ✅ Si existe estado actual, ese producto ya está asignado
            $ocupados = ProductoAsignacionActual::with(['producto', 'responsable'])
                ->whereIn('producto_id', $productoIds)
                ->lockForUpdate()
                ->get();

            if ($ocupados->count() > 0) {
                $lista = $ocupados->map(function ($x) {
                    $p = $x->producto ? "{$x->producto->codigo} - {$x->producto->nombre}" : "producto_id {$x->producto_id}";
                    $r = $x->responsable ? trim($x->responsable->nombre . ' ' . $x->responsable->apellido) : "responsable_id {$x->responsable_id}";
                    return "{$p} (Asignado a: {$r})";
                })->implode(' | ');

                throw ValidationException::withMessages([
                    'productos' => ["No se puede asignar: hay bienes ya asignados. {$lista}"]
                ]);
            }

            $asignacion = Asignacion::create([
                'responsable_id'   => $data['responsable_id'],
                'area_id'          => $data['area_id'],
                'fecha_asignacion' => $data['fecha_asignacion'],
                'categoria'        => $data['categoria'],
                'acta_id'          => null,
            ]);

            $asignacion->productos()->sync($productoIds);

            // ✅ Crear estado actual
            $rows = [];
            $now = now();
            foreach ($productoIds as $pid) {
                $rows[] = [
                    'producto_id'      => $pid,
                    'responsable_id'   => $data['responsable_id'],
                    'area_id'          => $data['area_id'],
                    'asignacion_id'    => $asignacion->id,
                    'fecha_asignacion' => $data['fecha_asignacion'],
                    'created_at'       => $now,
                    'updated_at'       => $now,
                ];
            }
            DB::table('producto_asignaciones_actuales')->insert($rows);

            // ✅ Movimiento
            if (function_exists('registrarMovimiento')) {
                $asignacion->load(['responsable', 'area', 'productos']);

                $responsableNombre = $asignacion->responsable
                    ? trim($asignacion->responsable->nombre . ' ' . $asignacion->responsable->apellido)
                    : "ID {$asignacion->responsable_id}";

                $areaNombre = $asignacion->area
                    ? $asignacion->area->nombre . ($asignacion->area->ubicacion ? ' - ' . $asignacion->area->ubicacion : '')
                    : 'Sin área';

                $listaProductos = $asignacion->productos
                    ->map(fn ($p) => "{$p->codigo} - {$p->nombre}")
                    ->implode(', ');

                $descripcion = "Se asignaron los productos: {$listaProductos} al responsable {$responsableNombre} en el área {$areaNombre}.";
                registrarMovimiento('Creación de asignación', $descripcion);
            }

            return response()->json($asignacion->load(['responsable','area','productos']), 201);
        });
    }

    public function update(Request $request, $id)
    {
        $asignacion = Asignacion::with('productos')->findOrFail($id);

        // si sólo vienen los campos de anulación, hacemos un camino directo sin validar productos, etc.
        if ($request->has('activo') && $request->get('activo') === false
            && !$request->has('responsable_id')
            && !$request->has('area_id')
            && !$request->has('fecha_asignacion')
            && !$request->has('categoria')
            && !$request->has('productos')
        ) {
            $data = $request->validate([
                'activo' => 'required|boolean',
                'motivo_anulacion' => 'nullable|string|max:1000',
            ]);

            $antesActivo = $asignacion->activo;
            $asignacion->update([
                'activo' => false,
                'motivo_anulacion' => $data['motivo_anulacion'] ?? null,
            ]);

            if ($antesActivo) {
                // eliminar cualquier estado actual asociado
                ProductoAsignacionActual::where('asignacion_id', $asignacion->id)->delete();
            }

            if (function_exists('registrarMovimiento')) {
                // cargar relaciones para construir una descripción legible
                $asignacion->load(['responsable','area','productos']);

                $responsableNombre = $asignacion->responsable
                    ? trim($asignacion->responsable->nombre . ' ' . $asignacion->responsable->apellido)
                    : "ID {$asignacion->responsable_id}";

                $areaNombre = $asignacion->area
                    ? $asignacion->area->nombre . ($asignacion->area->ubicacion ? ' - ' . $asignacion->area->ubicacion : '')
                    : 'Sin área';

                $listaProductos = $asignacion->productos
                    ->map(fn ($p) => "{$p->codigo} - {$p->nombre}")
                    ->implode(', ');

                $desc = "Asignación anulada: productos {$listaProductos} para responsable {$responsableNombre} en área {$areaNombre} (ID {$asignacion->id})";
                if (!empty($data['motivo_anulacion'])) {
                    $desc .= ". Motivo: {$data['motivo_anulacion']}";
                }

                registrarMovimiento('Anulación de asignación', $desc);
            }

            return response()->json($asignacion);
        }

        // validación normal para modificaciones completas
        $data = $request->validate([
            'responsable_id'    => 'required|exists:responsables,id',
            'area_id'           => 'required|exists:departamentos,id',
            'fecha_asignacion'  => 'required|date',
            'categoria'         => 'required|string|max:255',
            'productos'         => 'required|array|min:1',
            'productos.*'       => 'exists:productos,id',
            'activo'            => 'sometimes|boolean',
            'motivo_anulacion'  => 'nullable|string|max:1000',
        ]);

        $newIds = array_values(array_unique(array_map('intval', $data['productos'])));

        return DB::transaction(function () use ($asignacion, $data, $newIds) {

            $oldIds = $asignacion->productos->pluck('id')->map(fn ($x) => (int)$x)->all();

            $added   = array_values(array_diff($newIds, $oldIds));
            $removed = array_values(array_diff($oldIds, $newIds));
            $allIds  = array_values(array_unique(array_merge($oldIds, $newIds)));

            // ✅ Lock real
            Producto::whereIn('id', $allIds)->lockForUpdate()->get();

            // ✅ Reglas de negocio sobre TODO el set nuevo
            $this->validarProductosParaAsignar($newIds, $data['categoria']);

            // Bloquear estados actuales existentes
            $estados = ProductoAsignacionActual::whereIn('producto_id', $allIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('producto_id');

            // ✅ Validar: los agregados NO pueden pertenecer a otra asignación
            foreach ($added as $pid) {
                if (isset($estados[$pid]) && (int)$estados[$pid]->asignacion_id !== (int)$asignacion->id) {
                    throw ValidationException::withMessages([
                        'productos' => ["El producto_id {$pid} ya está asignado en otra asignación (id {$estados[$pid]->asignacion_id})."]
                    ]);
                }
            }

            // ✅ Validar: los que ya estaban, deben seguir perteneciendo a esta asignación
            foreach ($oldIds as $pid) {
                if (isset($estados[$pid]) && (int)$estados[$pid]->asignacion_id !== (int)$asignacion->id) {
                    throw ValidationException::withMessages([
                        'productos' => ["El producto_id {$pid} ya no pertenece a esta asignación. Refresca la página (estado cambió)."]
                    ]);
                }
            }

            $updateData = [
                'responsable_id'   => $data['responsable_id'],
                'area_id'          => $data['area_id'],
                'fecha_asignacion' => $data['fecha_asignacion'],
                'categoria'        => $data['categoria'],
            ];

            // permitir anular mediante el mismo endpoint
            if (array_key_exists('activo', $data)) {
                $updateData['activo'] = (bool)$data['activo'];
            }
            if (array_key_exists('motivo_anulacion', $data)) {
                $updateData['motivo_anulacion'] = $data['motivo_anulacion'];
            }

            $antesActivo = $asignacion->activo;

            $asignacion->update($updateData);

            // si la asignación se desactivó, limpiar estados actuales de productos
            if ($antesActivo && array_key_exists('activo', $data) && $data['activo'] === false) {
                ProductoAsignacionActual::where('asignacion_id', $asignacion->id)->delete();
            }

            $asignacion->productos()->sync($newIds);

            // ✅ Eliminar estado de removidos solo si pertenecen a ESTA asignación
            if (count($removed) > 0) {
                ProductoAsignacionActual::whereIn('producto_id', $removed)
                    ->where('asignacion_id', $asignacion->id)
                    ->delete();
            }

            // ✅ Upsert estado actual
            foreach ($newIds as $pid) {
                ProductoAsignacionActual::updateOrCreate(
                    ['producto_id' => $pid],
                    [
                        'responsable_id'   => $data['responsable_id'],
                        'area_id'          => $data['area_id'],
                        'asignacion_id'    => $asignacion->id,
                        'fecha_asignacion' => $data['fecha_asignacion'],
                    ]
                );
            }

            if (function_exists('registrarMovimiento')) {
                $asignacion->load(['responsable','area','productos']);

                $responsableNombre = $asignacion->responsable
                    ? trim($asignacion->responsable->nombre . ' ' . $asignacion->responsable->apellido)
                    : "ID {$asignacion->responsable_id}";

                $areaNombre = $asignacion->area
                    ? $asignacion->area->nombre . ($asignacion->area->ubicacion ? ' - ' . $asignacion->area->ubicacion : '')
                    : 'Sin área';

                $listaProductos = $asignacion->productos
                    ->map(fn ($p) => "{$p->codigo} - {$p->nombre}")
                    ->implode(', ');

                $descripcion = "Se actualizaron las asignaciones de {$listaProductos} para el responsable {$responsableNombre} en el área {$areaNombre}.";
                registrarMovimiento('Actualización de asignación', $descripcion);
            }

            return response()->json($asignacion->load(['responsable','area','productos']));
        });
    }

    public function destroy($id)
    {
        $asignacion = Asignacion::with(['responsable', 'area'])->findOrFail($id);

        return DB::transaction(function () use ($asignacion) {

            ProductoAsignacionActual::where('asignacion_id', $asignacion->id)->delete();

            $nombreResp = $asignacion->responsable
                ? trim($asignacion->responsable->nombre . ' ' . $asignacion->responsable->apellido)
                : "responsable ID {$asignacion->responsable_id}";

            $nombreArea = $asignacion->area
                ? trim($asignacion->area->nombre . ($asignacion->area->ubicacion ? ' - ' . $asignacion->area->ubicacion : ''))
                : "área ID {$asignacion->area_id}";

            $idAsign = $asignacion->id;
            $asignacion->delete();

            if (function_exists('registrarMovimiento')) {
                registrarMovimiento(
                    'Eliminación de asignación',
                    "Se eliminó la asignación {$idAsign} del responsable {$nombreResp} en el área {$nombreArea}."
                );
            }

            return response()->json(['message' => 'Asignación eliminada']);
        });
    }
}
