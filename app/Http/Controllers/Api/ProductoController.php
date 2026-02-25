<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Producto;
use App\Models\ProductoAsignacionActual;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\SimpleType\JcTable;
use Carbon\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProductoController extends Controller
{
    private const CATEGORIAS_CON_SERIE = [
        'Equipo de Computo',
        'Equipo de Oficina',
    ];

    private const CATEGORIAS_VALIDAS = [
        'Equipo de Computo',
        'Equipo de Oficina',
        'Muebles y Enseres',
        'Instalaciones, Maquinarias y Herramientas',
    ];

    private const PREFIJOS = [
        'Equipo de Computo'                         => 'E-EC',
        'Equipo de Oficina'                         => 'E-EO',
        'Muebles y Enseres'                         => 'E-ME',
        'Instalaciones, Maquinarias y Herramientas' => 'E-IM',
    ];

    private function normalizar(?string $s): ?string
    {
        if ($s === null) return null;
        $s = preg_replace('/\s+/', ' ', trim($s));
        return $s === '' ? null : $s;
    }

    private function generarCodigo(string $categoria): string
    {
        $prefijo = self::PREFIJOS[$categoria] ?? 'E-XX';
        $anio = now()->year;

        $ultimo = Producto::where('categoria', $categoria)
            ->where('codigo', 'like', "{$prefijo}-{$anio}-%")
            ->orderBy('codigo', 'desc')
            ->value('codigo');

        $num = 1;
        if ($ultimo) {
            $parte = substr($ultimo, -3);
            if (ctype_digit($parte)) {
                $num = intval($parte) + 1;
            }
        }

        return sprintf("%s-%s-%03d", $prefijo, $anio, $num);
    }

    private function formatearProducto(Producto $p)
    {
        return [
            'id'              => $p->id,
            'codigo'          => $p->codigo,
            'codigo_anterior' => $p->codigo_anterior,
            'nombre'          => $p->nombre,
            'descripcion'     => $p->descripcion,
            'categoria'       => $p->categoria,
            'marca'           => $p->marca,
            'modelo'          => $p->modelo,
            'numero_serie'    => $p->numero_serie,
            'dimensiones'     => $p->dimensiones,
            'color'           => $p->color,
            'es_donado'       => (bool) $p->es_donado,
            'estado'          => $p->estado,
            'motivo_baja'     => $p->motivo_baja,
            'fecha_ingreso'   => $p->fecha_ingreso,
            'fecha_baja'      => $p->fecha_baja,
            'ubicacion_id'    => $p->ubicacion_id,
            'ubicacion_texto' => $p->ubicacion ? ($p->ubicacion->ubicacion) : 'Sin ubicación',
            'created_at'      => $p->created_at,
            'updated_at'      => $p->updated_at,
        ];
    }

    public function index(Request $request)
    {
        $query = Producto::with('ubicacion');

        if ($request->filled('categoria')) $query->where('categoria', $request->categoria);
        if ($request->filled('estado')) $query->where('estado', $request->estado);

        if ($request->filled('donado')) {
            $donado = filter_var($request->donado, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($donado !== null) $query->where('es_donado', $donado);
        }

        if ($request->filled('ubicacion_id')) $query->where('ubicacion_id', $request->ubicacion_id);

        if ($request->filled('search')) {
            $s = trim($request->search);
            $query->where(function ($q) use ($s) {
                $q->where('nombre', 'like', "%{$s}%")
                  ->orWhere('codigo', 'like', "%{$s}%")
                  ->orWhere('descripcion', 'like', "%{$s}%")
                  ->orWhere('codigo_anterior', 'like', "%{$s}%")
;
                  
            });
        }

        $productos = $query->orderBy('id', 'desc')->get();

        return response()->json($productos->map(fn ($p) => $this->formatearProducto($p)), 200);
    }

    public function store(Request $request)
    {
        $request->merge([
            'codigo_anterior' => $this->normalizar($request->codigo_anterior),
            'nombre'       => $this->normalizar($request->nombre),
            'descripcion'  => $this->normalizar($request->descripcion),
            'categoria'    => $this->normalizar($request->categoria),
            'marca'        => $this->normalizar($request->marca),
            'modelo'       => $this->normalizar($request->modelo),
            'numero_serie' => $this->normalizar($request->numero_serie),
            'dimensiones'  => $this->normalizar($request->dimensiones),
            'color'        => $this->normalizar($request->color),
        ]);

        $categoria = $request->categoria;
        $requiereSerie = in_array($categoria, self::CATEGORIAS_CON_SERIE, true);

        $rules = [
            'codigo_anterior' => ['nullable','string','max:60', 'regex:/^[A-Za-z0-9._\-\/]+$/', Rule::unique('productos', 'codigo_anterior'),],
            'nombre'        => ['required', 'string', 'min:3', 'max:80'],
            'descripcion'   => ['required', 'string', 'min:3', 'max:255'],
            'categoria'     => ['required', 'string', Rule::in(self::CATEGORIAS_VALIDAS)],
            'fecha_ingreso' => ['nullable', 'date', 'before_or_equal:today'],
            'ubicacion_id'  => ['nullable', 'exists:departamentos,id'],
            'es_donado'     => ['nullable', 'boolean'],
        ];

        if ($requiereSerie) {
            $rules['marca'] = ['required', 'string', 'min:2', 'max:60'];
            $rules['modelo'] = ['required', 'string', 'min:2', 'max:60'];
            $rules['numero_serie'] = [
                'required','string','min:3','max:60','not_regex:/\s/','regex:/^[A-Za-z0-9._\-\/]+$/',
                Rule::unique('productos')->where(fn ($q) => $q->where('categoria', $categoria)),
            ];
        } else {
            $request->merge(['marca' => null, 'modelo' => null, 'numero_serie' => null]);
        }

        if ($categoria === 'Muebles y Enseres') {
           $rules['dimensiones'] = [
    'required',
    'string',
    'max:30',
    'regex:/^\d+(?:[.,]\d+)?\s*x\s*\d+(?:[.,]\d+)?\s*(cm)?$/i'
];

            $rules['color'] = ['required','string','min:3','max:30'];
        } else {
            $request->merge(['dimensiones' => null, 'color' => null]);
        }

        $data = $request->validate($rules);

        $codigo = $this->generarCodigo($data['categoria']);

        $producto = Producto::create([
            'codigo'        => $codigo,
            'codigo_anterior' => $data['codigo_anterior'] ?? null,
            'nombre'        => $data['nombre'],
            'descripcion'   => $data['descripcion'],
            'categoria'     => $data['categoria'],
            'marca'         => $data['marca'] ?? null,
            'modelo'        => $data['modelo'] ?? null,
            'numero_serie'  => $data['numero_serie'] ?? null,
            'dimensiones'   => $data['dimensiones'] ?? null,
            'color'         => $data['color'] ?? null,
            'fecha_ingreso' => $data['fecha_ingreso'] ?? null,
            'ubicacion_id'  => $data['ubicacion_id'] ?? null,
            'estado'        => 'Activo',
            'es_donado'     => $request->boolean('es_donado'),
        ]);

        if (function_exists('registrarMovimiento')) {
            registrarMovimiento(
                'Creación de producto',
                "Se registró el producto '{$producto->nombre}' en la categoría '{$producto->categoria}' con código '{$producto->codigo}'."
            );
        }

        $producto->load('ubicacion');

        return response()->json($this->formatearProducto($producto), 201);
    }

    public function show($id)
    {
        $producto = Producto::with('ubicacion')->findOrFail($id);
        return response()->json($this->formatearProducto($producto), 200);
    }

    public function update(Request $request, $id)
    {
        $producto = Producto::findOrFail($id);
        $estadoAnterior = $producto->estado;

        $request->merge([
            'codigo_anterior' => $this->normalizar($request->codigo_anterior),
            'nombre'       => $this->normalizar($request->nombre),
            'descripcion'  => $this->normalizar($request->descripcion),
            'categoria'    => $this->normalizar($request->categoria),
            'marca'        => $this->normalizar($request->marca),
            'modelo'       => $this->normalizar($request->modelo),
            'numero_serie' => $this->normalizar($request->numero_serie),
            'dimensiones'  => $this->normalizar($request->dimensiones),
            'color'        => $this->normalizar($request->color),
            'motivo_baja'  => $this->normalizar($request->motivo_baja),
        ]);

        $categoria = $request->input('categoria', $producto->categoria);
        $requiereSerie = in_array($categoria, self::CATEGORIAS_CON_SERIE, true);

        $rules = [
            'codigo_anterior' => [
    'sometimes',
    'nullable',
    'string',
    'max:60',
    'regex:/^[A-Za-z0-9._\-\/]+$/',
    Rule::unique('productos', 'codigo_anterior')->ignore($producto->id),
],

            'nombre'        => ['sometimes', 'required', 'string', 'min:3', 'max:80'],
            'descripcion'   => ['sometimes', 'required', 'string', 'min:3', 'max:255'],
            'categoria'     => ['sometimes', 'required', 'string', Rule::in(self::CATEGORIAS_VALIDAS)],
            'fecha_ingreso' => ['sometimes', 'nullable', 'date', 'before_or_equal:today'],
            'ubicacion_id'  => ['sometimes', 'nullable', 'exists:departamentos,id'],
            'es_donado'     => ['sometimes', 'nullable', 'boolean'],
            'estado'        => ['sometimes', 'string', Rule::in(['Activo', 'Inactivo'])],
            'motivo_baja'   => [
                'sometimes',
                'nullable',
                'string',
                'max:500',
                Rule::requiredIf(fn () =>
                    $request->has('estado') &&
                    $request->input('estado') === 'Inactivo' &&
                    $estadoAnterior !== 'Inactivo'
                ),
            ],
        ];

        if ($requiereSerie) {
            $rules['marca'] = ['sometimes', 'required', 'string', 'min:2', 'max:60'];
            $rules['modelo'] = ['sometimes', 'required', 'string', 'min:2', 'max:60'];
            $rules['numero_serie'] = [
                'sometimes','required','string','min:3','max:60','not_regex:/\s/','regex:/^[A-Za-z0-9._\-\/]+$/',
                Rule::unique('productos')
                    ->where(fn ($q) => $q->where('categoria', $categoria))
                    ->ignore($producto->id),
            ];
        } else {
            $request->merge(['marca' => null, 'modelo' => null, 'numero_serie' => null]);
        }

        if ($categoria === 'Muebles y Enseres') {
           $rules['dimensiones'] = [
    'sometimes',
    'required',
    'string',
    'max:30',
    'regex:/^\d+(?:[.,]\d+)?\s*x\s*\d+(?:[.,]\d+)?\s*(cm)?$/i'
];

            $rules['color'] = ['sometimes','required','string','min:3','max:30'];
        } else {
            $request->merge(['dimensiones' => null, 'color' => null]);
        }

        $data = $request->validate($rules);

        // ✅ Regla de negocio: NO dar de baja si está asignado actualmente
        $quiereInactivar = $request->has('estado') && $request->input('estado') === 'Inactivo' && $estadoAnterior !== 'Inactivo';
        if ($quiereInactivar) {
            $asigActual = ProductoAsignacionActual::where('producto_id', $producto->id)->first();
            if ($asigActual) {
                throw ValidationException::withMessages([
                    'estado' => ['No se puede dar de baja: el bien está asignado actualmente. Primero recepciona (devuelve) el bien y luego da de baja.']
                ]);
            }
        }

        if (isset($data['categoria']) && $data['categoria'] !== $producto->categoria) {
            $producto->categoria = $data['categoria'];
            $producto->codigo = $this->generarCodigo($producto->categoria);
        }

        $producto->fill([
            'codigo_anterior' => array_key_exists('codigo_anterior', $data)
    ? ($data['codigo_anterior'] ?? null)
    : $producto->codigo_anterior,

            'nombre'        => $data['nombre']        ?? $producto->nombre,
            'descripcion'   => $data['descripcion']   ?? $producto->descripcion,
            'marca'         => array_key_exists('marca', $data) ? ($data['marca'] ?? null) : $producto->marca,
            'modelo'        => array_key_exists('modelo', $data) ? ($data['modelo'] ?? null) : $producto->modelo,
            'numero_serie'  => array_key_exists('numero_serie', $data) ? ($data['numero_serie'] ?? null) : $producto->numero_serie,
            'dimensiones'   => array_key_exists('dimensiones', $data) ? ($data['dimensiones'] ?? null) : $producto->dimensiones,
            'color'         => array_key_exists('color', $data) ? ($data['color'] ?? null) : $producto->color,
            'fecha_ingreso' => array_key_exists('fecha_ingreso', $data) ? ($data['fecha_ingreso'] ?? null) : $producto->fecha_ingreso,
            'ubicacion_id'  => array_key_exists('ubicacion_id', $data) ? ($data['ubicacion_id'] ?? null) : $producto->ubicacion_id,
            'estado'        => $data['estado']        ?? $producto->estado,
            'motivo_baja'   => array_key_exists('motivo_baja', $data) ? ($data['motivo_baja'] ?? null) : $producto->motivo_baja,
        ]);

        if ($request->has('es_donado')) $producto->es_donado = $request->boolean('es_donado');

        if ($request->has('estado')) {
            if ($producto->estado === 'Inactivo' && $estadoAnterior !== 'Inactivo') {
                $producto->fecha_baja = now();

                if (function_exists('registrarMovimiento')) {
                    registrarMovimiento(
                        'Baja de producto',
                        "El producto '{$producto->nombre}' (código {$producto->codigo}) fue dado de baja. Motivo: {$producto->motivo_baja}"
                    );
                }
            } elseif ($producto->estado === 'Activo' && $estadoAnterior === 'Inactivo') {
                $producto->fecha_baja = null;

                if (function_exists('registrarMovimiento')) {
                    registrarMovimiento(
                        'Reactivación de producto',
                        "El producto '{$producto->nombre}' (código {$producto->codigo}) fue reactivado."
                    );
                }
            } else {
                if (function_exists('registrarMovimiento')) {
                    registrarMovimiento(
                        'Actualización de producto',
                        "Se actualizó el producto '{$producto->nombre}' (código {$producto->codigo})."
                    );
                }
            }
        } else {
            if (function_exists('registrarMovimiento')) {
                registrarMovimiento(
                    'Actualización de producto',
                    "Se actualizó el producto '{$producto->nombre}' (código {$producto->codigo})."
                );
            }
        }

        $producto->save();
        $producto->load('ubicacion');

        return response()->json($this->formatearProducto($producto), 200);
    }

    public function destroy($id)
    {
        $producto = Producto::findOrFail($id);

        // Reglas de negocio: no borrar si tiene estado actual o historial en pivotes
        $estaAsignado = ProductoAsignacionActual::where('producto_id', $producto->id)->exists();
        $tieneHistAsign = DB()->table('asignacion_producto')->where('producto_id', $producto->id)->exists();
        $tieneHistRecep = DB()->table('recepcion_producto')->where('producto_id', $producto->id)->exists();

        if ($estaAsignado || $tieneHistAsign || $tieneHistRecep) {
            throw ValidationException::withMessages([
                'producto' => ['No se puede eliminar: el bien tiene historial o está asignado. Usa “Dar de baja” en lugar de eliminar.']
            ]);
        }

        $nombre = $producto->nombre;
        $codigo = $producto->codigo;

        $producto->delete();

        if (function_exists('registrarMovimiento')) {
            registrarMovimiento(
                'Eliminación de producto',
                "Se eliminó el producto '{$nombre}' (código {$codigo})."
            );
        }

        return response()->json(['message' => 'Producto eliminado'], 200);
    }

    public function inactivos()
    {
        $productos = Producto::with('ubicacion')
            ->where('estado', 'Inactivo')
            ->orderBy('fecha_baja', 'desc')
            ->get();

        return response()->json($productos->map(fn ($p) => $this->formatearProducto($p)), 200);
    }

    // Tu exportarWordSeleccionados queda igual (solo asegúrate que registrarMovimiento exista)
    public function exportarWordSeleccionados(Request $request)
    {
        $data = $request->validate([
            'ids'   => 'required|array|min:1',
            'ids.*' => 'exists:productos,id',
        ]);

        $ids = $data['ids'];

        $productos = Producto::with('ubicacion')
            ->whereIn('id', $ids)
            ->orderBy('categoria')
            ->orderBy('codigo')
            ->get();

        if ($productos->isEmpty()) {
            return response()->json(['message' => 'No se encontraron productos para los IDs enviados'], 404);
        }

        $porCategoria = $productos->groupBy('categoria');

        $phpWord = new PhpWord();

        $section = $phpWord->addSection([
            'orientation' => 'landscape',
            'marginLeft'  => 800,
            'marginRight' => 800,
            'marginTop'   => 600,
            'marginBottom'=> 600,
        ]);

        $styleTable = [
            'borderSize'  => 6,
            'borderColor' => '1F4E78',
            'cellMargin'  => 80,
            'alignment'   => JcTable::CENTER,
        ];

        $headerCellStyle = ['bgColor' => '1F4E78', 'valign'  => 'center'];
        $headerTextStyle = ['bold' => true, 'color' => 'FFFFFF', 'size' => 9];
        $rowTextStyle    = ['size' => 8];

        foreach ($porCategoria as $categoria => $items) {
            $section->addTextBreak(1);
            $section->addText(
                'Inventario - ' . ($categoria ?? 'Sin categoría'),
                ['bold' => true, 'size' => 14],
                ['alignment' => 'center', 'spaceAfter' => 200]
            );
            $section->addTextBreak(1);

            $headers = [];
            $widths  = [];
            $tipoTabla = '';

            switch ($categoria) {
                case 'Equipo de Computo':
                case 'Equipo de Oficina':
                    $headers = ['Código','Nombre','Descripción','Marca','Modelo','Número Serie','Fecha Ingreso','Ubicación','Estado'];
                    $widths = [1400,2000,2600,1600,1600,2200,1600,2200,1200];
                    $tipoTabla = 'equipo';
                    break;

                case 'Muebles y Enseres':
                    $headers = ['Código','Nombre','Descripción','Dimensiones','Color','Fecha Ingreso','Ubicación','Estado'];
                    $widths = [1400,2000,2600,1800,1400,1600,2200,1200];
                    $tipoTabla = 'muebles';
                    break;

                default:
                    $headers = ['Código','Nombre','Descripción','Fecha Ingreso','Ubicación','Estado'];
                    $widths  = [1400,2200,2600,1600,2200,1200];
                    $tipoTabla = 'instalaciones';
                    break;
            }

            $table = $section->addTable($styleTable);

            $table->addRow();
            foreach ($headers as $i => $titulo) {
                $ancho = $widths[$i] ?? 1500;
                $table->addCell($ancho, $headerCellStyle)->addText($titulo, $headerTextStyle);
            }

            foreach ($items as $p) {
                $table->addRow();

                $fecha = '';
                if ($p->fecha_ingreso) {
                    $fecha = $p->fecha_ingreso instanceof \Carbon\Carbon
                        ? $p->fecha_ingreso->format('d/m/Y')
                        : Carbon::parse($p->fecha_ingreso)->format('d/m/Y');
                }

                $ubicacionTexto = $p->ubicacion
                    ? ($p->ubicacion->ubicacion ?? $p->ubicacion->nombre ?? 'Sin ubicación')
                    : 'Sin ubicación';

                if ($tipoTabla === 'equipo') {
                    $table->addCell($widths[0])->addText($p->codigo ?? '', $rowTextStyle);
                    $table->addCell($widths[1])->addText($p->nombre ?? '', $rowTextStyle);
                    $table->addCell($widths[2])->addText($p->descripcion ?? '', $rowTextStyle);
                    $table->addCell($widths[3])->addText($p->marca ?? '', $rowTextStyle);
                    $table->addCell($widths[4])->addText($p->modelo ?? '', $rowTextStyle);
                    $table->addCell($widths[5])->addText($p->numero_serie ?? '', $rowTextStyle);
                    $table->addCell($widths[6])->addText($fecha, $rowTextStyle);
                    $table->addCell($widths[7])->addText($ubicacionTexto, $rowTextStyle);
                    $table->addCell($widths[8])->addText($p->estado ?? '', $rowTextStyle);
                } elseif ($tipoTabla === 'muebles') {
                    $table->addCell($widths[0])->addText($p->codigo ?? '', $rowTextStyle);
                    $table->addCell($widths[1])->addText($p->nombre ?? '', $rowTextStyle);
                    $table->addCell($widths[2])->addText($p->descripcion ?? '', $rowTextStyle);
                    $table->addCell($widths[3])->addText($p->dimensiones ?? '', $rowTextStyle);
                    $table->addCell($widths[4])->addText($p->color ?? '', $rowTextStyle);
                    $table->addCell($widths[5])->addText($fecha, $rowTextStyle);
                    $table->addCell($widths[6])->addText($ubicacionTexto, $rowTextStyle);
                    $table->addCell($widths[7])->addText($p->estado ?? '', $rowTextStyle);
                } else {
                    $table->addCell($widths[0])->addText($p->codigo ?? '', $rowTextStyle);
                    $table->addCell($widths[1])->addText($p->nombre ?? '', $rowTextStyle);
                    $table->addCell($widths[2])->addText($p->descripcion ?? '', $rowTextStyle);
                    $table->addCell($widths[3])->addText($fecha, $rowTextStyle);
                    $table->addCell($widths[4])->addText($ubicacionTexto, $rowTextStyle);
                    $table->addCell($widths[5])->addText($p->estado ?? '', $rowTextStyle);
                }
            }
        }

        $outputDir = storage_path('app/reportes');
        if (!is_dir($outputDir)) mkdir($outputDir, 0775, true);

        $filename = 'Inventario-EPESPO-' . now()->format('Ymd-His') . '.docx';
        $fullPath = $outputDir . '/' . $filename;

        $writer = IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($fullPath);

        if (function_exists('registrarMovimiento')) {
            registrarMovimiento(
                'Generación de reporte Word',
                "Se generó un documento Word de inventario con {$productos->count()} productos."
            );
        }

        return response()->download($fullPath, $filename)->deleteFileAfterSend(true);
    }
}
