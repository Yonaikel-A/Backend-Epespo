<?php

namespace App\Http\Controllers;

use App\Models\Acta;
use App\Models\Asignacion;
use App\Models\Recepcion;
use App\Models\ProductoAsignacionActual;
use App\Models\Responsable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

use PhpOffice\PhpWord\TemplateProcessor;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\SimpleType\JcTable;

use Carbon\Carbon;

class ActaController extends Controller
{
    public function index()
    {
        $actas = Acta::with([
            'asignaciones.responsable',
            'asignaciones.area',
            'asignaciones.productos',
            'responsable',
            'recepciones.productos',
            'recepciones.area',
            'recepciones.responsable',
        ])->orderBy('created_at', 'desc')->get();
        foreach ($actas as $acta) {
            $tieneAsignaciones = $acta->asignaciones && $acta->asignaciones->count() > 0;

            if (!$tieneAsignaciones && $acta->responsable_id) {
                $tieneRecepciones = $acta->recepciones && $acta->recepciones->count() > 0;

                if (!$tieneRecepciones) {
                    $recepciones = Recepcion::with(['productos', 'area', 'responsable'])
                        ->where('responsable_id', $acta->responsable_id)
                        ->whereDate('fecha_devolucion', $acta->fecha_creacion)
                        ->get();

                    $acta->setRelation('recepciones', $recepciones);
                }
            }
        }

        return response()->json($actas);
    }

    public function generarDesdeAsignacion(Request $request)
    {
        $data = $request->validate([
            'asignacion_id' => 'required|exists:asignaciones,id',
        ]);

        $asignacionBase = Asignacion::with(['responsable', 'area', 'productos'])
            ->where('activo', true)
            ->findOrFail($data['asignacion_id']);
        if (!$asignacionBase->activo) {
            return response()->json([ 'message' => 'La asignación seleccionada está anulada' ], 400);
        }

        $asignacionesDelLote = Asignacion::with(['productos', 'area', 'responsable'])
            ->where('responsable_id', $asignacionBase->responsable_id)
            ->where('area_id', $asignacionBase->area_id)
            ->whereDate('fecha_asignacion', $asignacionBase->fecha_asignacion)
            ->where('activo', true)
            ->whereNull('acta_id')
            ->get();

        if ($asignacionesDelLote->isEmpty()) {
            return response()->json([
                'message' => 'No hay asignaciones pendientes para este responsable / área / fecha'
            ], 400);
        }

        $year = now()->year;
        $correlativo = Acta::whereYear('fecha_creacion', $year)->count() + 1;
        $codigo = 'ACT-' . $year . '-' . str_pad($correlativo, 4, '0', STR_PAD_LEFT);

        $acta = Acta::create([
            'codigo'           => $codigo,
            'asignacion_id'    => $asignacionBase->id,
            'responsable_id'   => $asignacionBase->responsable_id,
            'fecha_creacion'   => now()->toDateString(),
            'estado'           => 'Generada',
            'archivo_pdf_path' => null,
        ]);

        foreach ($asignacionesDelLote as $asig) {
            $asig->acta_id = $acta->id;
            $asig->save();
        }

        if ($asignacionBase->responsable) {
            $asignacionBase->responsable->update([
                'activo' => true,
                'fecha_inactivacion' => null,
                'motivo_inactivacion' => null,
            ]);
        }

        $plantillaPath = storage_path('app/plantillas/FO-ER-012.docx');
        if (!file_exists($plantillaPath)) {
            return response()->json([
                'message' => 'No se encontró la plantilla en storage/app/plantillas/FO-ER-012.docx',
            ], 404);
        }

        $template = new TemplateProcessor($plantillaPath);

        $responsable = $asignacionBase->responsable;
        $area        = $asignacionBase->area;

        Carbon::setLocale('es');
        $fechaAsignacion = Carbon::parse($asignacionBase->fecha_asignacion);

        $dia  = $fechaAsignacion->format('d');
        $mes  = mb_strtolower($fechaAsignacion->translatedFormat('F'), 'UTF-8');
        $anio = $fechaAsignacion->format('Y');

        $tituloResponsable = $responsable->titulo ?? '';
        $nombreResp        = $responsable->nombre ?? '';
        $apellidoResp      = $responsable->apellido ?? '';
        $cedulaResp        = $responsable->cedula ?? '';

        $template->setValue('codigo_acta', $codigo);
        $template->setValue('fecha_acta', now()->format('d/m/Y'));
        $template->setValue('fecha_acta_numerica', now()->format('d/m/Y'));

        $template->setValue('dia_fecha', $dia);
        $template->setValue('mes_fecha', $mes);
        $template->setValue('anio_fecha', $anio);

        $template->setValue('responsable_titulo', $tituloResponsable);
        $template->setValue('responsable_nombre', $nombreResp);
        $template->setValue('responsable_apellido', $apellidoResp);
        $template->setValue('responsable_cedula', $cedulaResp);

        $template->setValue('area_nombre', $area->area ?? $area->nombre ?? '');

        $styleTable = [
            'borderSize'  => 6,
            'borderColor' => '1F4E78',
            'cellMargin'  => 80,
            'alignment'   => JcTable::CENTER,
        ];

        $headerCellStyle = [
            'bgColor' => '1F4E78',
            'valign'  => 'center',
        ];
        $headerTextStyle = [
            'bold'  => true,
            'color' => 'FFFFFF',
        ];

        $table = new Table($styleTable);

        $table->addRow();
        $table->addCell(1600, $headerCellStyle)->addText('Código', $headerTextStyle);
        $table->addCell(1200, $headerCellStyle)->addText('Estado', $headerTextStyle);
        $table->addCell(2200, $headerCellStyle)->addText('Nombre', $headerTextStyle);
        $table->addCell(1800, $headerCellStyle)->addText('Categoría', $headerTextStyle);
        $table->addCell(3000, $headerCellStyle)->addText('Descripción', $headerTextStyle);
        $table->addCell(1800, $headerCellStyle)->addText('Área', $headerTextStyle);

        foreach ($asignacionesDelLote as $asig) {
            foreach ($asig->productos as $prod) {
                $table->addRow();
                $table->addCell(1600)->addText($prod->codigo ?? '');
                $table->addCell(1200)->addText($prod->estado ?? 'Activo');
                $table->addCell(2200)->addText($prod->nombre ?? '');
                $table->addCell(1800)->addText($prod->categoria ?? '');
                $table->addCell(3000)->addText($prod->descripcion ?? '');
                $table->addCell(1800)->addText($asig->area->area ?? $asig->area->nombre ?? '');
            }
        }

        $template->setComplexBlock('tabla_productos', $table);

        $outputDir = storage_path('app/actas');
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0775, true);
        }

        $outputPathDocx = $outputDir . '/ACTA-' . $codigo . '.docx';
        $template->saveAs($outputPathDocx);

        $archivoPathDocx = 'actas/ACTA-' . $codigo . '.docx';

        $acta->update([
            'archivo_path'     => $archivoPathDocx,
            'archivo_pdf_path' => null,
        ]);

        return response()->json([
            'message'      => 'Acta generada correctamente',
            'acta_id'      => $acta->id,
            'download_doc' => null,
            'download_pdf' => null,
        ]);
    }

    public function generarDesdeRecepcion(Request $request)
    {
        $data = $request->validate([
            'recepcion_id' => 'required|exists:recepciones,id',
        ]);

        $recepcionBase = Recepcion::with(['responsable', 'area', 'productos'])
            ->findOrFail($data['recepcion_id']);

        $recepcionesDelLote = Recepcion::with(['productos', 'area', 'responsable'])
            ->where('responsable_id', $recepcionBase->responsable_id)
            ->where('area_id', $recepcionBase->area_id)
            ->whereDate('fecha_devolucion', $recepcionBase->fecha_devolucion)
            ->whereNull('acta_id')
            ->get();

        if ($recepcionesDelLote->isEmpty()) {
            return response()->json([
                'message' => 'No hay recepciones pendientes (ya tienen acta o no existen) para este responsable / área / fecha de devolución.'
            ], 400);
        }

        $year = now()->year;
        $correlativo = Acta::whereYear('fecha_creacion', $year)->count() + 1;
        $codigo = 'ACT-' . $year . '-' . str_pad($correlativo, 4, '0', STR_PAD_LEFT);

        $fechaDevCarbon = Carbon::parse($recepcionBase->fecha_devolucion);

        $acta = Acta::create([
            'codigo'           => $codigo,
            'asignacion_id'    => null,
            'responsable_id'   => $recepcionBase->responsable_id,
            'fecha_creacion'   => $fechaDevCarbon->toDateString(),
            'estado'           => 'Generada',
            'archivo_pdf_path' => null,
        ]);

        foreach ($recepcionesDelLote as $rec) {
            $rec->acta_id = $acta->id;
            $rec->save();
        }

        $plantillaPath = storage_path('app/plantillas/ACTA_RECEPCION.docx');
        if (!file_exists($plantillaPath)) {
            return response()->json([
                'message' => 'No se encontró la plantilla en storage/app/plantillas/ACTA_RECEPCION.docx',
            ], 404);
        }

        $template = new TemplateProcessor($plantillaPath);

        $responsable = $recepcionBase->responsable;
        $area        = $recepcionBase->area;

        Carbon::setLocale('es');
        $fechaCorta = $fechaDevCarbon->format('d/m/Y');
        $fechaLarga = $fechaDevCarbon->isoFormat('D [de] MMMM [del año] YYYY');

        $tituloResponsable = $responsable->titulo ?? '';
        $nombreResp        = trim(($responsable->nombre ?? '') . ' ' . ($responsable->apellido ?? ''));
        $cedulaResp        = $responsable->cedula ?? '';
        $cargoResp         = $responsable->cargo ?? '';
        $departamento      = $area->area ?? $area->nombre ?? '';

        $template->setValue('fecha_devolucion_corta', $fechaCorta);
        $template->setValue('responsable_nombre_completo', $nombreResp);
        $template->setValue('responsable_cedula', $cedulaResp);
        $template->setValue('departamento_nombre', $departamento);
        $template->setValue('fecha_devolucion_larga', $fechaLarga);

        $template->setValue('responsable_titulo', $tituloResponsable);
        $template->setValue('responsable_cargo', $cargoResp);

        $styleTable = [
            'borderSize'  => 6,
            'borderColor' => '1F4E78',
            'cellMargin'  => 80,
            'alignment'   => JcTable::CENTER,
        ];

        $headerCellStyle = [
            'bgColor' => '1F4E78',
            'valign'  => 'center',
        ];
        $headerTextStyle = [
            'bold'  => true,
            'color' => 'FFFFFF',
        ];

        $table = new Table($styleTable);

        $table->addRow();
        $table->addCell(800,  $headerCellStyle)->addText('Ítem', $headerTextStyle);
        $table->addCell(1800, $headerCellStyle)->addText('Categoría', $headerTextStyle);
        $table->addCell(2200, $headerCellStyle)->addText('Nombre', $headerTextStyle);
        $table->addCell(1800, $headerCellStyle)->addText('Marca', $headerTextStyle);
        $table->addCell(2000, $headerCellStyle)->addText('Número Serie', $headerTextStyle);
        $table->addCell(1400, $headerCellStyle)->addText('Estado', $headerTextStyle);
        $table->addCell(2200, $headerCellStyle)->addText('Observaciones', $headerTextStyle);

        $contador = 1;

        foreach ($recepcionesDelLote as $rec) {
            foreach ($rec->productos as $prod) {
                $table->addRow();

                $categoria   = $prod->categoria ?? $rec->categoria ?? '';
                $nombre      = $prod->nombre ?? '';
                $marca       = $prod->marca ?: 'N/A';
                $numSerie    = $prod->numero_serie ?: 'N/A';
                $estado      = $prod->estado ?? 'Activo';
                $observacion = '';

                $table->addCell(800)->addText((string)$contador++);
                $table->addCell(1800)->addText($categoria);
                $table->addCell(2200)->addText($nombre);
                $table->addCell(1800)->addText($marca);
                $table->addCell(2000)->addText($numSerie);
                $table->addCell(1400)->addText($estado);
                $table->addCell(2200)->addText($observacion);
            }
        }

        $template->setComplexBlock('tabla_bienes_recepcion', $table);

        $outputDir = storage_path('app/actas');
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0775, true);
        }

        $outputPathDocx = $outputDir . '/ACTA-' . $codigo . '.docx';
        $template->saveAs($outputPathDocx);

        $archivoPathDocx = 'actas/ACTA-' . $codigo . '.docx';

        $acta->update([
            'archivo_path'     => $archivoPathDocx,
            'archivo_pdf_path' => null,
        ]);

        return response()->json([
            'message'      => 'Acta de recepción generada correctamente',
            'acta_id'      => $acta->id,
            'download_doc' => null,
            'download_pdf' => null,
        ]);
    }


    public function descargar($id)
    {
        return $this->descargarWord($id);
    }

    public function descargarWord($id)
    {
        $acta = Acta::findOrFail($id);

        if (!$acta->archivo_path) {
            return response()->json(['message' => 'No hay archivo Word registrado para esta acta'], 404);
        }
        if (!str_starts_with($acta->archivo_path, 'actas/')) {
            return response()->json(['message' => 'Ruta de archivo no permitida'], 400);
        }

        $path = storage_path('app/' . $acta->archivo_path);

        if (!file_exists($path)) {
            return response()->json(['message' => 'Archivo Word no encontrado'], 404);
        }

        return response()->download($path, basename($path));
    }
    public function descargarPdf($id)
    {
        $acta = Acta::findOrFail($id);

        if (!$acta->archivo_pdf_path) {
            return response()->json(['message' => 'No hay archivo PDF registrado para esta acta'], 404);
        }
        if (!str_starts_with($acta->archivo_pdf_path, 'actas/')) {
            return response()->json(['message' => 'Ruta de archivo no permitida'], 400);
        }
        $disk = Storage::disk('local');
        if (!$disk->exists($acta->archivo_pdf_path)) {
            $publicDisk = Storage::disk('public');
            if ($publicDisk->exists($acta->archivo_pdf_path)) {
                $disk = $publicDisk;
            } else {
                return response()->json(['message' => 'Archivo PDF no encontrado'], 404);
            }
        }

        $path = $disk->path($acta->archivo_pdf_path);

        return response()->download($path, basename($path), [
            'Content-Type' => 'application/pdf',
        ]);
    }
    public function subirPdfRecepcion(Request $request, Acta $acta)
    {
        $request->validate([
            'pdf' => 'required|file|mimes:pdf|max:20240', 
        ]);
        $acta->load([
            'asignaciones.productos',
            'asignaciones.responsable',
            'recepciones.productos',
            'recepciones.responsable',
        ]);


        $esAsignacion = ($acta->asignaciones && $acta->asignaciones->count() > 0) || !is_null($acta->asignacion_id);
        if (!$esAsignacion && (!$acta->recepciones || $acta->recepciones->count() === 0)) {
            $acta->setRelation(
                'recepciones',
                Recepcion::with(['productos', 'responsable'])
                    ->where('responsable_id', $acta->responsable_id)
                    ->whereDate('fecha_devolucion', $acta->fecha_creacion)
                    ->get()
            );
        }
        $esRecepcion = !$esAsignacion && ($acta->recepciones && $acta->recepciones->count() > 0);

        if (!$esRecepcion && !$esAsignacion) {
            return response()->json([
                'message' => 'No se pudo determinar el tipo de acta (ni Recepción ni Asignación).',
            ], 422);
        }

        return DB::transaction(function () use ($request, $acta, $esRecepcion, $esAsignacion) {
            if ($acta->archivo_pdf_path) {
                Storage::disk('local')->delete($acta->archivo_pdf_path);
                Storage::disk('public')->delete($acta->archivo_pdf_path);
            }

            $file = $request->file('pdf');
            $folder = $esRecepcion ? 'actas/recepciones' : 'actas/asignaciones';
            $path = $file->store($folder, 'local'); 

            $acta->archivo_pdf_path = $path;
            $acta->save();
            if ($esAsignacion) {
                return response()->json([
                    'message' => 'PDF de asignación subido correctamente ',
                    'archivo_pdf_path' => $acta->archivo_pdf_path,
                ]);
            }
            $totalDesbloqueados = 0;

            foreach ($acta->recepciones as $recepcion) {
                $productoIds = $recepcion->productos->pluck('id')->values()->all();
                if (count($productoIds) === 0) continue;

                $responsableId = $recepcion->responsable_id;

                $deleted = ProductoAsignacionActual::whereIn('producto_id', $productoIds)
                    ->where('responsable_id', $responsableId)
                    ->delete();

                $totalDesbloqueados += $deleted;
            }

            $quedanBienes = ProductoAsignacionActual::where('responsable_id', $acta->responsable_id)->exists();
            $responsableInactivado = false;

            if (!$quedanBienes) {
                $resp = Responsable::find($acta->responsable_id);
                if ($resp) {
                    $resp->update([
                        'activo' => false,
                        'fecha_inactivacion' => now()->toDateString(),
                        'motivo_inactivacion' => 'Salida de la empresa (acta de recepción)',
                    ]);
                    $responsableInactivado = true;
                }
            }

            return response()->json([
                'message' => $responsableInactivado
                    ? 'PDF subido, bienes desbloqueados y responsable inactivado '
                    : 'PDF subido y bienes desbloqueados  (responsable sigue activo porque aún tiene bienes asignados)',
                'archivo_pdf_path' => $acta->archivo_pdf_path,
                'desbloqueados' => $totalDesbloqueados,
                'responsable_inactivado' => $responsableInactivado,
            ]);
        });
    }
}
