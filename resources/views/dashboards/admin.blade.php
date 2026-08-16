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
                            <p class="text-sm text-slate-500 mt-1">Datos de acceso y roles en la plataforma.</p>
                        </div>
                        <div class="text-sm text-slate-500">Total: <span class="font-bold text-slate-900">{{ $usuarios->count() }}</span></div>
                    </div>
                    <div class="mt-6 grid gap-4 lg:grid-cols-2 xl:grid-cols-3">
                        @foreach ($usuarios as $u)
                            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
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
                                <form method="POST" action="{{ route('usuarios.reset-password', $u->id_usuario) }}" class="mt-4">
                                    @csrf
                                    <button class="w-full rounded-xl bg-slate-900 text-white py-2.5 text-sm font-bold hover:bg-slate-800 transition" type="submit">Generar contraseña temporal</button>
                                </form>
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
                                    data-estado="{{ $e->activo ? 'Activo' : 'Inactivo' }}"
                                    onclick="seleccionarEmpleado(this, @js($e))">
                                    <div class="flex items-center justify-between gap-4">
                                        <div>
                                            <h3 class="text-base font-black text-slate-900 leading-snug">{{ $e->nombres }} {{ $e->apellidos }}</h3>
                                            <p class="text-xs text-slate-500">{{ $e->nombre_cargo ?? 'Sin cargo' }}</p>
                                        </div>
                                        <span class="w-8 h-8 rounded-full flex items-center justify-center bg-[#00594E] text-white text-xs font-bold">{{ strtoupper(substr($e->nombres, 0, 1) . substr($e->apellidos, 0, 1)) }}</span>
                                    </div>
                                    <div class="mt-3 space-y-1 text-xs text-slate-600">
                                        <p><span class="font-bold">Doc:</span> {{ $e->documento_identidad }}</p>
                                        <p><span class="font-bold">Área:</span> {{ $e->nombre_area ?? 'Sin Área' }}</p>
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
                        </div>

                        <!-- Carga Masiva Excel/CSV -->
                        <div class="panel-card rounded-3xl p-6">
                            <h3 class="text-lg font-bold text-slate-800 mb-2">Importar Usuarios</h3>
                            <p class="text-xs text-slate-500 mb-4">Sube un archivo CSV con columnas: `cedula, nombres, apellidos, correo, cargo, nivel, area, tipo_vinculacion, sistema_evaluacion, es_evaluador, aplica_eje`.</p>
                            <form method="POST" action="{{ route('admin.importar.store') }}" enctype="multipart/form-data" class="space-y-3">
                                @csrf
                                <input type="file" name="archivo" accept=".csv" class="w-full text-xs text-slate-600 file:mr-2 file:py-1.5 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-[#EAF2EF] file:text-[#00594E] hover:file:bg-[#d8e8e3] cursor-pointer" required />
                                <button type="submit" class="w-full bg-[#00594E] text-white rounded-xl py-2 text-xs font-bold hover:brightness-110 transition">Importar archivo</button>
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
                                            @if($e->id_vinculacion && $e->es_evaluador)
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
                                            @if($e->id_vinculacion)
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
                                        <th class="px-4 py-3">Semestre</th>
                                        <th class="px-4 py-3">Inicio</th>
                                        <th class="px-4 py-3">Fin</th>
                                        <th class="px-4 py-3">Descripción</th>
                                        <th class="px-4 py-3">Estado</th>
                                        <th class="px-4 py-3 text-right">Acción</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @foreach($periodos as $p)
                                    <tr>
                                        <td class="px-4 py-3 font-semibold text-slate-800">{{ $p->sistema }}</td>
                                        <td class="px-4 py-3">{{ $p->anio }}</td>
                                        <td class="px-4 py-3">Semestre {{ $p->semestre }}</td>
                                        <td class="px-4 py-3 text-xs">{{ $p->fecha_inicio }}</td>
                                        <td class="px-4 py-3 text-xs">{{ $p->fecha_fin }}</td>
                                        <td class="px-4 py-3 text-xs text-slate-500 max-w-[160px] truncate" title="{{ $p->descripcion ?? '' }}">{{ $p->descripcion ?? '-' }}</td>
                                        <td class="px-4 py-3">
                                            <span class="px-2.5 py-1 text-[10px] font-bold uppercase rounded-full @if($p->estado === 'ABIERTO') bg-green-50 text-green-700 @else bg-gray-100 text-gray-500 @endif">
                                                {{ $p->estado }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-right">
                                            <div class="flex items-center justify-end gap-2">
                                                <button type="button" onclick="abrirEditarPeriodo(this)" data-id="{{ $p->id_periodo }}" data-sistema="{{ $p->sistema }}" data-anio="{{ $p->anio }}" data-semestre="{{ $p->semestre }}" data-inicio="{{ $p->fecha_inicio }}" data-fin="{{ $p->fecha_fin }}" data-estado="{{ $p->estado }}" data-descripcion="{{ $p->descripcion ?? '' }}" class="px-3 py-1.5 rounded-lg border text-xs font-bold hover:bg-slate-50 transition">Editar</button>
                                                <button type="button" onclick="verAuditoriaPeriodo(this)" data-id="{{ $p->id_periodo }}" class="px-3 py-1.5 rounded-lg border text-xs font-bold hover:bg-slate-50 transition">Auditoría</button>
                                                <form method="POST" action="{{ route('admin.periodos.toggle', $p->id_periodo) }}">
                                                    @csrf
                                                    <button type="submit" class="px-3 py-1.5 rounded-lg border text-xs font-bold hover:bg-slate-50 transition">
                                                        {{ $p->estado === 'ABIERTO' ? 'Cerrar' : 'Abrir' }}
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div id="panel-editar-periodo" class="hidden mt-6 rounded-2xl border border-slate-200 bg-slate-50/60 p-5">
                            <div class="flex items-center justify-between gap-3 mb-4">
                                <div>
                                    <h4 class="text-sm font-black text-slate-800">Editar periodo</h4>
                                    <p id="editar-periodo-titulo" class="text-[11px] text-slate-500 mt-0.5">-</p>
                                </div>
                                <button type="button" onclick="cerrarEditarPeriodo()" class="text-slate-400 hover:text-slate-600 font-bold text-xs">Cerrar ×</button>
                            </div>
                            <form id="form-editar-periodo" method="POST" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-12">
                                @csrf
                                <input type="hidden" name="id_periodo" id="editar-periodo-id" />
                                <div class="lg:col-span-4">
                                    <label class="block text-[10px] font-bold text-slate-600 uppercase mb-1">Descripción</label>
                                    <input type="text" name="descripcion" id="editar-periodo-descripcion" maxlength="200" class="w-full text-xs rounded-xl border border-slate-200 p-2.5 bg-white outline-none focus:border-[#00594E]" placeholder="Opcional" />
                                </div>
                                <div class="lg:col-span-2">
                                    <label class="block text-[10px] font-bold text-slate-600 uppercase mb-1">Fecha inicio</label>
                                    <input type="date" name="fecha_inicio" id="editar-periodo-inicio" class="w-full text-xs rounded-xl border border-slate-200 p-2.5 bg-white outline-none focus:border-[#00594E]" required />
                                </div>
                                <div class="lg:col-span-2">
                                    <label class="block text-[10px] font-bold text-slate-600 uppercase mb-1">Fecha fin</label>
                                    <input type="date" name="fecha_fin" id="editar-periodo-fin" class="w-full text-xs rounded-xl border border-slate-200 p-2.5 bg-white outline-none focus:border-[#00594E]" required />
                                </div>
                                <div class="lg:col-span-2">
                                    <label class="block text-[10px] font-bold text-slate-600 uppercase mb-1">Estado</label>
                                    <select name="estado" id="editar-periodo-estado" class="w-full text-xs rounded-xl border border-slate-200 p-2.5 bg-white outline-none focus:border-[#00594E]" required>
                                        <option value="ABIERTO">ABIERTO</option>
                                        <option value="CERRADO">CERRADO</option>
                                    </select>
                                </div>
                                <div class="lg:col-span-2 flex items-end">
                                    <button type="submit" class="w-full bg-[#00594E] text-white rounded-xl py-2.5 text-xs font-bold hover:brightness-110 transition shadow-md shadow-[#00594E]/20">Guardar cambios</button>
                                </div>
                            </form>
                        </div>

                        <div id="panel-auditoria-periodo" class="hidden mt-6 rounded-2xl border border-slate-200 bg-white p-5">
                            <div class="flex items-center justify-between gap-3 mb-4">
                                <div>
                                    <h4 class="text-sm font-black text-slate-800">Auditoría del periodo</h4>
                                    <p id="auditoria-periodo-titulo" class="text-[11px] text-slate-500 mt-0.5">-</p>
                                </div>
                                <button type="button" onclick="document.getElementById('panel-auditoria-periodo').classList.add('hidden')" class="text-slate-400 hover:text-slate-600 font-bold text-xs">Cerrar ×</button>
                            </div>
                            <div id="auditoria-periodo-lista" class="space-y-2 text-xs">
                                <div class="text-slate-400 text-center py-4">Cargando auditoría...</div>
                            </div>
                        </div>
                    </div>

                    <div class="panel-card rounded-3xl p-6">
                        <h3 class="text-lg font-bold text-slate-800 mb-4">Abrir Nuevo Periodo</h3>
                        <form method="POST" action="{{ route('admin.periodos.store') }}" class="space-y-4">
                            @csrf
                            <div>
                                <label class="block text-xs font-bold text-slate-600 mb-1">Sistema</label>
                                <select name="sistema" class="w-full text-sm rounded-xl border border-slate-200 p-3 bg-white" required>
                                    <option value="RENDIMIENTO_LABORAL">Rendimiento Laboral (RL)</option>
                                    <option value="ACUERDO_GESTION">Acuerdos de Gestión (AG)</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-600 mb-1">Año</label>
                                <input type="number" name="anio" value="{{ date('Y') }}" class="w-full text-sm rounded-xl border border-slate-200 p-3 bg-white" required />
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-600 mb-1">Semestre</label>
                                <select name="semestre" class="w-full text-sm rounded-xl border border-slate-200 p-3 bg-white" required>
                                    <option value="1">Semestre 1</option>
                                    <option value="2">Semestre 2</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-600 mb-1">Fecha Inicio</label>
                                <input type="date" name="fecha_inicio" class="w-full text-sm rounded-xl border border-slate-200 p-3 bg-white" required />
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-600 mb-1">Fecha Fin</label>
                                <input type="date" name="fecha_fin" class="w-full text-sm rounded-xl border border-slate-200 p-3 bg-white" required />
                            </div>
                            <button type="submit" class="w-full bg-[#00594E] text-white rounded-2xl py-3 font-bold hover:brightness-110 transition shadow-lg shadow-[#00594E]/25">Abrir Periodo</button>
                        </form>
                    </div>
                </div>

                <div class="panel-card rounded-3xl p-6">
                    <div>
                        <h3 class="text-lg font-bold text-slate-800">Crear Periodo Parcial</h3>
                        <p class="text-xs text-slate-500 mt-1 mb-4">Registra un periodo parcial para un funcionario que no estuvo desde el inicio del semestre (ingreso o traslado).</p>
                    </div>
                    <form method="POST" action="{{ route('admin.periodos-parciales.store') }}" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-12">
                        @csrf
                        <div class="lg:col-span-3">
                            <label class="block text-xs font-bold text-slate-600 mb-1">Funcionario</label>
                            <select name="id_vinc_funcionario" class="w-full text-sm rounded-xl border border-slate-200 p-3 bg-white" required>
                                <option value="">Seleccione...</option>
                                @foreach($funcionariosParaPeriodoParcial as $fp)
                                    <option value="{{ $fp->id_vinculacion }}">{{ $fp->nombres }} {{ $fp->apellidos }} — {{ $fp->sistema_evaluacion }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="lg:col-span-2">
                            <label class="block text-xs font-bold text-slate-600 mb-1">Periodo base</label>
                            <select name="id_periodo" class="w-full text-sm rounded-xl border border-slate-200 p-3 bg-white" required>
                                @foreach($periodos->where('estado', 'ABIERTO') as $p)
                                    <option value="{{ $p->id_periodo }}">{{ $p->sistema }} {{ $p->anio }}-{{ str_pad($p->semestre, 2, '0', STR_PAD_LEFT) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="lg:col-span-2">
                            <label class="block text-xs font-bold text-slate-600 mb-1">Fecha inicio</label>
                            <input type="date" name="fecha_inicio" class="w-full text-sm rounded-xl border border-slate-200 p-3 bg-white" required />
                        </div>
                        <div class="lg:col-span-2">
                            <label class="block text-xs font-bold text-slate-600 mb-1">Fecha fin</label>
                            <input type="date" name="fecha_fin" class="w-full text-sm rounded-xl border border-slate-200 p-3 bg-white" required />
                        </div>
                        <div class="lg:col-span-3">
                            <label class="block text-xs font-bold text-slate-600 mb-1">Nombre o referencia</label>
                            <input type="text" name="referencia" maxlength="200" class="w-full text-sm rounded-xl border border-slate-200 p-3 bg-white" placeholder="Identifica el funcionario asociado" />
                        </div>
                        <div class="sm:col-span-2 lg:col-span-12">
                            <button type="submit" class="w-full bg-[#B5A160] text-white rounded-xl py-3 font-bold hover:brightness-110 transition shadow-lg shadow-[#B5A160]/25">Crear Periodo Parcial</button>
                        </div>
                    </form>

                    @if($periodosParciales->isNotEmpty())
                    <div class="mt-6 overflow-x-auto">
                        <table class="w-full text-sm text-left text-slate-500">
                            <thead class="text-xs uppercase bg-[#EAF2EF] text-[#00594E] font-bold">
                                <tr>
                                    <th class="px-4 py-3">Funcionario</th>
                                    <th class="px-4 py-3">Referencia</th>
                                    <th class="px-4 py-3">Periodo</th>
                                    <th class="px-4 py-3">Inicio</th>
                                    <th class="px-4 py-3">Fin</th>
                                    <th class="px-4 py-3">Estado</th>
                                    <th class="px-4 py-3 text-right">Acción</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach($periodosParciales as $pp)
                                <tr>
                                    <td class="px-4 py-3 font-semibold text-slate-800">{{ $pp->funcionario_nombres }} {{ $pp->funcionario_apellidos }}<div class="text-[11px] font-normal text-slate-400">{{ $pp->funcionario_cargo }} - {{ $pp->funcionario_area }}</div></td>
                                    <td class="px-4 py-3">{{ $pp->referencia ?? '-' }}</td>
                                    <td class="px-4 py-3">{{ $pp->sistema }} {{ $pp->anio }}-{{ str_pad($pp->semestre, 2, '0', STR_PAD_LEFT) }}</td>
                                    <td class="px-4 py-3 text-xs">{{ $pp->fecha_inicio }}</td>
                                    <td class="px-4 py-3 text-xs">{{ $pp->fecha_fin }}</td>
                                    <td class="px-4 py-3">
                                        <span class="px-2.5 py-1 text-[10px] font-bold uppercase rounded-full @if($pp->estado === 'ABIERTO') bg-green-50 text-green-700 @else bg-gray-100 text-gray-500 @endif">{{ $pp->estado }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <form method="POST" action="{{ route('admin.periodos-parciales.toggle', $pp->id_periodo_parcial) }}">
                                            @csrf
                                            <button type="submit" class="px-3 py-1.5 rounded-lg border text-xs font-bold hover:bg-slate-50 transition">{{ $pp->estado === 'ABIERTO' ? 'Cerrar' : 'Abrir' }}</button>
                                        </form>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @endif
                </div>
            </section>

            <!-- SECTION: PONDERACIONES (Admin Only) -->
            <section id="section-ponderaciones" class="section-content hidden space-y-6">
                <div class="panel-card rounded-3xl p-6">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between mb-6">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-[0.22em] text-[#00594E]">Configuración</p>
                            <h2 class="text-2xl sm:text-3xl font-black text-slate-900">Ponderación de componentes</h2>
                            <p class="text-sm text-slate-500 mt-1">Define el porcentaje de cada componente por sistema de evaluación.</p>
                        </div>
                        <span class="text-[10px] font-bold uppercase rounded-full px-3 py-1.5 bg-[#EAF2EF] text-[#00594E]">Total requerido: 100%</span>
                    </div>
                    <div class="grid gap-6 md:grid-cols-2">
                        @foreach($ponderaciones as $pond)
                        @php
                            $totalPonderacion = (float) $pond->peso_compromisos + (float) $pond->peso_competencias + (float) $pond->peso_docencia + (float) $pond->peso_investigacion + (float) $pond->peso_proyeccion_social;
                            $labelSistema = $pond->sistema === 'RENDIMIENTO_LABORAL' ? 'Rendimiento Laboral (RL)' : 'Acuerdos de Gestión (AG)';
                            $tieneEjeMisional = $pond->sistema === 'ACUERDO_GESTION';
                        @endphp
                        <div class="rounded-2xl border border-slate-100 bg-slate-50/50 p-6 flex flex-col justify-between">
                            <div>
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <span class="px-2.5 py-1 text-[10px] font-bold uppercase rounded-full bg-[#EAF2EF] text-[#00594E]">
                                            {{ $pond->sistema }}
                                        </span>
                                        <h3 class="text-xl font-bold text-slate-800 mt-3">{{ $labelSistema }}</h3>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-[10px] uppercase font-bold text-slate-400">Total</p>
                                        <p class="text-lg font-black {{ abs($totalPonderacion - 100) < 0.01 ? 'text-[#00594E]' : 'text-red-600' }}">{{ number_format($totalPonderacion, 1) }}%</p>
                                    </div>
                                </div>
                                <div class="mt-5 space-y-3 text-sm">
                                    <div>
                                        <div class="flex justify-between py-1">
                                            <span class="text-slate-500">Compromisos</span>
                                            <span class="font-bold text-slate-800">{{ $pond->peso_compromisos }}%</span>
                                        </div>
                                        <div class="h-2 rounded-full bg-slate-200 overflow-hidden"><div class="h-full bg-[#00594E]" style="width: {{ min(100, max(0, (float) $pond->peso_compromisos)) }}%"></div></div>
                                    </div>
                                    <div>
                                        <div class="flex justify-between py-1">
                                            <span class="text-slate-500">Competencias</span>
                                            <span class="font-bold text-slate-800">{{ $pond->peso_competencias }}%</span>
                                        </div>
                                        <div class="h-2 rounded-full bg-slate-200 overflow-hidden"><div class="h-full bg-[#B5A160]" style="width: {{ min(100, max(0, (float) $pond->peso_competencias)) }}%"></div></div>
                                    </div>
                                    @if($tieneEjeMisional)
                                    <div>
                                        <div class="flex justify-between py-1">
                                            <span class="text-slate-500">Docencia</span>
                                            <span class="font-bold text-slate-800">{{ $pond->peso_docencia }}%</span>
                                        </div>
                                        <div class="h-2 rounded-full bg-slate-200 overflow-hidden"><div class="h-full bg-slate-700" style="width: {{ min(100, max(0, (float) $pond->peso_docencia)) }}%"></div></div>
                                    </div>
                                    <div>
                                        <div class="flex justify-between py-1">
                                            <span class="text-slate-500">Horas de Investigación</span>
                                            <span class="font-bold text-slate-800">{{ $pond->peso_investigacion }}%</span>
                                        </div>
                                        <div class="h-2 rounded-full bg-slate-200 overflow-hidden"><div class="h-full bg-slate-500" style="width: {{ min(100, max(0, (float) $pond->peso_investigacion)) }}%"></div></div>
                                    </div>
                                    <div>
                                        <div class="flex justify-between py-1">
                                            <span class="text-slate-500">Proyección Social</span>
                                            <span class="font-bold text-slate-800">{{ $pond->peso_proyeccion_social }}%</span>
                                        </div>
                                        <div class="h-2 rounded-full bg-slate-200 overflow-hidden"><div class="h-full bg-slate-400" style="width: {{ min(100, max(0, (float) $pond->peso_proyeccion_social)) }}%"></div></div>
                                    </div>
                                    @endif
                                </div>
                            </div>
                            <form method="POST" action="{{ route('admin.ponderaciones.update') }}" class="mt-6 space-y-3 bg-white p-4 rounded-xl border">
                                @csrf
                                <input type="hidden" name="sistema" value="{{ $pond->sistema }}" />
                                @unless($tieneEjeMisional)
                                    <input type="hidden" name="peso_docencia" value="0" />
                                    <input type="hidden" name="peso_investigacion" value="0" />
                                    <input type="hidden" name="peso_proyeccion_social" value="0" />
                                @endunless
                                <div class="grid {{ $tieneEjeMisional ? 'grid-cols-2 sm:grid-cols-3' : 'grid-cols-2' }} gap-2">
                                    <div>
                                        <label class="text-[10px] font-bold text-slate-500 block mb-0.5">Compromisos</label>
                                        <input type="number" name="peso_compromisos" min="0" max="100" step="0.1" value="{{ $pond->peso_compromisos }}" class="w-full text-xs rounded border p-1.5" required />
                                    </div>
                                    <div>
                                        <label class="text-[10px] font-bold text-slate-500 block mb-0.5">Competencias</label>
                                        <input type="number" name="peso_competencias" min="0" max="100" step="0.1" value="{{ $pond->peso_competencias }}" class="w-full text-xs rounded border p-1.5" required />
                                    </div>
                                    @if($tieneEjeMisional)
                                    <div>
                                        <label class="text-[10px] font-bold text-slate-500 block mb-0.5">Docencia</label>
                                        <input type="number" name="peso_docencia" min="0" max="100" step="0.1" value="{{ $pond->peso_docencia }}" class="w-full text-xs rounded border p-1.5" required />
                                    </div>
                                    <div>
                                        <label class="text-[10px] font-bold text-slate-500 block mb-0.5">Horas de Investigación</label>
                                        <input type="number" name="peso_investigacion" min="0" max="100" step="0.1" value="{{ $pond->peso_investigacion }}" class="w-full text-xs rounded border p-1.5" required />
                                    </div>
                                    <div>
                                        <label class="text-[10px] font-bold text-slate-500 block mb-0.5">Proyección Social</label>
                                        <input type="number" name="peso_proyeccion_social" min="0" max="100" step="0.1" value="{{ $pond->peso_proyeccion_social }}" class="w-full text-xs rounded border p-1.5" required />
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
                            <p class="text-sm text-slate-500 mt-1">Radicados de los evaluados, decisiones y motivaciones.</p>
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
                            <p class="text-sm text-slate-500 mt-1">RL y AG 0-70 (No satisfactorio), primer semestre.</p>
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
                            <p class="text-sm text-slate-500 mt-1">Notificaciones de calificación con renuencia y los testigos registrados.</p>
                        </div>
                    </div>
                    <div id="renuencias-admin-lista" class="grid gap-4 lg:grid-cols-2">
                        <div class="py-10 text-center text-slate-500 text-xs">Cargando renuencias...</div>
                    </div>
                </div>
            </section>

            <!-- SECTION: TRASLADOS (Admin Only) -->
            <section id="section-traslados" class="section-content hidden space-y-6">
                <div class="grid gap-6 lg:grid-cols-[1.1fr_0.9fr] items-start">
                    <div class="panel-card rounded-3xl p-6">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="material-symbols-outlined text-[#00594E]">swap_horiz</span>
                            <h2 class="text-2xl sm:text-3xl font-black text-slate-900">Traslados</h2>
                        </div>
                        <p class="text-sm text-slate-500 mb-4">Registra el traslado de un funcionario a otra dependencia con cambio de evaluador.</p>
                        <form method="POST" action="{{ route('admin.traslados.store') }}" class="space-y-3">
                            @csrf
                            <div>
                                <label class="block text-[10px] font-bold text-slate-600 uppercase mb-1">Funcionario a trasladar (Vinculación)</label>
                                <input type="search" id="buscar-funcionario-traslado" oninput="filtrarOpcionesAsignacion('buscar-funcionario-traslado', 'select-funcionario-traslado')" class="mb-2 w-full text-xs rounded-xl border border-slate-200 p-2.5 bg-white outline-none focus:border-[#00594E]" placeholder="Buscar funcionario por nombre o cargo" />
                                <select name="id_vinc_funcionario" id="select-funcionario-traslado" onchange="mostrarEvaluadorActualTraslado()" class="w-full text-xs rounded-xl border border-slate-200 p-2.5 bg-white" required>
                                    <option value="">Selecciona un funcionario</option>
                                    @foreach($empleados as $e)
                                        @if($e->id_vinculacion)
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
                                        @if($e->id_vinculacion && $e->es_evaluador)
                                            <option value="{{ $e->id_vinculacion }}">{{ $e->nombres }} {{ $e->apellidos }} - {{ $e->nombre_cargo }}</option>
                                        @endif
                                    @endforeach
                                </select>
                            </div>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-600 uppercase mb-1">Fecha del traslado</label>
                                    <input type="date" name="fecha_traslado" class="w-full text-xs rounded-xl border border-slate-200 p-2.5 bg-white outline-none focus:border-[#00594E]" required />
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-600 uppercase mb-1">Resolución (opcional)</label>
                                    <input type="text" name="resolucion" class="w-full text-xs rounded-xl border border-slate-200 p-2.5 bg-white outline-none focus:border-[#00594E]" placeholder="Ej. RES-2026-0123" />
                                </div>
                            </div>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-600 uppercase mb-1">Nueva dependencia (área)</label>
                                    <input type="text" name="area_nuevo" class="w-full text-xs rounded-xl border border-slate-200 p-2.5 bg-white outline-none focus:border-[#00594E]" placeholder="Nueva área" />
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-600 uppercase mb-1">Nuevo cargo</label>
                                    <input type="text" name="cargo_nuevo" class="w-full text-xs rounded-xl border border-slate-200 p-2.5 bg-white outline-none focus:border-[#00594E]" placeholder="Nuevo cargo" />
                                </div>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-600 uppercase mb-1">Reemplaza a (opcional — Vinculación)</label>
                                <input type="search" id="buscar-reemplazado-traslado" oninput="filtrarOpcionesAsignacion('buscar-reemplazado-traslado', 'select-reemplazado-traslado')" class="mb-2 w-full text-xs rounded-xl border border-slate-200 p-2.5 bg-white outline-none focus:border-[#00594E]" placeholder="Buscar reemplazo por nombre o cargo" />
                                <select name="id_vinc_reemplazado" id="select-reemplazado-traslado" class="w-full text-xs rounded-xl border border-slate-200 p-2.5 bg-white outline-none focus:border-[#00594E]">
                                    <option value="">Ninguno (puesto nuevo)</option>
                                    @foreach($vinculacionesReemplazo as $vr)
                                        <option value="{{ $vr->id_vinculacion }}">{{ $vr->nombres }} {{ $vr->apellidos }} - {{ $vr->cargo }}{{ $vr->activa ? '' : ' (inactivo)' }}</option>
                                    @endforeach
                                </select>
                                <p class="text-[10px] text-slate-400 mt-1">Opcional. Titular del puesto que ocupará el trasladado (suele estar retirado). Si lo indicas, sus compromisos del semestre se copian a la evaluación parcial del nuevo evaluador y quedan <b>editables</b> hasta firmar la concertación.</p>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-600 uppercase mb-1">Motivo (opcional)</label>
                                <input type="text" name="motivo" class="w-full text-xs rounded-xl border border-slate-200 p-2.5 bg-white outline-none focus:border-[#00594E]" placeholder="Motivo del traslado" />
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-600 uppercase mb-1">Nombre o referencia del funcionario para la evaluación parcial</label>
                                <input type="text" name="referencia" maxlength="200" class="w-full text-xs rounded-xl border border-slate-200 p-2.5 bg-white outline-none focus:border-[#00594E]" placeholder="Ej. Juan Pérez — Traslado a Secretaría General" />
                            </div>
                            <button type="submit" class="w-full bg-[#B5A160] text-white rounded-xl py-2.5 text-xs font-bold hover:brightness-110 transition shadow-md shadow-[#B5A160]/20">Registrar traslado</button>
                        </form>
                    </div>

                    <div class="panel-card rounded-3xl p-6">
                        <div class="flex items-end justify-between gap-4 mb-4">
                            <div>
                                <h3 class="text-lg font-bold text-slate-800">Histórico de traslados</h3>
                                <p class="text-xs text-slate-500 mt-1">Registros de cambio de dependencia y evaluador.</p>
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
                            <span class="material-symbols-outlined text-[#00594E]">swap_horiz</span>
                            <h2 class="text-2xl sm:text-3xl font-black text-slate-900">Delegaciones de funciones del cargo</h2>
                        </div>
                        <p class="text-sm text-slate-500 mb-4">Registra que un evaluador (delegante, titular del cargo) ceda temporalmente el ejercicio de su rol de evaluador a otro funcionario (delegado). La delegación no altera la titularidad ni la responsabilidad de firma final del titular cuando retorna.</p>
                        <form method="POST" action="{{ route('admin.delegaciones.store') }}" class="space-y-3">
                            @csrf
                            <div>
                                <label class="block text-[10px] font-bold text-slate-600 uppercase mb-1">Delegante — titular del cargo (Vinculación)</label>
                                <input type="search" id="buscar-delegante" oninput="filtrarOpcionesAsignacion('buscar-delegante', 'select-delegante')" class="mb-2 w-full text-xs rounded-xl border border-slate-200 p-2.5 bg-white outline-none focus:border-[#00594E]" placeholder="Buscar evaluador por nombre o cargo" />
                                <select name="id_vinc_delegante" id="select-delegante" class="w-full text-xs rounded-xl border border-slate-200 p-2.5 bg-white" required>
                                    <option value="">Selecciona un evaluador</option>
                                    @foreach($evaluadoresDelegacion as $ed)
                                        <option value="{{ $ed->id_vinculacion }}">{{ $ed->nombres }} {{ $ed->apellidos }} - {{ $ed->cargo }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-600 uppercase mb-1">Delegado (Vinculación)</label>
                                <input type="search" id="buscar-delegado" oninput="filtrarOpcionesAsignacion('buscar-delegado', 'select-delegado')" class="mb-2 w-full text-xs rounded-xl border border-slate-200 p-2.5 bg-white outline-none focus:border-[#00594E]" placeholder="Buscar evaluador por nombre o cargo" />
                                <select name="id_vinc_delegado" id="select-delegado" class="w-full text-xs rounded-xl border border-slate-200 p-2.5 bg-white" required>
                                    <option value="">Selecciona un evaluador</option>
                                    @foreach($delegadosDisponibles as $dd)
                                        <option value="{{ $dd->id_vinculacion }}">{{ $dd->nombres }} {{ $dd->apellidos }} - {{ $dd->cargo }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-600 uppercase mb-1">Fecha inicio (vigencia)</label>
                                    <input type="date" name="fecha_inicio" class="w-full text-xs rounded-xl border border-slate-200 p-2.5 bg-white outline-none focus:border-[#00594E]" required />
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-600 uppercase mb-1">Fecha fin (vigencia)</label>
                                    <input type="date" name="fecha_fin" class="w-full text-xs rounded-xl border border-slate-200 p-2.5 bg-white outline-none focus:border-[#00594E]" required />
                                </div>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-600 uppercase mb-1">Motivo (opcional)</label>
                                <input type="text" name="motivo" maxlength="500" class="w-full text-xs rounded-xl border border-slate-200 p-2.5 bg-white outline-none focus:border-[#00594E]" placeholder="Ej. Vacaciones, comisión, licencia" />
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
        </main>
    </div>
</div>

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
    };
</script>
@vite('resources/js/dashboards/admin.js')
@endsection
