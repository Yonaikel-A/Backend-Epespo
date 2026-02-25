<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Responsable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\SimpleType\JcTable;

class ResponsableController extends Controller
{
    private const TITULOS = ['Ing','Lcdo','Lcda','Tnlgo','Tnlga','Econ','Mag','Biol'];

    private function normalizar(?string $s): ?string
    {
        if ($s === null) return null;
        $s = preg_replace('/\s+/', ' ', trim($s));
        return $s === '' ? null : $s;
    }

    private function cedulaEcuatorianaValida(string $cedula): bool
    {
        $cedula = preg_replace('/\D/', '', $cedula);
        if (strlen($cedula) !== 10) return false;

        $prov = intval(substr($cedula, 0, 2));
        if ($prov < 1 || $prov > 24) return false;

        $dig = array_map('intval', str_split($cedula));
        $total = 0;

        for ($i = 0; $i < 9; $i++) {
            $num = $dig[$i];
            if ($i % 2 === 0) $num *= 2;
            if ($num > 9) $num -= 9;
            $total += $num;
        }

        $ver = $total % 10 === 0 ? 0 : 10 - ($total % 10);
        return $ver === $dig[9];
    }

    public function index()
    {
        return response()->json(Responsable::all(), 200);
    }

    public function store(Request $request)
    {
        $data = [
            'titulo'   => $this->normalizar($request->input('titulo')),
            'nombre'   => $this->normalizar($request->input('nombre')),
            'apellido' => $this->normalizar($request->input('apellido')),
            'correo'   => $this->normalizar($request->input('correo')),
            'cedula'   => $this->normalizar($request->input('cedula')),
            'cargo'    => $this->normalizar($request->input('cargo')),
        ];

        if ($data['correo'] !== null) {
            $data['correo'] = strtolower($data['correo']);
        }
        if ($data['cedula'] !== null) {
            $data['cedula'] = preg_replace('/\D/', '', $data['cedula']);
        }

        $validated = validator($data, [
            'titulo'   => ['required', 'string', Rule::in(self::TITULOS)],
            'nombre'   => ['required', 'string', 'max:100'],
            'apellido' => ['required', 'string', 'max:100'],
            'correo'   => ['required', 'email', 'max:150', 'unique:responsables,correo'],
            'cedula'   => ['required', 'digits:10', 'unique:responsables,cedula'],
            'cargo'    => ['required', 'string', 'max:100'],
        ])->validate();

        if (!$this->cedulaEcuatorianaValida($validated['cedula'])) {
            return response()->json(['message' => 'La cédula ecuatoriana no es válida'], 422);
        }

        $responsable = Responsable::create($validated);

        if (function_exists('registrarMovimiento')) {
            registrarMovimiento(
                'Creación de responsable',
                "Se registró al responsable {$responsable->titulo} {$responsable->nombre} {$responsable->apellido} con cargo '{$responsable->cargo}'."
            );
        }

        return response()->json([
            'message' => 'Responsable agregado correctamente ',
            'data'    => $responsable
        ], 201);
    }

    public function show($id)
    {
        $responsable = Responsable::find($id);
        if (!$responsable) {
            return response()->json(['message' => 'Responsable no encontrado'], 404);
        }
        return response()->json($responsable, 200);
    }

    public function update(Request $request, $id)
    {
        $responsable = Responsable::find($id);
        if (!$responsable) {
            return response()->json(['message' => 'Responsable no encontrado'], 404);
        }

        $data = [];

        if ($request->has('titulo'))   $data['titulo'] = $this->normalizar($request->input('titulo'));
        if ($request->has('nombre'))   $data['nombre'] = $this->normalizar($request->input('nombre'));
        if ($request->has('apellido')) $data['apellido'] = $this->normalizar($request->input('apellido'));
        if ($request->has('cargo'))    $data['cargo'] = $this->normalizar($request->input('cargo'));

        if ($request->has('correo')) {
            $correo = $this->normalizar($request->input('correo'));
            $data['correo'] = $correo !== null ? strtolower($correo) : null;
        }

        if ($request->has('cedula')) {
            $cedula = $this->normalizar($request->input('cedula'));
            $data['cedula'] = $cedula !== null ? preg_replace('/\D/', '', $cedula) : null;
        }

        $validated = validator($data, [
            'titulo'   => ['sometimes', 'required', 'string', Rule::in(self::TITULOS)],
            'nombre'   => ['sometimes', 'required', 'string', 'max:100'],
            'apellido' => ['sometimes', 'required', 'string', 'max:100'],
            'correo'   => [
                'sometimes', 'required', 'email', 'max:150',
                Rule::unique('responsables', 'correo')->ignore($responsable->id)
            ],
            'cedula'   => [
                'sometimes', 'required', 'digits:10',
                Rule::unique('responsables', 'cedula')->ignore($responsable->id)
            ],
            'cargo'    => ['sometimes', 'required', 'string', 'max:100'],
        ])->validate();

        if (isset($validated['cedula']) && !$this->cedulaEcuatorianaValida($validated['cedula'])) {
            return response()->json(['message' => 'La cédula ecuatoriana no es válida'], 422);
        }

        $responsable->update($validated);

        if (function_exists('registrarMovimiento')) {
            registrarMovimiento(
                'Actualización de responsable',
                "Se actualizó al responsable {$responsable->titulo} {$responsable->nombre} {$responsable->apellido}."
            );
        }

        return response()->json([
            'message' => 'Responsable actualizado ',
            'data'    => $responsable
        ], 200);
    }
    public function destroy($id)
    {
        return response()->json([
            'message' => 'La eliminación de responsables está deshabilitada. Usa inactivar/activar mediante actas.'
        ], 409);
    }

    public function exportarWordSeleccionados(Request $request)
    {
        $data = $request->validate([
            'ids'   => 'required|array|min:1',
            'ids.*' => 'exists:responsables,id',
        ]);

        $ids = $data['ids'];

        $responsables = Responsable::whereIn('id', $ids)
            ->orderBy('cargo')
            ->orderBy('apellido')
            ->orderBy('nombre')
            ->get();

        if ($responsables->isEmpty()) {
            return response()->json(['message' => 'No se encontraron responsables'], 404);
        }

        $phpWord = new PhpWord();

        $section = $phpWord->addSection([
            'orientation' => 'portrait',
            'marginLeft'  => 900,
            'marginRight' => 900,
            'marginTop'   => 700,
            'marginBottom'=> 700,
        ]);

        $section->addText(
            'Listado de Responsables - EPESPO',
            ['bold' => true, 'size' => 14],
            ['alignment' => 'center', 'spaceAfter' => 250]
        );

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
            'size'  => 10,
        ];

        $rowTextStyle = ['size' => 9];

        $headers = ['Título', 'Nombre', 'Apellido', 'Correo', 'Cédula', 'Cargo'];
        $widths  = [1100, 1800, 1800, 2600, 1600, 2200];

        $table = $section->addTable($styleTable);

        $table->addRow();
        foreach ($headers as $i => $titulo) {
            $table->addCell($widths[$i], $headerCellStyle)->addText($titulo, $headerTextStyle);
        }

        foreach ($responsables as $r) {
            $table->addRow();

            $titulo = $r->titulo ? ($r->titulo . '.') : '-';

            $table->addCell($widths[0])->addText($titulo, $rowTextStyle);
            $table->addCell($widths[1])->addText($r->nombre ?? '', $rowTextStyle);
            $table->addCell($widths[2])->addText($r->apellido ?? '', $rowTextStyle);
            $table->addCell($widths[3])->addText($r->correo ?? '', $rowTextStyle);
            $table->addCell($widths[4])->addText($r->cedula ?? '', $rowTextStyle);
            $table->addCell($widths[5])->addText($r->cargo ?? '', $rowTextStyle);
        }

        $outputDir = storage_path('app/reportes');
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0775, true);
        }

        $filename = 'Responsables-EPESPO-' . now()->format('Ymd-His') . '.docx';
        $fullPath = $outputDir . '/' . $filename;

        $writer = IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($fullPath);

        if (function_exists('registrarMovimiento')) {
            registrarMovimiento(
                'Generación de reporte Word',
                'Se generó un documento Word con ' . $responsables->count() . ' responsables.'
            );
        }

        return response()->download($fullPath, $filename)->deleteFileAfterSend(true);
    }
}
