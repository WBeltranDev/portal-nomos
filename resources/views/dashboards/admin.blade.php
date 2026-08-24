@extends('layouts.app')

@section('content')
@include('partials.dashboard-styles')

<div class="panel-shell">
    @include('partials.header')

    <div class="flex min-h-0 overflow-hidden">
        @include('partials.sidebar')

        <main class="flex-1 min-w-0 p-4 sm:p-6 lg:p-8 overflow-y-auto">
            @include('partials.alerts')

            <!-- SECTION: USUARIOS (Admin Only) -->
            <section id="section-usuarios" class="section-content space-y-6">
                <div class="panel-card rounded-3xl p-6">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-[0.22em] text-[#00594E]">Usuarios</p>
                            <h1 class="text-2xl sm:text-3xl font-black text-slate-900">Usuarios de la plataforma</h1>
                            <p class="text-sm text-slate-500 mt-1">Datos de acceso, contraseñas y roles institucionales.</p>
                        </div>
                        <div class="text-sm text-slate-500">Total: <span class="font-bold text-slate-900">{{ $usuarios->count() }}</span></div>
                    </div>
                    <div class="mt-4">
                        <input id="buscador-usuarios" oninput="filtrarUsuarios()" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm shadow-sm outline-none focus:border-[#00594E] focus:ring-2 focus:ring-[#00594E]/10" type="text" placeholder="Buscar por documento, nombre o correo">
                    </div>
                    <div class="mt-6 grid gap-4 lg:grid-cols-2 xl:grid-cols-3">
                        @foreach ($usuarios as $u)
                            <article class="usuario-card rounded-2xl border border-slate-200 bg-white p-4 shadow-sm" data-nombre="{{ strtolower(($u->nombres ?? 'Usuario') . ' ' . ($u->apellidos ?? 'Admin')) }}" data-cedula="{{ strtolower($u->documento_identidad ?? '') }}" data-correo="{{ strtolower($u->correo_institucional ?? '') }}">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <h3 class="font-bold text-slate-900">{{ $u->nombres ?? 'Usuario' }} {{ $u->apellidos ?? 'Admin' }}</h3>
                                        <p class="text-sm text-slate-500">{{ $u->correo_institucional }}</p>
                                    </div>
                                    <span class="text-[10px] font-bold uppercase rounded-full px-2.5 py-1 bg-[#EAF2EF] text-[#00594E]">{{ $u->rol }}</span>
                                </div>
                                <div class="mt-4 grid grid-cols-2 gap-2 text-xs">
                                    <div class="rounded-xl bg-slate-50 p-3"><p class="text-slate-400 uppercase font-bold text-[10px]">ID</p><p class="font-semibold text-slate-800">#{{ $u->id_usuario }}</p></div>
                                    <div class="rounded-xl bg-slate-50 p-3"><p class="text-slate-400 uppercase font-bold text-[10px]">Documento</p><p class="font-semibold text-slate-800">{{ $u->documento_identidad ?? '-' }}</p></div>
                                </div>
                                <div class="mt-4 flex gap-2">
                                    <form method="POST" action="{{ route('usuarios.reset-password', $u->id_usuario) }}" class="flex-1">
                                        @csrf
                                        <button class="w-full rounded-xl bg-slate-900 text-white py-2 text-xs font-bold hover:bg-slate-800 transition" type="submit">Generar contraseña temporal</button>
                                    </form>
                                    @if($u->documento_identidad)
                                    <button type="button" onclick="navigator.clipboard.writeText('{{ $u->documento_identidad }}').then(() => alert('Contraseña inicial (Cédula: {{ $u->documento_identidad }}) copiada al portapapeles'))" class="px-3 py-2 bg-[#EAF2EF] hover:bg-[#d5e7e1] text-[#00594E] rounded-xl text-xs font-bold transition flex items-center gap-1" title="Copiar contraseña inicial (cédula)">
                                        <span class="material-symbols-outlined text-sm">content_copy</span>
                                    </button>
                                    @endif
                                </div>
                            </article>
                        @endforeach
                    </div>
                </div>
            </section>

            <!-- SECTION: EMPLEADOS / FUNCIONARIOS (Admin Only) -->
            <section id="section-empleados" class="section-content hidden space-y-6">
                <div class="grid gap-6 lg:grid-cols-[1.3fr_0.7fr]">
                    <div class="panel-card rounded-3xl p-6">
                        <div class="flex justify-between items-center mb-6">
                            <div>
                                <p class="text-xs font-bold uppercase tracking-[0.22em] text-[#00594E]">Maestro</p>
                                <h2 class="text-2xl sm:text-3xl font-black text-slate-900">Funcionarios</h2>
                            </div>
                        </div>

                        <input id="buscador-empleados" oninput="filtrarEmpleados()" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm shadow-sm outline-none focus:border-[#00594E] focus:ring-2 focus:ring-[#00594E]/10" type="text" placeholder="Buscar por documento, nombre o correo">

                        <div class="mt-4 grid gap-4 md:grid-cols-2">
                            @foreach ($empleados as $e)
                                <article
                                    class="empleado-card rounded-2xl border border-slate-200 bg-white p-4 shadow-sm cursor-pointer transition hover:border-[#00594E]"
                                    data-nombre="{{ strtolower($e->nombres . ' ' . $e->apellidos) }}"
                                    data-cedula="{{ strtolower($e->documento_identidad) }}"
                                    data-correo="{{ strtolower($e->correo_institucional ?? '') }}"
                                    data-cargo="{{ e($e->nombre_cargo ?? 'Sin cargo') }}"
                                    data-area="{{ e($e->nombre_area ?? 'Sin Área') }}"
                                    data-estado="{{ $e->activo ? ($e->es_vacante ? 'Vacante' : 'Activo') : 'Inactivo' }}"
                                    onclick="seleccionarEmpleado(this, @js($e))">
                                    <div class="flex items-center justify-between gap-4">
                                        <div>
                                            <h3 class="text-base font-black text-slate-900 leading-snug">{{ $e->nombres }} {{ $e->apellidos }}</h3>
                                            <p class="text-xs text-slate-500">{{ $e->nombre_cargo ?? 'Sin cargo' }}</p>
                                        </div>
                                        <span class="w-8 h-8 rounded-full flex items-center justify-center {{ $e->es_vacante ? 'bg-amber-600' : ($e->activo ? 'bg-[#00594E]' : 'bg-slate-400') }} text-white text-xs font-bold">{{ strtoupper(substr($e->nombres, 0, 1) . substr($e->apellidos, 0, 1)) }}</span>
                                    </div>
                                    <div class="mt-3 space-y-1 text-xs text-slate-600">
                                        <p><span class="font-bold">Doc:</span> {{ $e->documento_identidad }}</p>
                                        <p><span class="font-bold">Área:</span> {{ $e->nombre_area ?? 'Sin Área' }}</p>
                                        @if($e->es_vacante)
                                        <span class="inline-block px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800">Puesto Vacante</span>
                                        @endif
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    </div>

                    <aside class="space-y-6">
                        <!-- Ficha de Empleado -->
                        <div class="panel-card rounded-3xl p-6">
                            <p class="text-xs font-bold uppercase tracking-[0.22em] text-[#00594E] mb-4">Detalle del funcionario</p>
                            <div class="flex items-start gap-3 pb-4 border-b border-slate-100">
                                <div id="empleado-avatar" class="w-14 h-14 rounded-2xl bg-[#00594E] flex items-center justify-center text-white text-lg font-black shadow-md">--</div>
                                <div class="min-w-0">
                                    <h3 id="empleado-nombre" class="text-lg font-black text-slate-900 leading-tight">Selecciona uno</h3>
                                    <p id="empleado-cargo" class="text-xs text-slate-500 mt-0.5">Verás sus datos ampliados</p>
                                </div>
                            </div>
                            <div class="mt-4 space-y-2 text-xs">
                                <div class="flex justify-between py-2 border-b border-slate-50"><span class="text-slate-500">Correo</span><span id="empleado-correo" class="text-slate-800 font-medium">-</span></div>
                                <div class="flex justify-between py-2 border-b border-slate-50"><span class="text-slate-500">Documento</span><span id="empleado-documento" class="text-slate-800 font-medium">-</span></div>
                                <div class="flex justify-between py-2 border-b border-slate-50"><span class="text-slate-500">Área</span><span id="empleado-area" class="text-slate-800 font-medium">-</span></div>
                                <div class="flex justify-between py-2"><span class="text-slate-500">Estado</span><span id="empleado-estado" class="text-slate-800 font-medium">-</span></div>
                            </div>
                            <div class="mt-4 pt-4 border-t border-slate-100 space-y-2 hidden" id="bloque-acciones-empleado">
                                <form method="POST" id="form-inhabilitar-empleado">
                                    @csrf
                                    <button type="submit" class="w-full bg-red-600 hover:bg-red-700 text-white rounded-xl py-2 text-xs font-bold transition">Inhabilitar Funcionario</button>
                                </form>
                                <form method="POST" id="form-vacancia-empleado">
                                    @csrf
                                    <input type="hidden" name="es_vacante" id="vacancia-valor" value="1">
                                    <button type="submit" id="btn-toggle-vacancia" class="w-full bg-amber-600 hover:bg-amber-700 text-white rounded-xl py-2 text-xs font-bold transition">Declarar Vacante en este Cargo</button>
                                </form>
                            </div>
                        </div>

                        <!-- Crear Usuario / Cargo Manualmente con Desplegables -->
                        <div class="panel-card rounded-3xl p-6">
                            <h3 class="text-lg font-bold text-slate-800 mb-1">Registrar Nuevo Funcionario</h3>
                            <p class="text-xs text-slate-500 mb-4">Selecciona el cargo y dependencia desde el catálogo institucional.</p>
                            <form method="POST" action="{{ route('admin.usuarios.store') }}" class="space-y-3">
                                @csrf
                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-600 uppercase">Nombres</label>
                                        <input type="text" name="nombres" class="w-full text-xs rounded-xl border border-slate-200 p-2 bg-white" required>
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-600 uppercase">Apellidos</label>
                                        <input type="text" name="apellidos" class="w-full text-xs rounded-xl border border-slate-200 p-2 bg-white" required>
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-600 uppercase">Tipo Doc.</label>
                                        <select name="tipo_documento" class="w-full text-xs rounded-xl border border-slate-200 p-2 bg-white">
                                            <option value="CEDULA_CIUDADANIA">CC</option>
                                            <option value="CEDULA_EXTRANJERIA">CE</option>
                                            <option value="PASAPORTE">Pasaporte</option>
                                            <option value="TARJETA_IDENTIDAD">TI</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-600 uppercase">Número</label>
                                        <input type="text" name="numero_doc" class="w-full text-xs rounded-xl border border-slate-200 p-2 bg-white" required>
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-600 uppercase">Correo Inst.</label>
                                    <input type="email" name="correo" class="w-full text-xs rounded-xl border border-slate-200 p-2 bg-white" required>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-600 uppercase mb-1">Cargo Institucional</label>
                                    <select name="cargo" class="w-full text-xs rounded-xl border border-slate-200 p-2 bg-white" required>
                                        <option value="">-- Seleccionar Cargo --</option>
                                        @foreach($cargosCatalogo as $cg)
                                            @if($cg->activo ?? true)
                                                <option value="{{ $cg->nombre }}">{{ $cg->nombre }} ({{ $cg->nivel_jerarquico ?? 'PROFESIONAL' }})</option>
                                            @endif
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-600 uppercase mb-1">Área / Dependencia</label>
                                    <select name="area" class="w-full text-xs rounded-xl border border-slate-200 p-2 bg-white" required>
                                        <option value="">-- Seleccionar Dependencia --</option>
                                        @foreach($dependenciasCatalogo as $dp)
                                            @if($dp->activa ?? true)
                                                <option value="{{ $dp->nombre }}">{{ $dp->nombre }}</option>
                                            @endif
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-600 uppercase">Rol en el Sistema</label>
                                    <select name="rol" class="w-full text-xs rounded-xl border border-slate-200 p-2 bg-white">
                                        <option value="EVALUADO">Evaluado (Solo recibe)</option>
                                        <option value="EVALUADOR">Evaluador (Califica a otros)</option>
                                        <option value="ADMINISTRADOR">Talento Humano (Admin)</option>
                                        <option value="INSTANCIA_EXTERNA">Instancia Externa</option>
                                    </select>
                                </div>
                                <button type="submit" class="w-full bg-[#B5A160] text-white rounded-xl py-2.5 text-xs font-bold hover:brightness-110 transition shadow-md">Guardar Funcionario</button>
                            </form>
                        </div>

                        <!-- Asignación de evaluados a evaluador -->
                        <div class="panel-card rounded-3xl p-6">
                            <h3 class="text-lg font-bold text-slate-800 mb-2">Asignar personas a evaluar</h3>
                            <p class="text-xs text-slate-500 mb-4">Selecciona primero el evaluador y, posteriormente, las personas que deberá evaluar.</p>
                            <form method="POST" action="{{ route('admin.asignaciones.store') }}" class="space-y-3">
                                @csrf
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-600 uppercase mb-1">Evaluador (Vinculación)</label>
                                    <input type="search" id="buscar-evaluador-asignacion" oninput="filtrarOpcionesAsignacion('buscar-evaluador-asignacion', 'select-evaluador-asignacion')" class="mb-2 w-full text-xs rounded-xl border border-slate-200 p-2.5 bg-white outline-none focus:border-[#00594E]" placeholder="Buscar evaluador por nombre o cargo" />
                                    <select name="id_vinc_evaluador" id="select-evaluador-asignacion" class="w-full text-xs rounded-xl border border-slate-200 p-2.5 bg-white" required>
                                        <option value="">Selecciona un evaluador</option>
                                        @foreach($empleados as $e)
                                            @if($e->id_vinculacion && $e->es_evaluador && $e->activo)
                                                <option value="{{ $e->id_vinculacion }}">{{ $e->nombres }} {{ $e->apellidos }} - {{ $e->nombre_cargo }}</option>
                                            @endif
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <div class="flex items-center justify-between mb-1">
                                        <label class="block text-[10px] font-bold text-slate-600 uppercase">Personas a evaluar</label>
                                        <span id="contador-asignacion" class="text-[10px] font-bold rounded-full px-2.5 py-1 bg-[#EAF2EF] text-[#00594E]">0 seleccionadas</span>
                                    </div>
                                    <input type="search" id="buscar-evaluado-asignacion" oninput="filtrarCheckboxAsignacion()" class="mb-1.5 w-full text-xs rounded-xl border border-slate-200 p-2 bg-white outline-none focus:border-[#00594E]" placeholder="Buscar persona por nombre o cargo" />
                                    <div id="lista-evaluados-asignacion" class="h-32 max-w-xs w-full overflow-y-auto rounded-xl border border-slate-200 divide-y divide-slate-100 bg-white">
                                        @foreach($empleados as $e)
                                            @if($e->id_vinculacion && $e->activo)
                                                <label class="checkbox-evaluado flex items-center gap-2.5 px-3 py-1.5 text-xs text-slate-700 hover:bg-slate-50 cursor-pointer" hidden data-buscar="{{ strtolower($e->nombres . ' ' . $e->apellidos . ' ' . ($e->nombre_cargo ?? '')) }}">
                                                    <input type="checkbox" name="id_vinc_evaluado[]" value="{{ $e->id_vinculacion }}" onchange="contarAsignados()" class="shrink-0 rounded border-slate-300 text-[#00594E] focus:ring-[#00594E]" />
                                                    <span class="min-w-0">
                                                        <span class="block font-semibold leading-tight">{{ $e->nombres }} {{ $e->apellidos }}</span>
                                                        <span class="block text-slate-400 text-[10px] truncate">{{ $e->nombre_cargo }}</span>
                                                    </span>
                                                </label>
                                            @endif
                                        @endforeach
                                    </div>
                                </div>
                                <button type="submit" class="w-full bg-[#B5A160] text-white rounded-xl py-2.5 text-xs font-bold hover:brightness-110 transition shadow-md shadow-[#B5A160]/20">Asignar evaluador</button>
                            </form>
                        </div>
                    </aside>
                </div>
            </section>

            <!-- SECTION: CARGOS Y DEPENDENCIAS (Admin Only - S9) -->
            <section id="section-cargos-dependencias" class="section-content hidden space-y-6">
                <div class="grid gap-6 lg:grid-cols-2">
                    <!-- Catálogo de Cargos -->
                    <div class="panel-card rounded-3xl p-6 space-y-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-bold uppercase tracking-[0.22em] text-[#00594E]">Catálogo Maestro</p>
                                <h2 class="text-2xl font-black text-slate-900">Cargos</h2>
                            </div>
                        </div>

                        <!-- Formulario Crear Cargo -->
                        <form method="POST" action="{{ route('admin.cargos.store') }}" class="rounded-2xl border border-slate-200 bg-slate-50 p-4 space-y-3">
                            @csrf
                            <h4 class="text-xs font-bold text-slate-700 uppercase">Crear Nuevo Cargo</h4>
                            <div class="grid gap-2 sm:grid-cols-2">
                                <div class="sm:col-span-2">
                                    <label class="block text-[10px] font-bold text-slate-600 uppercase">Nombre del Cargo</label>
                                    <input type="text" name="nombre" class="w-full text-xs rounded-xl border border-slate-200 p-2 bg-white" placeholder="Ej: Profesional Especializado" required>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-600 uppercase">Nivel Jerárquico</label>
                                    <select name="nivel_jerarquico" class="w-full text-xs rounded-xl border border-slate-200 p-2 bg-white" required>
                                        <option value="PROFESIONAL">Profesional</option>
                                        <option value="DIRECTIVO">Directivo</option>
                                        <option value="ASESOR">Asesor</option>
                                        <option value="TECNICO">Técnico</option>
                                        <option value="ASISTENCIAL">Asistencial</option>
                                        <option value="DOCENTE">Docente</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-600 uppercase">Código / Grado</label>
                                    <div class="flex gap-1">
                                        <input type="number" name="codigo_cargo" class="w-1/2 text-xs rounded-xl border border-slate-200 p-2 bg-white" placeholder="Cód: 219">
                                        <input type="number" name="grado_cargo" class="w-1/2 text-xs rounded-xl border border-slate-200 p-2 bg-white" placeholder="Grd: 01">
                                    </div>
                                </div>
                            </div>
                            <button type="submit" class="w-full bg-[#00594E] text-white rounded-xl py-2 text-xs font-bold hover:brightness-110 transition">Guardar Cargo</button>
                        </form>

                        <!-- Tabla de Cargos -->
                        <div class="overflow-x-auto max-h-96">
                            <table class="w-full text-left text-xs">
                                <thead class="bg-[#EAF2EF] text-[#00594E] font-bold uppercase text-[10px] rounded-xl sticky top-0">
                                    <tr>
                                        <th class="p-3">Cargo</th>
                                        <th class="p-3">Nivel</th>
                                        <th class="p-3 text-center">Estado</th>
                                        <th class="p-3 text-right">Acción</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @foreach($cargosCatalogo as $cg)
                                    <tr class="hover:bg-slate-50">
                                        <td class="p-3 font-semibold text-slate-800">{{ $cg->nombre }}</td>
                                        <td class="p-3 text-slate-500">{{ $cg->nivel_jerarquico ?? 'PROFESIONAL' }}</td>
                                        <td class="p-3 text-center">
                                            <span class="inline-block px-2 py-0.5 rounded text-[10px] font-bold {{ ($cg->activo ?? true) ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-600' }}">
                                                {{ ($cg->activo ?? true) ? 'Activo' : 'Inactivo' }}
                                            </span>
                                        </td>
                                        <td class="p-3 text-right">
                                            @if(isset($cg->id_cargo))
                                            <form method="POST" action="{{ route('admin.cargos.toggle', $cg->id_cargo) }}" class="inline">
                                                @csrf
                                                <button type="submit" class="text-[10px] font-bold px-2 py-1 rounded {{ ($cg->activo ?? true) ? 'bg-red-50 text-red-600 hover:bg-red-100' : 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100' }}">
                                                    {{ ($cg->activo ?? true) ? 'Inhabilitar' : 'Habilitar' }}
                                                </button>
                                            </form>
                                            @endif
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Catálogo de Dependencias / Áreas -->
                    <div class="panel-card rounded-3xl p-6 space-y-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-bold uppercase tracking-[0.22em] text-[#00594E]">Catálogo Maestro</p>
                                <h2 class="text-2xl font-black text-slate-900">Dependencias / Áreas</h2>
                            </div>
                        </div>

                        <!-- Formulario Crear Dependencia -->
                        <form method="POST" action="{{ route('admin.dependencias.store') }}" class="rounded-2xl border border-slate-200 bg-slate-50 p-4 space-y-3">
                            @csrf
                            <h4 class="text-xs font-bold text-slate-700 uppercase">Crear Nueva Dependencia</h4>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-600 uppercase">Nombre del Área / Dependencia</label>
                                <input type="text" name="nombre" class="w-full text-xs rounded-xl border border-slate-200 p-2 bg-white" placeholder="Ej: Dirección de Planeación" required>
                            </div>
                            <button type="submit" class="w-full bg-[#00594E] text-white rounded-xl py-2 text-xs font-bold hover:brightness-110 transition">Guardar Dependencia</button>
                        </form>

                        <!-- Tabla de Dependencias -->
                        <div class="overflow-x-auto max-h-96">
                            <table class="w-full text-left text-xs">
                                <thead class="bg-[#EAF2EF] text-[#00594E] font-bold uppercase text-[10px] rounded-xl sticky top-0">
                                    <tr>
                                        <th class="p-3">Dependencia / Área</th>
                                        <th class="p-3 text-center">Estado</th>
                                        <th class="p-3 text-right">Acción</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @foreach($dependenciasCatalogo as $dp)
                                    <tr class="hover:bg-slate-50">
                                        <td class="p-3 font-semibold text-slate-800">{{ $dp->nombre }}</td>
                                        <td class="p-3 text-center">
                                            <span class="inline-block px-2 py-0.5 rounded text-[10px] font-bold {{ ($dp->activa ?? true) ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-600' }}">
                                                {{ ($dp->activa ?? true) ? 'Activa' : 'Inactiva' }}
                                            </span>
                                        </td>
                                        <td class="p-3 text-right">
                                            @if(isset($dp->id_dependencia))
                                            <form method="POST" action="{{ route('admin.dependencias.toggle', $dp->id_dependencia) }}" class="inline">
                                                @csrf
                                                <button type="submit" class="text-[10px] font-bold px-2 py-1 rounded {{ ($dp->activa ?? true) ? 'bg-red-50 text-red-600 hover:bg-red-100' : 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100' }}">
                                                    {{ ($dp->activa ?? true) ? 'Inhabilitar' : 'Habilitar' }}
                                                </button>
                                            </form>
                                            @endif
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </section>

            <!-- SECTION: PARCIALES Y NO CALIFICADOS (Admin Only - S9) -->
            <section id="section-no-calificados" class="section-content hidden space-y-6">
                <!-- Evaluaciones Parciales -->
                <div class="panel-card rounded-3xl p-6">
                    <div class="flex items-center justify-between gap-4 mb-4">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-[0.22em] text-[#00594E]">Monitoreo</p>
                            <h2 class="text-2xl font-black text-slate-900">Periodos y Evaluaciones Parciales</h2>
                            <p class="text-xs text-slate-500 mt-1">Listado de tramos parciales abiertos y cerrados para funcionarios.</p>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <span class="text-xs font-bold bg-[#EAF2EF] text-[#00594E] px-3 py-1 rounded-full">Total: {{ $periodosParciales->count() }}</span>
                            <button type="button" onclick="document.getElementById('form-periodo-parcial').classList.toggle('hidden')" class="inline-flex items-center gap-1.5 bg-[#00594E] text-white px-3 py-2 rounded-xl text-xs font-bold hover:brightness-110 transition">
                                <span class="material-symbols-outlined text-base">add</span>
                                Abrir periodo parcial
                            </button>
                        </div>
                    </div>
                    <form id="form-periodo-parcial" method="POST" action="{{ route('admin.periodos-parciales.store') }}" class="{{ $errors->has('periodo_parcial') ? '' : 'hidden' }} mb-5 rounded-2xl border border-emerald-100 bg-[#EAF2EF]/45 p-4">
                        @csrf
                        <div class="flex items-start gap-2 mb-4 text-xs text-[#00594E]">
                            <span class="material-symbols-outlined text-base shrink-0">info</span>
                            <p>Habilita una evaluación parcial para un funcionario. El tramo debe pertenecer al periodo seleccionado y durar al menos 90 días.</p>
                        </div>
                        @error('periodo_parcial')
                            <p class="mb-3 rounded-lg bg-red-50 p-2 text-xs font-semibold text-red-700">{{ $message }}</p>
                        @enderror
                        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                            <div>
                                <label for="periodo-parcial-periodo" class="mb-1 block text-[10px] font-bold uppercase text-slate-500">Periodo base</label>
                                <select id="periodo-parcial-periodo" name="id_periodo" required onchange="actualizarFormularioPeriodoParcial()" class="w-full rounded-xl border border-slate-200 bg-white p-2.5 text-xs outline-none focus:border-[#00594E]">
                                    <option value="">Selecciona un periodo abierto</option>
                                    @foreach($periodos->where('estado', 'ABIERTO') as $periodo)
                                        <option value="{{ $periodo->id_periodo }}" data-sistema="{{ $periodo->sistema }}" data-inicio="{{ $periodo->fecha_inicio }}" data-fin="{{ $periodo->fecha_fin }}">{{ $periodo->sistema === 'RENDIMIENTO_LABORAL' ? 'RL' : 'AG' }} · {{ $periodo->anio }}-{{ (int) $periodo->semestre === 1 ? 'A' : 'B' }} ({{ $periodo->fecha_inicio }} a {{ $periodo->fecha_fin }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="periodo-parcial-funcionario" class="mb-1 block text-[10px] font-bold uppercase text-slate-500">Funcionario</label>
                                <select id="periodo-parcial-funcionario" name="id_vinc_funcionario" required disabled class="w-full rounded-xl border border-slate-200 bg-white p-2.5 text-xs outline-none focus:border-[#00594E] disabled:bg-slate-100">
                                    <option value="">Primero selecciona un periodo</option>
                                    @foreach($funcionariosParaPeriodoParcial as $funcionario)
                                        <option value="{{ $funcionario->id_vinculacion }}" data-sistema="{{ $funcionario->sistema_evaluacion }}" hidden>{{ $funcionario->nombres }} {{ $funcionario->apellidos }} · {{ $funcionario->cargo }} · {{ $funcionario->area }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="periodo-parcial-inicio" class="mb-1 block text-[10px] font-bold uppercase text-slate-500">Fecha de inicio</label>
                                <input id="periodo-parcial-inicio" type="date" name="fecha_inicio" required disabled class="w-full rounded-xl border border-slate-200 bg-white p-2.5 text-xs outline-none focus:border-[#00594E] disabled:bg-slate-100">
                            </div>
                            <div>
                                <label for="periodo-parcial-fin" class="mb-1 block text-[10px] font-bold uppercase text-slate-500">Fecha de fin</label>
                                <input id="periodo-parcial-fin" type="date" name="fecha_fin" required disabled class="w-full rounded-xl border border-slate-200 bg-white p-2.5 text-xs outline-none focus:border-[#00594E] disabled:bg-slate-100">
                            </div>
                        </div>
                        <div class="mt-3 flex flex-col gap-3 sm:flex-row sm:items-end">
                            <div class="flex-1">
                                <label for="periodo-parcial-referencia" class="mb-1 block text-[10px] font-bold uppercase text-slate-500">Referencia o motivo</label>
                                <input id="periodo-parcial-referencia" type="text" name="referencia" maxlength="200" placeholder="Ej.: ingreso posterior, traslado o licencia" class="w-full rounded-xl border border-slate-200 bg-white p-2.5 text-xs outline-none focus:border-[#00594E]">
                            </div>
                            <button type="submit" class="bg-[#00594E] px-4 py-2.5 text-xs font-bold text-white rounded-xl hover:brightness-110 transition">Crear periodo parcial</button>
                        </div>
                    </form>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-[#EAF2EF] text-[#00594E] font-bold uppercase text-[10px]">
                                <tr>
                                    <th class="p-3">Funcionario</th>
                                    <th class="p-3">Cargo / Área</th>
                                    <th class="p-3">Rango de Fechas</th>
                                    <th class="p-3">Referencia</th>
                                    <th class="p-3 text-center">Estado</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse($periodosParciales as $pp)
                                <tr class="hover:bg-slate-50">
                                    <td class="p-3 font-semibold text-slate-900">{{ $pp->funcionario_nombres }} {{ $pp->funcionario_apellidos }}</td>
                                    <td class="p-3 text-slate-500">{{ $pp->funcionario_cargo }} ({{ $pp->funcionario_area }})</td>
                                    <td class="p-3 text-slate-600">{{ $pp->fecha_inicio }} al {{ $pp->fecha_fin }}</td>
                                    <td class="p-3 text-slate-500">{{ $pp->referencia ?: 'Sin referencia' }}</td>
                                    <td class="p-3 text-center">
                                        <span class="inline-block px-2.5 py-1 rounded-full text-[10px] font-bold {{ $pp->estado === 'ABIERTO' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">
                                            {{ $pp->estado }}
                                        </span>
                                    </td>
                                </tr>
                                @empty
                                <tr><td colspan="5" class="p-6 text-center text-slate-400">No hay periodos parciales registrados.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Lista de Personas No Calificadas / Sin Concertación -->
                <div class="panel-card rounded-3xl p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-[0.22em] text-red-600">Alerta de Seguimiento</p>
                            <h2 class="text-2xl font-black text-slate-900">Funcionarios Sin Concertación / No Calificados</h2>
                            <p class="text-xs text-slate-500 mt-1">Funcionarios con vinculación activa que no registran concertación ni evaluación en el periodo actual.</p>
                        </div>
                        <span class="text-xs font-bold bg-red-50 text-red-700 px-3 py-1 rounded-full">Total: {{ $funcionariosNoCalificados->count() }}</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-red-50 text-red-800 font-bold uppercase text-[10px]">
                                <tr>
                                    <th class="p-3">Funcionario</th>
                                    <th class="p-3">Documento</th>
                                    <th class="p-3">Cargo</th>
                                    <th class="p-3">Dependencia</th>
                                    <th class="p-3">Sistema</th>
                                    <th class="p-3 text-center">Estado</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse($funcionariosNoCalificados as $fnc)
                                <tr class="hover:bg-slate-50">
                                    <td class="p-3 font-semibold text-slate-900">{{ $fnc->nombres }} {{ $fnc->apellidos }}</td>
                                    <td class="p-3 text-slate-500">{{ $fnc->numero_doc }}</td>
                                    <td class="p-3 text-slate-600">{{ $fnc->cargo }}</td>
                                    <td class="p-3 text-slate-600">{{ $fnc->area }}</td>
                                    <td class="p-3 text-slate-500">{{ $fnc->sistema_evaluacion }}</td>
                                    <td class="p-3 text-center">
                                        <span class="inline-block px-2.5 py-1 rounded-full text-[10px] font-bold bg-amber-50 text-amber-700">
                                            Sin Concertación
                                        </span>
                                    </td>
                                </tr>
                                @empty
                                <tr><td colspan="6" class="p-6 text-center text-emerald-600 font-medium">Todos los funcionarios activos cuentan con concertación o evaluación en curso.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <!-- SECTION: PERIODOS (Admin Only) -->
            <section id="section-periodos" class="section-content hidden space-y-6">
                <div class="grid gap-6 lg:grid-cols-[1.3fr_0.7fr]">
                    <div class="panel-card rounded-3xl p-6">
                        <h2 class="text-2xl font-black text-slate-900 mb-6">Periodos de Evaluación</h2>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm text-left text-slate-500">
                                <thead class="text-xs uppercase bg-[#EAF2EF] text-[#00594E] font-bold rounded-xl">
                                    <tr>
                                        <th class="px-4 py-3">Sistema</th>
                                        <th class="px-4 py-3">Año</th>
                                        <th class="px-4 py-3">Período</th>
                                        <th class="px-4 py-3">Inicio</th>
                                        <th class="px-4 py-3">Fin</th>
                                        <th class="px-4 py-3">Descripción</th>
                                        <th class="px-4 py-3">Estado</th>
                                        <th class="px-4 py-3 text-right">Acción</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($periodos as $p)
                                        <tr class="border-b border-slate-100 hover:bg-slate-50">
                                            <td class="px-4 py-3 font-semibold text-slate-800">{{ $p->sistema }}</td>
                                            <td class="px-4 py-3">{{ $p->anio }}</td>
                                            <td class="px-4 py-3">{{ $p->anio }}-{{ (int) $p->semestre === 1 ? 'A' : 'B' }}</td>
                                            <td class="px-4 py-3 text-xs">{{ $p->fecha_inicio }}</td>
                                            <td class="px-4 py-3 text-xs">{{ $p->fecha_fin }}</td>
                                            <td class="px-4 py-3 text-xs text-slate-500">{{ $p->descripcion ?? 'Semestre regular' }}</td>
                                            <td class="px-4 py-3">
                                                <span class="text-[10px] font-bold uppercase rounded-full px-2.5 py-1 {{ $p->estado === 'ABIERTO' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">{{ $p->estado }}</span>
                                            </td>
                                            <td class="px-4 py-3">
                                                <div class="flex justify-end items-center gap-3 whitespace-nowrap">
                                                    <button type="button" onclick="window.abrirModalPeriodo(@js($p))" class="text-xs font-bold text-[#00594E] hover:underline">Editar</button>
                                                    <button type="button" data-id="{{ $p->id_periodo }}" onclick="window.verAuditoriaPeriodo(this)" class="text-xs font-bold text-slate-600 hover:underline">Auditoría</button>
                                                    <form method="POST" action="{{ route('admin.periodos.toggle', $p->id_periodo) }}">
                                                    @csrf
                                                    <button class="text-xs font-bold {{ $p->estado === 'ABIERTO' ? 'text-red-600' : 'text-emerald-700' }} hover:underline" type="submit">{{ $p->estado === 'ABIERTO' ? 'Cerrar' : 'Abrir' }}</button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <aside class="space-y-6">
                        <div id="panel-auditoria-periodo" class="panel-card rounded-3xl p-6 hidden">
                            <div class="flex items-start justify-between gap-3 mb-4">
                                <div>
                                    <p class="text-xs font-bold uppercase tracking-[0.22em] text-[#00594E]">Trazabilidad</p>
                                    <h3 id="auditoria-periodo-titulo" class="text-lg font-black text-slate-800">Auditoría del período</h3>
                                </div>
                                <button type="button" onclick="document.getElementById('panel-auditoria-periodo').classList.add('hidden')" aria-label="Cerrar auditoría" class="w-8 h-8 flex items-center justify-center rounded-full bg-slate-100 text-slate-500 hover:bg-slate-200 transition">
                                    <span class="material-symbols-outlined text-sm">close</span>
                                </button>
                            </div>
                            <div id="auditoria-periodo-lista" class="space-y-3 max-h-96 overflow-y-auto"></div>
                        </div>

                        <div class="panel-card rounded-3xl p-6">
                            <h3 class="text-lg font-bold text-slate-800 mb-2">Crear Periodo</h3>
                            <p class="text-xs text-slate-500 mb-4">Abre un nuevo periodo semestral de evaluación.</p>
                            <form method="POST" action="{{ route('admin.periodos.store') }}" class="space-y-3">
                                @csrf
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-600 uppercase">Sistema</label>
                                    <select name="sistema" class="w-full text-xs rounded-xl border border-slate-200 p-2 bg-white">
                                        <option value="RENDIMIENTO_LABORAL">Rendimiento Laboral</option>
                                        <option value="ACUERDO_GESTION">Acuerdo de Gestión</option>
                                    </select>
                                </div>
                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-600 uppercase">Año</label>
                                        <input type="number" name="anio" value="{{ date('Y') }}" class="w-full text-xs rounded-xl border border-slate-200 p-2 bg-white" required>
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-600 uppercase">Semestre</label>
                                        <select name="semestre" class="w-full text-xs rounded-xl border border-slate-200 p-2 bg-white">
                                            <option value="1">Semestre 1</option>
                                            <option value="2">Semestre 2</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-600 uppercase">Fecha Inicio</label>
                                        <input type="date" name="fecha_inicio" class="w-full text-xs rounded-xl border border-slate-200 p-2 bg-white" required>
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-600 uppercase">Fecha Fin</label>
                                        <input type="date" name="fecha_fin" class="w-full text-xs rounded-xl border border-slate-200 p-2 bg-white" required>
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-600 uppercase text-[#00594E]">Inicio Concertación (Opcional)</label>
                                        <input type="date" name="fecha_inicio_concertacion" class="w-full text-xs rounded-xl border border-slate-200 p-2 bg-white focus:border-[#00594E] outline-none">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-600 uppercase text-red-600">Cierre Concertación</label>
                                        <input type="date" name="fecha_fin_concertacion" class="w-full text-xs rounded-xl border border-slate-200 p-2 bg-white focus:border-red-500 outline-none" required>
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-600 uppercase">Descripción (Opcional)</label>
                                    <input type="text" name="descripcion" maxlength="200" placeholder="Ej: Primer semestre 2026 ordinario" class="w-full text-xs rounded-xl border border-slate-200 p-2 bg-white">
                                </div>
                                <button type="submit" class="w-full bg-[#00594E] text-white rounded-xl py-2 text-xs font-bold hover:brightness-110 transition">Guardar Periodo</button>
                            </form>
                        </div>
                    </aside>
                </div>
            </section>

            <!-- SECTION: PONDERACIONES (Admin Only) -->
            <section id="section-ponderaciones" class="section-content hidden space-y-6">
                <div class="panel-card rounded-3xl p-6">
                    <h2 class="text-2xl font-black text-slate-900 mb-6">Ponderaciones del Sistema</h2>
                    <div class="grid gap-6 md:grid-cols-2">
                        @foreach ($ponderaciones as $pond)
                        @php
                            $tieneEjeMisional = $pond->sistema === 'ACUERDO_GESTION';
                            $totalPonderacion = (float) $pond->peso_compromisos + (float) $pond->peso_competencias + (float) $pond->peso_docencia + (float) $pond->peso_investigacion + (float) $pond->peso_proyeccion_social;
                        @endphp
                        <div class="rounded-2xl border border-slate-200 p-5 bg-white space-y-4">
                            <div class="flex items-center justify-between">
                                <h3 class="font-bold text-slate-800">{{ $pond->sistema === 'RENDIMIENTO_LABORAL' ? 'Rendimiento Laboral' : 'Acuerdos de Gestión' }}</h3>
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-[#EAF2EF] text-[#00594E]">{{ $pond->sistema }}</span>
                            </div>
                            <div class="space-y-3 text-xs">
                                <div class="flex justify-between items-center"><span class="font-bold text-slate-500 uppercase">Total</span><span class="text-base font-black {{ abs($totalPonderacion - 100) < 0.01 ? 'text-[#00594E]' : 'text-red-600' }}">{{ number_format($totalPonderacion, 1) }}%</span></div>
                                <div><div class="flex justify-between py-1"><span class="text-slate-500">Compromisos</span><span class="font-bold text-slate-800">{{ $pond->peso_compromisos }}%</span></div><div class="h-2 rounded-full bg-slate-200 overflow-hidden"><div class="h-full bg-[#00594E]" style="width: {{ min(100, max(0, (float) $pond->peso_compromisos)) }}%"></div></div></div>
                                <div><div class="flex justify-between py-1"><span class="text-slate-500">Competencias</span><span class="font-bold text-slate-800">{{ $pond->peso_competencias }}%</span></div><div class="h-2 rounded-full bg-slate-200 overflow-hidden"><div class="h-full bg-[#B5A160]" style="width: {{ min(100, max(0, (float) $pond->peso_competencias)) }}%"></div></div></div>
                                @if ($tieneEjeMisional)
                                <div><div class="flex justify-between py-1"><span class="text-slate-500">Docencia</span><span class="font-bold text-slate-800">{{ $pond->peso_docencia }}%</span></div><div class="h-2 rounded-full bg-slate-200 overflow-hidden"><div class="h-full bg-slate-700" style="width: {{ min(100, max(0, (float) $pond->peso_docencia)) }}%"></div></div></div>
                                <div><div class="flex justify-between py-1"><span class="text-slate-500">Investigación</span><span class="font-bold text-slate-800">{{ $pond->peso_investigacion }}%</span></div><div class="h-2 rounded-full bg-slate-200 overflow-hidden"><div class="h-full bg-slate-500" style="width: {{ min(100, max(0, (float) $pond->peso_investigacion)) }}%"></div></div></div>
                                <div><div class="flex justify-between py-1"><span class="text-slate-500">Proyección social</span><span class="font-bold text-slate-800">{{ $pond->peso_proyeccion_social }}%</span></div><div class="h-2 rounded-full bg-slate-200 overflow-hidden"><div class="h-full bg-slate-400" style="width: {{ min(100, max(0, (float) $pond->peso_proyeccion_social)) }}%"></div></div></div>
                                @endif
                            </div>
                            <form method="POST" action="{{ route('admin.ponderaciones.update', $pond->sistema) }}" class="space-y-3">
                                @csrf
                                <div class="space-y-2">
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-600 uppercase">Compromisos (%)</label>
                                        <input type="number" step="0.1" name="peso_compromisos" value="{{ $pond->peso_compromisos }}" class="w-full text-xs rounded-lg border border-slate-200 p-2" required>
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-600 uppercase">Competencias (%)</label>
                                        <input type="number" step="0.1" name="peso_competencias" value="{{ $pond->peso_competencias }}" class="w-full text-xs rounded-lg border border-slate-200 p-2" required>
                                    </div>
                                    @if ($pond->sistema === 'ACUERDO_GESTION')
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-600 uppercase">Docencia (%)</label>
                                        <input type="number" step="0.1" name="peso_docencia" value="{{ $pond->peso_docencia }}" class="w-full text-xs rounded-lg border border-slate-200 p-2">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-600 uppercase">Investigación (%)</label>
                                        <input type="number" step="0.1" name="peso_investigacion" value="{{ $pond->peso_investigacion }}" class="w-full text-xs rounded-lg border border-slate-200 p-2">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-600 uppercase">Proyección Social (%)</label>
                                        <input type="number" step="0.1" name="peso_proyeccion_social" value="{{ $pond->peso_proyeccion_social }}" class="w-full text-xs rounded-lg border border-slate-200 p-2">
                                    </div>
                                    @endif
                                </div>
                                <button type="submit" class="w-full bg-[#B5A160] text-white rounded-lg py-2 text-xs font-bold hover:brightness-110 transition">Guardar ponderación</button>
                            </form>
                        </div>
                        @endforeach
                    </div>
                </div>
            </section>

            <!-- SECTION: RECURSOS Y PLANES (Admin / Talento Humano) -->
            <section id="section-recursos-admin" class="section-content hidden space-y-6">
                <div class="panel-card rounded-3xl p-6">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between mb-6">
                        <div>
                            <h2 class="text-2xl sm:text-3xl font-black text-slate-900">Recursos de reposición y apelación</h2>
                            <p class="text-sm text-slate-500 mt-1">Radicados de los evaluados, decisiones y asignación de superior jerárquico.</p>
                        </div>
                        <span id="recursos-admin-contador" class="text-[10px] font-bold uppercase rounded-full px-3 py-1.5 bg-[#EAF2EF] text-[#00594E]">Cargando...</span>
                    </div>
                    <div id="recursos-admin-lista" class="grid gap-4 lg:grid-cols-2">
                        <div class="py-10 text-center text-slate-500 text-xs">Cargando recursos...</div>
                    </div>
                </div>

                <div class="panel-card rounded-3xl p-6">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between mb-6">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-[0.22em] text-[#00594E]">Planes de mejoramiento</p>
                            <h2 class="text-2xl sm:text-3xl font-black text-slate-900">Planes condicionados a la calificación</h2>
                            <p class="text-sm text-slate-500 mt-1">Solo habilitados cuando la nota fue firmada y aceptada sin renuencia.</p>
                        </div>
                    </div>
                    <div id="planes-admin-lista" class="grid gap-4 lg:grid-cols-2">
                        <div class="py-10 text-center text-slate-500 text-xs">Cargando planes...</div>
                    </div>
                </div>

                <div class="panel-card rounded-3xl p-6">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between mb-6">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-[0.22em] text-[#00594E]">Renuencia</p>
                            <h2 class="text-2xl sm:text-3xl font-black text-slate-900">Renuncias a la firma con testigos</h2>
                            <p class="text-sm text-slate-500 mt-1">Notificaciones con constancia de testigo institucional y evidencias.</p>
                        </div>
                    </div>
                    <div id="renuencias-admin-lista" class="grid gap-4 lg:grid-cols-2">
                        <div class="py-10 text-center text-slate-500 text-xs">Cargando renuencias...</div>
                    </div>
                </div>
            </section>

            <!-- SECTION: IMPEDIMENTOS Y RECUSACIONES (Admin Only - S9) -->
            <section id="section-impedimentos-admin" class="section-content hidden space-y-6">
                <div class="panel-card rounded-3xl p-6">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between mb-6">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-[0.22em] text-[#00594E]">Gestión de Talento Humano</p>
                            <h2 class="text-2xl sm:text-3xl font-black text-slate-900">Impedimentos y Recusaciones</h2>
                            <p class="text-sm text-slate-500 mt-1">Revisión de solicitudes, dictamen de la oficina y reasignación directa de evaluador.</p>
                        </div>
                    </div>
                    <div id="impedimentos-admin-lista" class="grid gap-4 lg:grid-cols-2">
                        <div class="py-10 text-center text-slate-500 text-xs">Cargando solicitudes...</div>
                    </div>
                </div>
            </section>

            <!-- SECTION: TRASLADOS (Admin Only) -->
            <section id="section-traslados" class="section-content hidden space-y-6">
                <div class="grid gap-6 lg:grid-cols-[1.1fr_0.9fr] items-start">
                    <div class="panel-card rounded-3xl p-6">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="material-symbols-outlined text-[#00594E]">swap_horiz</span>
                            <h2 class="text-2xl sm:text-3xl font-black text-slate-900">Traslados de Funcionarios</h2>
                        </div>
                        <p class="text-sm text-slate-500 mb-4">Registra el traslado a otra dependencia. El traslado reinicia la concertación con el nuevo evaluador.</p>
                        <form method="POST" action="{{ route('admin.traslados.store') }}" class="space-y-3">
                            @csrf
                            <div>
                                <label class="block text-[10px] font-bold text-slate-600 uppercase mb-1">Funcionario a trasladar (Vinculación)</label>
                                <input type="search" id="buscar-funcionario-traslado" oninput="filtrarOpcionesAsignacion('buscar-funcionario-traslado', 'select-funcionario-traslado')" class="mb-2 w-full text-xs rounded-xl border border-slate-200 p-2.5 bg-white outline-none focus:border-[#00594E]" placeholder="Buscar funcionario por nombre o cargo" />
                                <select name="id_vinc_funcionario" id="select-funcionario-traslado" onchange="mostrarEvaluadorActualTraslado()" class="w-full text-xs rounded-xl border border-slate-200 p-2.5 bg-white" required>
                                    <option value="">Selecciona un funcionario</option>
                                    @foreach($empleados as $e)
                                        @if($e->id_vinculacion && $e->activo)
                                            <option value="{{ $e->id_vinculacion }}">{{ $e->nombres }} {{ $e->apellidos }} - {{ $e->nombre_cargo }}</option>
                                        @endif
                                    @endforeach
                                </select>
                            </div>
                            <div id="evaluador-origen-box" class="hidden rounded-xl bg-[#EAF2EF] p-3">
                                <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-[#00594E]">Evaluador actual</p>
                                <p id="evaluador-origen-texto" class="text-sm font-semibold text-slate-800 mt-0.5">-</p>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-600 uppercase mb-1">Nuevo evaluador (Vinculación)</label>
                                <input type="search" id="buscar-evaluador-traslado" oninput="filtrarOpcionesAsignacion('buscar-evaluador-traslado', 'select-evaluador-traslado')" class="mb-2 w-full text-xs rounded-xl border border-slate-200 p-2.5 bg-white outline-none focus:border-[#00594E]" placeholder="Buscar evaluador por nombre o cargo" />
                                <select name="id_vinc_evaluador_nuevo" id="select-evaluador-traslado" class="w-full text-xs rounded-xl border border-slate-200 p-2.5 bg-white" required>
                                    <option value="">Selecciona un evaluador</option>
                                    @foreach($empleados as $e)
                                        @if($e->id_vinculacion && $e->es_evaluador && $e->activo)
                                            <option value="{{ $e->id_vinculacion }}">{{ $e->nombres }} {{ $e->apellidos }} - {{ $e->nombre_cargo }}</option>
                                        @endif
                                    @endforeach
                                </select>
                            </div>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-600 uppercase mb-1">Fecha efectiva del traslado</label>
                                    <input type="date" name="fecha_traslado" min="{{ date('Y-m-d') }}" value="{{ date('Y-m-d') }}" class="w-full text-xs rounded-xl border border-slate-200 p-2.5 bg-white outline-none focus:border-[#00594E]" required />
                                    <p class="text-[9px] text-slate-400 mt-0.5">Solo fecha actual o futura.</p>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-600 uppercase mb-1">Nueva Área / Dependencia</label>
                                    <select name="area_nuevo" class="w-full text-xs rounded-xl border border-slate-200 p-2.5 bg-white">
                                        <option value="">Conservar área actual o seleccionar nueva</option>
                                        @foreach($dependenciasCatalogo as $dp)
                                            <option value="{{ $dp->nombre }}">{{ $dp->nombre }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-600 uppercase mb-1">Nuevo Cargo (opcional)</label>
                                <select name="cargo_nuevo" class="w-full text-xs rounded-xl border border-slate-200 p-2.5 bg-white">
                                    <option value="">Conservar cargo actual o seleccionar nuevo</option>
                                    @foreach($cargosCatalogo as $cg)
                                        <option value="{{ $cg->nombre }}">{{ $cg->nombre }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-600 uppercase mb-1">Motivo del traslado</label>
                                <input type="text" name="motivo" maxlength="500" class="w-full text-xs rounded-xl border border-slate-200 p-2.5 bg-white outline-none focus:border-[#00594E]" placeholder="Ej: Reorganización administrativa, necesidad del servicio" />
                            </div>
                            <button type="submit" class="w-full bg-[#B5A160] text-white rounded-xl py-2.5 text-xs font-bold hover:brightness-110 transition shadow-md shadow-[#B5A160]/20">Procesar traslado</button>
                        </form>
                    </div>

                    <div class="panel-card rounded-3xl p-6">
                        <div class="flex items-end justify-between gap-4 mb-4">
                            <div>
                                <h3 class="text-lg font-bold text-slate-800">Histórico de traslados</h3>
                                <p class="text-xs text-slate-500 mt-1">Registro de traslados ejecutados.</p>
                            </div>
                            <span id="traslados-admin-contador" class="text-[10px] font-bold uppercase rounded-full px-3 py-1.5 bg-[#EAF2EF] text-[#00594E]">Cargando...</span>
                        </div>
                        <div id="traslados-admin-lista" class="grid gap-3">
                            <div class="py-10 text-center text-slate-500 text-xs">Cargando traslados...</div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- SECTION: DELEGACIONES (Admin Only) -->
            <section id="section-delegaciones" class="section-content hidden space-y-6">
                <div class="grid gap-6 lg:grid-cols-[1.1fr_0.9fr] items-start">
                    <div class="panel-card rounded-3xl p-6">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="material-symbols-outlined text-[#00594E]">supervisor_account</span>
                            <h2 class="text-2xl sm:text-3xl font-black text-slate-900">Delegaciones de Funciones</h2>
                        </div>
                        <p class="text-sm text-slate-500 mb-4">Cede temporalmente el rol de evaluador a profesionales universitarios, especializados o docentes sin alterar la titularidad.</p>

                        <form method="POST" action="{{ route('admin.delegaciones.store') }}" class="space-y-3">
                            @csrf
                            <div>
                                <label class="block text-[10px] font-bold text-slate-600 uppercase mb-1">Evaluador titular (Delegante)</label>
                                <select name="id_vinc_delegante" class="w-full text-xs rounded-xl border border-slate-200 p-2.5 bg-white" required>
                                    <option value="">Selecciona el evaluador titular</option>
                                    @foreach($evaluadoresDelegacion as $ed)
                                        <option value="{{ $ed->id_vinculacion }}">{{ $ed->nombres }} {{ $ed->apellidos }} - {{ $ed->cargo }} ({{ $ed->area }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-600 uppercase mb-1">Funcionario que asume la delegación (Delegado)</label>
                                <select name="id_vinc_delegado" class="w-full text-xs rounded-xl border border-slate-200 p-2.5 bg-white" required>
                                    <option value="">Selecciona el funcionario delegado</option>
                                    @foreach($delegadosDisponibles as $dd)
                                        <option value="{{ $dd->id_vinculacion }}">{{ $dd->nombres }} {{ $dd->apellidos }} - {{ $dd->cargo }} ({{ $dd->area }} - {{ $dd->nivel_jerarquico ?? 'PROFESIONAL' }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-600 uppercase mb-1">Fecha inicio (vigencia)</label>
                                    <input type="date" name="fecha_inicio" class="w-full text-xs rounded-xl border border-slate-200 p-2.5 bg-white outline-none focus:border-[#00594E]" required />
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-600 uppercase mb-1">Fecha fin (opcional)</label>
                                    <input type="date" name="fecha_fin" class="w-full text-xs rounded-xl border border-slate-200 p-2.5 bg-white outline-none focus:border-[#00594E]" />
                                    <p class="text-[9px] text-slate-400 mt-0.5">Vacío = vigencia abierta</p>
                                </div>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-600 uppercase mb-1">Área / Dependencia Delegada</label>
                                <select name="area" class="w-full text-xs rounded-xl border border-slate-200 p-2.5 bg-white">
                                    <option value="">Selecciona dependencia para trazabilidad</option>
                                    @foreach($dependenciasCatalogo as $dp)
                                        <option value="{{ $dp->nombre }}">{{ $dp->nombre }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-600 uppercase mb-1">Motivo</label>
                                <input type="text" name="motivo" maxlength="500" class="w-full text-xs rounded-xl border border-slate-200 p-2.5 bg-white outline-none focus:border-[#00594E]" placeholder="Ej. Vacaciones, comisión de servicios, licencia" />
                            </div>

                            <button type="submit" class="w-full bg-[#B5A160] text-white rounded-xl py-2.5 text-xs font-bold hover:brightness-110 transition shadow-md shadow-[#B5A160]/20">Activar delegación</button>
                        </form>
                    </div>

                    <div class="panel-card rounded-3xl p-6">
                        <div class="flex items-end justify-between gap-4 mb-4">
                            <div>
                                <h3 class="text-lg font-bold text-slate-800">Histórico de delegaciones</h3>
                                <p class="text-xs text-slate-500 mt-1">Delegaciones activas y finalizadas.</p>
                            </div>
                            <span id="delegaciones-admin-contador" class="text-[10px] font-bold uppercase rounded-full px-3 py-1.5 bg-[#EAF2EF] text-[#00594E]">Cargando...</span>
                        </div>
                        <div id="delegaciones-admin-lista" class="grid gap-3">
                            <div class="py-10 text-center text-slate-500 text-xs">Cargando delegaciones...</div>
                        </div>
                    </div>
                </div>
            </section>
            <!-- SECTION: EXTRATIEMPO (Admin Only - S9) -->
            <section id="section-extratiempo" class="section-content hidden space-y-6">
                <div class="grid gap-6 lg:grid-cols-[1.2fr_0.8fr]">
                    <!-- Formulario de Extratiempo -->
                    <div class="panel-card rounded-3xl p-6">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="material-symbols-outlined text-[#00594E]">history_toggle_off</span>
                            <h2 class="text-2xl sm:text-3xl font-black text-slate-900">Concertación Extratiempo</h2>
                        </div>
                        <p class="text-sm text-slate-500 mb-6">Autoriza tiempo adicional justificado para aquellas evaluaciones que no lograron concertarse en los plazos establecidos.</p>

                        <form method="POST" action="" id="form-extratiempo" class="space-y-4">
                            @csrf
                            <div>
                                <label class="block text-[10px] font-bold text-slate-600 uppercase mb-1">Evaluación / Funcionario a habilitar</label>
                                <select name="id_evaluacion" id="select-evaluacion-extratiempo" onchange="document.getElementById('form-extratiempo').action = '/admin/evaluaciones/' + this.value + '/extratiempo'" class="w-full text-xs rounded-xl border border-slate-200 p-2.5 bg-white outline-none focus:border-[#00594E]" required>
                                    <option value="">Selecciona la evaluación del funcionario</option>
                                    @foreach($evaluacionesExtratiempo as $ee)
                                        <option value="{{ $ee->id_evaluacion }}">
                                            {{ $ee->nombres }} {{ $ee->apellidos }} ({{ $ee->cargo }}) - {{ $ee->sistema }} {{ $ee->anio }}-{{ (int) $ee->semestre === 1 ? 'A' : 'B' }} [{{ $ee->estado }}]
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-600 uppercase mb-1">Justificación / Observación Oficial</label>
                                <textarea name="justificacion" rows="3" class="w-full text-xs rounded-xl border border-slate-200 p-2.5 bg-white outline-none focus:border-[#00594E]" placeholder="Ej. Permiso sindical certificado, incapacidad médica radicada, etc." required></textarea>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-600 uppercase mb-1">Nueva Fecha Límite para Concertar</label>
                                <input type="date" name="fecha_limite" class="w-full text-xs rounded-xl border border-slate-200 p-2.5 bg-white outline-none focus:border-[#00594E]" required>
                            </div>
                            <button type="submit" class="w-full bg-[#B5A160] hover:brightness-110 text-white rounded-xl py-2.5 text-xs font-bold transition shadow-md shadow-[#B5A160]/20 flex items-center justify-center gap-2">
                                <span class="material-symbols-outlined text-sm">lock_open</span> Autorizar Extratiempo
                            </button>
                        </form>
                    </div>

                    <!-- Panel lateral info -->
                    <div class="panel-card rounded-3xl p-6 bg-slate-50 border border-slate-100 flex flex-col justify-center">
                        <div class="text-center space-y-3">
                            <div class="w-16 h-16 bg-white rounded-full flex items-center justify-center shadow-sm mx-auto text-[#00594E]">
                                <span class="material-symbols-outlined text-3xl">info</span>
                            </div>
                            <h3 class="text-base font-black text-slate-800">Sobre el Extratiempo</h3>
                            <p class="text-xs text-slate-500">Al aprobar un extratiempo, el sistema registrará la observación y reabrirá la concertación para el evaluador y evaluado seleccionado, independientemente del cierre general del periodo.</p>
                        </div>
                    </div>
                </div>

                <div class="panel-card rounded-3xl p-6 lg:col-span-2">
                    <h3 id="section-extratiempo-historial" class="text-lg font-bold text-slate-800 mb-4 flex items-center gap-2">
                        <span class="material-symbols-outlined text-base">history</span>
                        Historial de Autorizaciones de Extratiempo
                    </h3>
                    <div class="space-y-3">
                        @forelse($historialExtratiempo as $hx)
                            <div class="rounded-2xl border border-slate-100 bg-white p-4 space-y-2 relative">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="text-xs font-black text-slate-800">{{ $hx->nombres }} {{ $hx->apellidos }}</p>
                                        <p class="text-[10px] text-slate-400">Cargo: {{ $hx->cargo }}</p>
                                    </div>
                                    <span class="text-[10px] font-bold uppercase rounded-full px-2.5 py-1 {{ $hx->activo ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">{{ $hx->activo ? 'Vigente' : 'Inactivo/Cerrado' }}</span>
                                </div>
                                <p class="text-[11px] text-slate-600"><span class="font-bold text-slate-700">Justificación:</span> {{ $hx->justificacion }}</p>
                                <div class="flex flex-wrap items-center justify-between gap-4 text-[10px] text-slate-500 border-t border-slate-100 pt-2 mt-2">
                                    <p><span class="font-bold text-slate-600">Límite concedido:</span> {{ \Carbon\Carbon::parse($hx->fecha_limite)->format('d/m/Y') }}</p>
                                    <p><span class="font-bold text-slate-600">Autorizado por:</span> {{ $hx->autorizado_por }} el {{ \Carbon\Carbon::parse($hx->created_at)->format('d/m/Y') }}</p>
                                </div>
                            </div>
                        @empty
                            <div class="py-8 text-center text-slate-500 text-xs rounded-xl border border-dashed border-slate-200">No hay registros de extratiempo concedidos.</div>
                        @endforelse
                    </div>
                </div>
            </section>
        </main>
    </div>
</div>

@include('partials.periodo-modal')
@include('partials.delegacion-modal')
@include('partials.password-modal')
@include('partials.temp-password-toast')

<script>
    window.APP_CONFIG = {
        activeRole: @js($rolActivo),
        csrfToken: @js(csrf_token()),
        periodosDisponibles: @js($periodos->map(fn ($p) => [
            'id_periodo' => $p->id_periodo,
            'sistema' => $p->sistema,
            'anio' => $p->anio,
            'semestre' => $p->semestre,
            'estado' => $p->estado,
            'fecha_inicio' => $p->fecha_inicio,
            'fecha_fin' => $p->fecha_fin,
        ])->values()),
        ponderacionesConfig: @js($ponderacionesConfig ?? []),
        impedimentos: @js($impedimentos ?? []),
        evaluadores: @js($empleados->filter(fn($e) => $e->es_evaluador && $e->activo)->map(fn($e) => [
            'id_vinculacion' => $e->id_vinculacion,
            'nombre_completo' => $e->nombres . ' ' . $e->apellidos,
            'cargo' => $e->nombre_cargo
        ])->values()),
    };
</script>
@vite('resources/js/dashboards/admin.js')
@endsection
