<?php

namespace App\Http\Controllers;

use App\Services\CargaPlanta;
use App\Services\OcupacionPuestos;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use RuntimeException;

/**
 * S14 - Carga de la planta oficial al catálogo de plazas, en cinco pasos:
 * 1-2) TH sube el Excel y ve el ensayo (nada se guarda); 3) aplica la carga;
 * 4) resuelve los pendientes en el panel; 5) valida y descarga el catálogo
 * oficial (PDF o Excel).
 */
class CargaPlantaController extends Controller
{
    private function soloAdmin(): void
    {
        abort_unless(session('usuario_autenticado.rol_activo') === 'admin', 403);
    }

    /** Ruta privada del archivo subido; el token es su huella SHA-256. */
    private function rutaArchivo(string $token): string
    {
        abort_unless(preg_match('/^[a-f0-9]{64}$/', $token), 422, 'Archivo no válido.');

        return storage_path("app/cargas_planta/{$token}.xlsx");
    }

    private function sinInternos(array $analisis): array
    {
        return array_diff_key($analisis, ['_personas' => 1, '_vacantes' => 1]);
    }

    /** Pasos 1-2: guarda el archivo y devuelve el ensayo sin escribir en la base. */
    public function analizar(Request $request, CargaPlanta $carga)
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
            $analisis = $carga->analizar($carga->leer($ruta));
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['token' => $token, 'archivo' => $archivo->getClientOriginalName()] + $this->sinInternos($analisis));
    }

    /** Paso 3: aplica la carga ya revisada en el ensayo. */
    public function aplicar(Request $request, CargaPlanta $carga)
    {
        $this->soloAdmin();
        $data = $request->validate(['token' => ['required', 'string'], 'archivo' => ['required', 'string', 'max:255']]);
        $ruta = $this->rutaArchivo($data['token']);
        abort_unless(is_file($ruta), 404, 'El archivo del ensayo ya no está; vuelve a subirlo.');

        try {
            $resultado = $carga->aplicar($ruta, $data['archivo'], session('usuario_autenticado.id_usuario'));
        } catch (RuntimeException $e) {
            return response()->json(['message' => 'No se aplicó la carga: ' . $e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'message' => 'Carga de planta aplicada. Revisa los pendientes y valida las plazas.'] + $resultado);
    }

    /** Paso 4: pendientes de las cargas, con lo necesario para resolverlos. */
    public function pendientes()
    {
        $this->soloAdmin();

        return response()->json([
            'pendientes' => DB::table('carga_planta_pendiente')->where('estado', 'PENDIENTE')->orderBy('id_pendiente')->get()
                ->map(fn ($p) => tap($p, fn ($p) => $p->datos = json_decode($p->datos, true))),
            'dependencias' => DB::table('dependencia')->where('activa', 1)->orderBy('nombre')->get(['id_dependencia', 'nombre']),
            'ultima_carga' => DB::table('carga_planta as c')->leftJoin('usuario as u', 'u.id_usuario', '=', 'c.id_usuario')
                ->orderByDesc('c.id_carga')->first(['c.id_carga', 'c.archivo', 'c.created_at', 'c.resumen', 'u.username']),
        ]);
    }

    public function resolverPendiente(Request $request, int $id, CargaPlanta $carga, OcupacionPuestos $ocupaciones)
    {
        $this->soloAdmin();
        $pendiente = DB::table('carga_planta_pendiente')->where('id_pendiente', $id)->where('estado', 'PENDIENTE')->first();
        abort_unless($pendiente, 404, 'El pendiente no existe o ya fue resuelto.');

        $data = $request->validate([
            'accion' => ['required', 'in:crear_vacante,asignar_plaza,resuelto,descartar'],
            'id_dependencia' => ['required_if:accion,crear_vacante', 'nullable', 'integer', 'exists:dependencia,id_dependencia'],
            'id_puesto' => ['required_if:accion,asignar_plaza', 'nullable', 'integer', 'exists:puesto,id_puesto'],
        ]);
        $datos = json_decode($pendiente->datos, true) ?: [];

        try {
            DB::transaction(function () use ($data, $pendiente, $datos, $carga, $ocupaciones) {
                if ($data['accion'] === 'crear_vacante') {
                    abort_unless($pendiente->tipo === 'VACANTE_SIN_DEPENDENCIA', 422, 'Este pendiente no es una vacante.');
                    $carga->crearVacante($datos, (int) $data['id_dependencia']);
                }
                if ($data['accion'] === 'asignar_plaza') {
                    abort_unless(! empty($datos['id_vinculacion']), 422, 'Este pendiente no tiene una vinculación a la cual asignar plaza.');
                    $ocupaciones->cerrar((int) $datos['id_vinculacion'], now()->toDateString(), 'Asignación desde pendientes de carga');
                    if ($error = $ocupaciones->ocupar((int) $data['id_puesto'], (int) $datos['id_vinculacion'], now()->toDateString(), 'Asignación desde pendientes de carga')) {
                        throw new RuntimeException($error);
                    }
                }

                DB::table('carga_planta_pendiente')->where('id_pendiente', $pendiente->id_pendiente)->update([
                    'estado' => $data['accion'] === 'descartar' ? 'DESCARTADO' : 'RESUELTO',
                    'resuelto_por' => session('usuario_autenticado.id_usuario'),
                    'resuelto_at' => now(),
                    'updated_at' => now(),
                ]);
            });
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'message' => 'Pendiente actualizado.']);
    }

    /** Paso 5: catálogo oficial de plazas, en PDF o Excel. */
    public function documento(Request $request)
    {
        $this->soloAdmin();
        $formato = $request->query('formato') === 'xlsx' ? 'xlsx' : 'pdf';

        $plazas = DB::table('puesto as p')
            ->join('dependencia as d', 'd.id_dependencia', '=', 'p.id_dependencia')
            ->join('cargo as c', 'c.id_cargo', '=', 'p.id_cargo')
            ->leftJoin('puesto as ps', 'ps.id_puesto', '=', 'p.id_puesto_superior')
            ->leftJoin('puesto_ocupacion as po', fn ($j) => $j->on('po.id_puesto', '=', 'p.id_puesto')->where('po.estado', '=', 'VIGENTE'))
            ->leftJoin('vinculacion as v', 'v.id_vinculacion', '=', 'po.id_vinculacion')
            ->leftJoin('funcionario as f', 'f.id_funcionario', '=', 'v.id_funcionario')
            ->where('p.estado', '!=', 'INACTIVA')
            ->orderBy('d.nombre')->orderBy('p.codigo_plaza')
            ->get(['p.codigo_plaza', 'p.correo_cargo', 'p.codigo_cargo', 'p.grado', 'p.nivel_jerarquico', 'p.sistema_evaluacion', 'p.estado',
                'd.nombre as dependencia', 'c.nombre as cargo', 'ps.codigo_plaza as superior', 'f.numero_doc',
                \Illuminate\Support\Facades\DB::raw("TRIM(CONCAT(COALESCE(f.nombres, ''), ' ', COALESCE(f.apellidos, ''))) as ocupante"),
                'po.tipo_ocupacion', 'po.vigente_desde']);

        $ultimaCarga = DB::table('carga_planta')->orderByDesc('id_carga')->first(['archivo', 'created_at']);
        $info = [
            'plazas' => $plazas,
            'total' => $plazas->count(),
            'ocupadas' => $plazas->filter(fn ($p) => $p->ocupante !== '')->count(),
            'validadas' => $plazas->where('estado', 'VALIDADA')->count(),
            'fuente' => $ultimaCarga ? "{$ultimaCarga->archivo} (cargada el " . \Carbon\Carbon::parse($ultimaCarga->created_at)->format('d/m/Y') . ')' : 'Catálogo de la plataforma',
            'escudo' => 'data:image/png;base64,' . base64_encode(file_get_contents(public_path('escudo-color.png'))),
            'logo' => 'data:image/png;base64,' . base64_encode(file_get_contents(public_path('logo.png'))),
        ];
        $nombre = 'Catalogo_Oficial_Plazas_' . now('America/Bogota')->format('Y-m-d');

        if ($formato === 'pdf') {
            return descargarInformePdf('reportes.catalogo-plazas', $info, "{$nombre}.pdf", 'landscape');
        }

        $ruta = tempnam(sys_get_temp_dir(), 'plazas') . '.xlsx';
        $writer = new Writer();
        $writer->openToFile($ruta);
        $writer->addRow(Row::fromValues(['Código de plaza', 'Dependencia', 'Cargo', 'Código', 'Grado', 'Nivel', 'Sistema de evaluación',
            'Estado de la plaza', 'Ocupante', 'Cédula', 'Tipo de vinculación', 'Desde', 'Plaza superior', 'Correo del cargo', 'Validación']));
        foreach ($plazas as $p) {
            $writer->addRow(Row::fromValues([$p->codigo_plaza, $p->dependencia, $p->cargo, $p->codigo_cargo, $p->grado, $p->nivel_jerarquico,
                $p->sistema_evaluacion, $p->ocupante !== '' ? 'Ocupada' : 'Vacante', $p->ocupante, $p->numero_doc, $p->tipo_ocupacion,
                $p->vigente_desde, $p->superior, $p->correo_cargo, $p->estado === 'VALIDADA' ? 'Validada' : 'Borrador']));
        }
        $writer->close();

        return response()->download($ruta, "{$nombre}.xlsx")->deleteFileAfterSend();
    }
}
