<?php

namespace App\Http\Controllers;

use App\Models\ProductoAsignacionActual;

class ProductoAsignacionActualController extends Controller
{
    public function index()
    {
        $rows = ProductoAsignacionActual::with(['producto', 'responsable', 'area', 'asignacion'])
            ->orderBy('updated_at', 'desc')
            ->get();

        return response()->json($rows);
    }
    public function bienesDeResponsable($id)
    {
        $rows = ProductoAsignacionActual::with(['producto', 'area'])
            ->where('responsable_id', $id)
            ->orderBy('updated_at', 'desc')
            ->get();

        $agrupados = [];
        $areasSet = [];

        foreach ($rows as $row) {
            if (!$row->producto) continue;

            $p = $row->producto;
            $cat = $p->categoria ?? 'Sin categoría';

            if (!isset($agrupados[$cat])) $agrupados[$cat] = [];
            $agrupados[$cat][] = $p;

            $areasSet[$row->area_id] = true;
        }

        $areaUnica = count($areasSet) === 1 ? array_key_first($areasSet) : null;

        return response()->json([
            'agrupados' => $agrupados,
            'areaUnica' => $areaUnica,
        ]);
    }
}
