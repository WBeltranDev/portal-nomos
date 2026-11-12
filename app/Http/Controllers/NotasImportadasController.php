<?php

namespace App\Http\Controllers;

use App\Services\NotasImportadas;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Notas por contingencia: 1) TH descarga el formato; 2) lo sube y revisa fila
 * por fila (nada se guarda); 3) guarda el lote; 4) puede revertirlo.
 */
class NotasImportadasController extends Controller
{
    private function soloAdmin(): void
    {
        abort_unless(session('usuario_autenticado.rol_activo') === 'admin', 403);
    }

    private function rutaArchivo(string $token): string
    {
        abort_unless(preg_match('/^[a-f0-9]{64}$/', $token), 422, 'Archivo no válido.');

        return storage_path("app/notas_importadas/{$token}.xlsx");
    }

    private function periodo(Request $request): array
    {
        $d = $request->validate(['anio' => ['required', 'integer', 'min:2000', 'max:2100'], 'semestre' => ['required', 'integer', 'in:1,2']]);

        return [(int) $d['anio'], (int) $d['semestre']];
    }

    public function plantilla(Request $request, NotasImportadas $notas)
    {
        $this->soloAdmin();
        [$anio, $semestre] = $this->periodo($request);

        $ruta = tempnam(sys_get_temp_dir(), 'notas') . '.xlsx';
        $notas->plantilla($ruta, $anio, $semestre);

        return response()->download($ruta, "Formato_notas_{$anio}_semestre_{$semestre}.xlsx")->deleteFileAfterSend();
    }

    public function analizar(Request $request, NotasImportadas $notas)
    {
        $this->soloAdmin();
        [$anio, $semestre] = $this->periodo($request);
        $request->validate(['archivo' => ['required', 'file', 'max:10240', 'extensions:xlsx']], ['archivo.extensions' => 'El archivo debe ser un Excel .xlsx.']);

        $archivo = $request->file('archivo');
        $token = hash_file('sha256', $archivo->getRealPath());
        $ruta = $this->rutaArchivo($token);
        if (! is_dir(dirname($ruta))) {
            mkdir(dirname($ruta), 0775, true);
        }
        copy($archivo->getRealPath(), $ruta);

        try {
            $analisis = $notas->analizar($ruta, $anio, $semestre);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['token' => $token, 'archivo' => $archivo->getClientOriginalName(), 'anio' => $anio, 'semestre' => $semestre] + $analisis);
    }

    public function aplicar(Request $request, NotasImportadas $notas)
    {
        $this->soloAdmin();
        [$anio, $semestre] = $this->periodo($request);
        $data = $request->validate(['token' => ['required', 'string'], 'archivo' => ['nullable', 'string', 'max:255'], 'descripcion' => ['nullable', 'string', 'max:300']]);

        $ruta = $this->rutaArchivo($data['token']);
        abort_unless(is_file($ruta), 404, 'El archivo de la revisión ya no está; vuelve a subirlo.');

        try {
            $analisis = $notas->analizar($ruta, $anio, $semestre);
            if ($analisis['resumen']['con_error'] > 0) {
                return response()->json(['message' => 'El archivo tiene filas con errores: corrígelas y vuelve a subirlo.'], 422);
            }
            $idLote = $notas->aplicar($analisis['filas'], $anio, $semestre, $data['descripcion'] ?? null, $data['archivo'] ?? null);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $r = $analisis['resumen'];

        return response()->json(['success' => true, 'id_lote' => $idLote, 'message' => "Lote #{$idLote} guardado: {$r['listas']} nota(s) cargada(s)" . ($r['omitidas'] ? ", {$r['omitidas']} omitida(s)." : '.')]);
    }

    public function index(NotasImportadas $notas)
    {
        $this->soloAdmin();

        return response()->json($notas->lotes());
    }

    public function revertir(int $id, NotasImportadas $notas)
    {
        $this->soloAdmin();
        if ($error = $notas->revertir($id)) {
            return response()->json(['message' => $error], 422);
        }

        return response()->json(['success' => true, 'message' => "Lote #{$id} revertido: sus notas dejaron de contar."]);
    }
}
