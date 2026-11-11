<?php

namespace App\Http\Controllers;

use App\Services\TrasladoLote;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Traslados en bloque (rotación): 1) TH descarga el formato; 2) lo sube y ve la
 * revisión fila por fila (nada se guarda); 3) programa el lote; 4) cuando todos
 * los tramos están calificados, lo confirma, y se mueve a todos a la vez.
 */
class TrasladoLoteController extends Controller
{
    private function soloAdmin(): void
    {
        abort_unless(session('usuario_autenticado.rol_activo') === 'admin', 403);
    }

    private function rutaArchivo(string $token): string
    {
        abort_unless(preg_match('/^[a-f0-9]{64}$/', $token), 422, 'Archivo no válido.');

        return storage_path("app/traslados_lote/{$token}.xlsx");
    }

    public function plantilla(Request $request, TrasladoLote $lote)
    {
        $this->soloAdmin();
        $cargo = trim((string) $request->query('cargo', '')) ?: null;

        $ruta = tempnam(sys_get_temp_dir(), 'lote') . '.xlsx';
        $lote->plantilla($ruta, $cargo);

        return response()->download($ruta, 'Formato_traslados_en_bloque_' . now('America/Bogota')->format('Y-m-d') . '.xlsx')->deleteFileAfterSend();
    }

    /** Paso 2: guarda el archivo y devuelve la revisión fila por fila. */
    public function analizar(Request $request, TrasladoLote $lote)
    {
        $this->soloAdmin();
        $request->validate(['archivo' => ['required', 'file', 'max:10240', 'extensions:xlsx']], [
            'archivo.extensions' => 'El archivo debe ser un Excel .xlsx.',
        ]);

        $archivo = $request->file('archivo');
        $token = hash_file('sha256', $archivo->getRealPath());
        $ruta = $this->rutaArchivo($token);
        if (! is_dir(dirname($ruta))) {
            mkdir(dirname($ruta), 0775, true);
        }
        copy($archivo->getRealPath(), $ruta);

        try {
            $analisis = $lote->analizar($ruta);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['token' => $token, 'archivo' => $archivo->getClientOriginalName()] + $analisis);
    }

    /** Paso 3: programa el lote ya revisado (todos o ninguno). */
    public function programar(Request $request, TrasladoLote $lote)
    {
        $this->soloAdmin();
        $data = $request->validate([
            'token' => ['required', 'string'],
            'archivo' => ['nullable', 'string', 'max:255'],
            'fecha_traslado' => ['required', 'date', 'after_or_equal:today'],
            'descripcion' => ['nullable', 'string', 'max:300'],
        ], [
            'fecha_traslado.after_or_equal' => 'La fecha del traslado no puede ser anterior a la fecha actual.',
        ]);

        $ruta = $this->rutaArchivo($data['token']);
        abort_unless(is_file($ruta), 404, 'El archivo de la revisión ya no está; vuelve a subirlo.');

        try {
            $analisis = $lote->analizar($ruta);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        if ($analisis['resumen']['con_error'] > 0) {
            return response()->json(['message' => 'El archivo tiene filas con errores: corrígelas y vuelve a subirlo.', 'filas' => $analisis['filas']], 422);
        }

        $resultado = $lote->programar($analisis['filas'], $data['fecha_traslado'], $data['descripcion'] ?? null, $data['archivo'] ?? null);
        if (isset($resultado['errores'])) {
            return response()->json(['message' => 'No se programó el lote: ' . count($resultado['errores']) . ' fila(s) no pasaron la validación del traslado.', 'errores' => $resultado['errores']], 422);
        }

        return response()->json(['success' => true, 'id_lote' => $resultado['id_lote'], 'message' => "Lote #{$resultado['id_lote']} programado con {$analisis['resumen']['total']} traslado(s). Se confirma cuando todos los tramos estén calificados."]);
    }

    public function index(TrasladoLote $lote)
    {
        $this->soloAdmin();

        return response()->json($lote->lotes());
    }

    public function confirmar(int $id, TrasladoLote $lote)
    {
        $this->soloAdmin();
        if ($error = $lote->confirmar($id)) {
            return response()->json(['message' => $error], 422);
        }

        return response()->json(['success' => true, 'message' => "Lote #{$id} confirmado: todos los traslados quedaron registrados y las evaluaciones abiertas en los cargos nuevos."]);
    }

    public function cancelar(int $id, TrasladoLote $lote)
    {
        $this->soloAdmin();
        if ($error = $lote->cancelar($id)) {
            return response()->json(['message' => $error], 422);
        }

        return response()->json(['success' => true, 'message' => "Lote #{$id} cancelado. Nadie se movió."]);
    }
}
