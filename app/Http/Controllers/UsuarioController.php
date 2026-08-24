<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use App\Models\User;
use App\Models\Funcionario;
use App\Models\Cargo;
use App\Models\Dependencia;

class UsuarioController extends Controller
{
    /**
     * Crear un usuario y funcionario asociado con su vinculación.
     */
    public function store(Request $request)
    {
        abort_unless(session('usuario_autenticado.rol_activo') === 'admin', 403);

        $data = $request->validate([
            'nombres' => ['required', 'string', 'max:255'],
            'apellidos' => ['required', 'string', 'max:255'],
            'tipo_documento' => ['required', 'string', 'max:50'],
            'numero_doc' => ['required', 'string', 'max:50'],
            'correo' => ['required', 'email', 'max:255'],
            'cargo' => ['required', 'string', 'max:255'],
            'area' => ['required', 'string', 'max:255'],
            'rol' => ['required', 'string', 'in:EVALUADOR,EVALUADO,ADMINISTRADOR,INSTANCIA_EXTERNA'],
            'sistema_evaluacion' => ['nullable', 'string', 'in:RENDIMIENTO_LABORAL,ACUERDO_GESTION'],
            'nivel_jerarquico' => ['nullable', 'string'],
            'codigo_cargo' => ['nullable', 'numeric'],
            'grado_cargo' => ['nullable', 'numeric'],
        ]);

        $defaultPassword = trim($data['numero_doc']);

        DB::beginTransaction();
        try {
            // Asegurar que el cargo exista en el catálogo maestro
            if (Schema::hasTable('cargo') && !DB::table('cargo')->where('nombre', $data['cargo'])->exists()) {
                DB::table('cargo')->insert([
                    'nombre' => $data['cargo'],
                    'codigo_cargo' => $data['codigo_cargo'] ?? 0,
                    'grado_cargo' => $data['grado_cargo'] ?? 0,
                    'nivel_jerarquico' => $data['nivel_jerarquico'] ?? 'PROFESIONAL',
                    'activo' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Asegurar que el área/dependencia exista en el catálogo maestro
            if (Schema::hasTable('dependencia') && !DB::table('dependencia')->where('nombre', $data['area'])->exists()) {
                DB::table('dependencia')->insert([
                    'nombre' => $data['area'],
                    'activa' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // 1. Crear o reactivar Usuario
            $user = User::where('username', $data['correo'])->first();
            $tempPassword = null;

            if (!$user) {
                $tempPassword = $defaultPassword;
                $user = User::create([
                    'username' => $data['correo'],
                    'password' => Hash::make($tempPassword),
                    'rol' => $data['rol'],
                    'activo' => 1
                ]);
            } else {
                $user->update([
                    'rol' => $data['rol'],
                    'activo' => 1
                ]);
            }

            // 2. Crear o actualizar Funcionario
            $funcionario = Funcionario::where('numero_doc', $data['numero_doc'])->first();
            if (!$funcionario) {
                $funcionario = new Funcionario();
                $funcionario->id_usuario = $user->id_usuario;
                $funcionario->tipo_documento = $data['tipo_documento'];
                $funcionario->numero_doc = $data['numero_doc'];
                $funcionario->nombres = $data['nombres'];
                $funcionario->apellidos = $data['apellidos'];
                $funcionario->correo_cargo = $data['correo'];
                $funcionario->save();
            } else {
                $funcionario->update([
                    'id_usuario' => $user->id_usuario,
                    'nombres' => $data['nombres'],
                    'apellidos' => $data['apellidos'],
                    'correo_cargo' => $data['correo'],
                ]);
            }

            // 3. Crear Vinculación (Cargo asignado)
            $sistema = $data['sistema_evaluacion'] ?? ($data['rol'] === 'ADMINISTRADOR' || str_contains(strtoupper($data['cargo']), 'DIRECT') ? 'ACUERDO_GESTION' : 'RENDIMIENTO_LABORAL');
            
            DB::table('vinculacion')->insert([
                'id_funcionario' => $funcionario->id_funcionario,
                'cargo' => $data['cargo'],
                'area' => $data['area'],
                'activa' => 1,
                'es_evaluador' => ($data['rol'] === 'EVALUADOR' || $data['rol'] === 'ADMINISTRADOR' ? 1 : 0),
                'sistema_evaluacion' => $sistema,
                'codigo_cargo' => $data['codigo_cargo'] ?? 0,
                'grado_cargo' => $data['grado_cargo'] ?? 0,
                'nivel_jerarquico' => $data['nivel_jerarquico'] ?? 'PROFESIONAL',
                'tipo_vinculacion' => 'INDEFINIDO',
                'fecha_ingreso' => date('Y-m-d'),
            ]);

            DB::commit();

            return back()->with([
                'success' => 'Funcionario y vinculación creados exitosamente.',
                'temp_password' => $tempPassword ?? $defaultPassword,
                'temp_password_user' => $user->id_usuario,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Error al crear usuario y cargo: ' . $e->getMessage()]);
        }
    }

    /**
     * Inhabilitar funcionario y sus vinculaciones para que no figuren en listados activos.
     */
    public function disable(Request $request, $id)
    {
        abort_unless(session('usuario_autenticado.rol_activo') === 'admin', 403);

        $funcionario = Funcionario::find($id);
        abort_unless($funcionario, 404);

        DB::beginTransaction();
        try {
            if ($funcionario->id_usuario) {
                DB::table('usuario')->where('id_usuario', $funcionario->id_usuario)->update(['activo' => 0]);
            }

            DB::table('vinculacion')->where('id_funcionario', $id)->update(['activa' => 0]);

            DB::commit();
            return back()->with('success', 'Empleado inhabilitado correctamente. Ya no aparecerá en los listados activos.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Error al inhabilitar empleado.']);
        }
    }

    /**
     * Habilitar o marcar vacancia en un cargo/vinculación.
     */
    public function vacancia(Request $request, $id)
    {
        abort_unless(session('usuario_autenticado.rol_activo') === 'admin', 403);

        $vinc = DB::table('vinculacion')->where('id_vinculacion', $id)->first();
        abort_unless($vinc, 404);

        $nuevoEstadoVacante = $request->boolean('es_vacante', true);

        DB::table('vinculacion')->where('id_vinculacion', $id)->update([
            'es_vacante' => $nuevoEstadoVacante ? 1 : 0,
            'activa' => $nuevoEstadoVacante ? 0 : 1,
        ]);

        $msg = $nuevoEstadoVacante 
            ? 'El cargo ha sido marcado como VACANTE y notificado al Administrador para su futura reasignación.'
            : 'La vacante ha sido cerrada.';

        return back()->with('success', $msg);
    }

    /**
     * Guardar nuevo cargo en el catálogo maestro.
     */
    public function storeCargo(Request $request)
    {
        abort_unless(session('usuario_autenticado.rol_activo') === 'admin', 403);

        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'codigo_cargo' => ['nullable', 'numeric'],
            'grado_cargo' => ['nullable', 'numeric'],
            'nivel_jerarquico' => ['required', 'string', 'in:DIRECTIVO,ASESOR,PROFESIONAL,TECNICO,ASISTENCIAL,DOCENTE'],
        ]);

        if (Schema::hasTable('cargo')) {
            $nombre = trim($data['nombre']);
            $existe = DB::table('cargo')->whereRaw('LOWER(nombre) = ?', [strtolower($nombre)])->first();
            if ($existe) {
                return back()->withErrors(['cargo' => 'Ya existe un cargo registrado con ese nombre.']);
            }

            DB::table('cargo')->insert([
                'nombre' => $nombre,
                'codigo_cargo' => $data['codigo_cargo'] ?? 0,
                'grado_cargo' => $data['grado_cargo'] ?? 0,
                'nivel_jerarquico' => $data['nivel_jerarquico'],
                'activo' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return back()->with('success', 'Cargo creado correctamente en el catálogo institucional.');
    }

    /**
     * Inhabilitar o reactivar cargo.
     */
    public function toggleCargo(Request $request, $id)
    {
        abort_unless(session('usuario_autenticado.rol_activo') === 'admin', 403);

        if (Schema::hasTable('cargo')) {
            $cargo = DB::table('cargo')->where('id_cargo', $id)->first();
            if ($cargo) {
                $nuevoActivo = $cargo->activo ? 0 : 1;
                DB::table('cargo')->where('id_cargo', $id)->update([
                    'activo' => $nuevoActivo,
                    'updated_at' => now(),
                ]);
                $msg = $nuevoActivo ? 'Cargo habilitado exitosamente.' : 'Cargo inhabilitado correctamente (no aparecerá en nuevos registros).';
                return back()->with('success', $msg);
            }
        }

        return back()->with('success', 'Estado del cargo actualizado.');
    }

    /**
     * Guardar nueva dependencia/área en el catálogo maestro.
     */
    public function storeDependencia(Request $request)
    {
        abort_unless(session('usuario_autenticado.rol_activo') === 'admin', 403);

        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
        ]);

        if (Schema::hasTable('dependencia')) {
            $nombre = trim($data['nombre']);
            $existe = DB::table('dependencia')->whereRaw('LOWER(nombre) = ?', [strtolower($nombre)])->first();
            if ($existe) {
                return back()->withErrors(['dependencia' => 'Ya existe un área/dependencia con ese nombre.']);
            }

            DB::table('dependencia')->insert([
                'nombre' => $nombre,
                'activa' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return back()->with('success', 'Área/Dependencia registrada exitosamente.');
    }

    /**
     * Inhabilitar o reactivar dependencia.
     */
    public function toggleDependencia(Request $request, $id)
    {
        abort_unless(session('usuario_autenticado.rol_activo') === 'admin', 403);

        if (Schema::hasTable('dependencia')) {
            $dep = DB::table('dependencia')->where('id_dependencia', $id)->first();
            if ($dep) {
                $nuevaActiva = $dep->activa ? 0 : 1;
                DB::table('dependencia')->where('id_dependencia', $id)->update([
                    'activa' => $nuevaActiva,
                    'updated_at' => now(),
                ]);
                $msg = $nuevaActiva ? 'Área habilitada exitosamente.' : 'Área inhabilitada correctamente.';
                return back()->with('success', $msg);
            }
        }

        return back()->with('success', 'Estado de la dependencia actualizado.');
    }
}
