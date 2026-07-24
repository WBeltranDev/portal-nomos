@extends('layouts.app')

@section('content')
<style>
    body {
        overflow-x: hidden;
        min-height: 100vh;
        margin: 0;
        background: linear-gradient(180deg, #f4f7f5 0%, #eef3f0 100%);
        padding-top: 0;
    }

    .panel-shell {
        display: grid;
        grid-template-rows: 4.25rem 1fr;
        height: 100vh;
        width: 100%;
        margin: 0;
    }

    .app-header {
        position: relative;
        z-index: 40;
        background: linear-gradient(135deg, #00352e 0%, #00594E 45%, #B5A160 100%);
        color: white;
        box-shadow: 0 10px 30px rgba(0, 89, 78, 0.22);
        border: 0;
        margin: 0;
        top: 0;
    }

    .profile-menu {
        position: absolute;
        top: calc(100% + 0.75rem);
        right: 0;
        width: 18rem;
        background: white;
        border: 1px solid rgba(15, 23, 42, 0.08);
        border-radius: 1rem;
        box-shadow: 0 24px 50px rgba(15, 23, 42, 0.18);
        display: none;
    }

    .profile-menu.open {
        display: block;
    }

    .sidebar-link.active {
        background: #e6f2f0;
        color: #00594E;
        font-weight: 700;
        border-left: 4px solid #B5A160;
    }

    .panel-card {
        background: rgba(255, 255, 255, 0.85);
        backdrop-filter: blur(12px);
        border: 1px solid rgba(15, 23, 42, 0.08);
        box-shadow: 0 12px 40px rgba(15, 23, 42, 0.08);
    }
</style>

<div class="panel-shell">
    <!-- Header -->
    <header class="app-header flex items-center justify-between px-4 sm:px-6 lg:px-8">
        <div class="flex items-center gap-3 min-w-0">
            <button onclick="toggleSidebar()" class="lg:hidden p-2 rounded-lg hover:bg-white/10">
                <span class="material-symbols-outlined">menu</span>
            </button>
            <div class="hidden sm:flex flex-col">
                <span class="text-[10px] sm:text-xs font-bold uppercase tracking-[0.18em] text-[#B5A160]">Unitrópico</span>
                <span class="text-xs sm:text-sm font-semibold text-white/90">Sistema de Evaluación del Desempeño</span>
            </div>
            <div class="sm:hidden text-xs font-semibold text-white/90">Unitrópico</div>
        </div>

        <div class="flex items-center gap-3 sm:gap-4">
            <div class="hidden sm:flex flex-col items-end text-right leading-tight">
                <span class="text-sm font-semibold text-white">{{ $usuario['nombres'] }} {{ $usuario['apellidos'] }}</span>
                <span class="text-[10px] uppercase tracking-[0.18em] text-[#B5A160] font-bold">{{ $rolActivo }}</span>
            </div>
            <div class="relative">
                <button type="button" onclick="toggleProfileMenu()" class="flex items-center gap-3 rounded-full pl-1 pr-3 py-1.5 hover:bg-white/10 transition">
                    <div class="w-10 h-10 rounded-full bg-[#B5A160] text-white font-black flex items-center justify-center shadow-lg">
                        {{ strtoupper(substr($usuario['nombres'] ?? 'U', 0, 1) . substr($usuario['apellidos'] ?? 'X', 0, 1)) }}
                    </div>
                    <span class="material-symbols-outlined text-white/80 text-base hidden sm:block">expand_more</span>
                </button>

                <div id="profile-menu" class="profile-menu p-2">
                    <div class="px-4 py-3 border-b border-slate-100">
                        <p class="text-sm font-bold text-slate-800">{{ $usuario['nombres'] }} {{ $usuario['apellidos'] }}</p>
                        <p class="text-xs text-slate-500">{{ $usuario['correo'] }}</p>
                        <p class="text-[10px] uppercase tracking-[0.18em] text-[#00594E] font-bold mt-1">{{ $rolActivo }}</p>
                    </div>
                    @if(count($usuario['roles'] ?? []) > 1)
                    <a href="/seleccionar-rol" class="w-full text-left px-4 py-3 rounded-lg hover:bg-slate-50 text-sm font-medium text-slate-700 flex items-center gap-2">
                        <span class="material-symbols-outlined text-base">swap_horiz</span>
                        Cambiar perfil
                    </a>
                    @endif
                    <button onclick="openPasswordModal()" class="w-full text-left px-4 py-3 rounded-lg hover:bg-slate-50 text-sm font-medium text-slate-700 flex items-center gap-2">
                        <span class="material-symbols-outlined text-base">lock_reset</span>
                        Cambiar contraseña
                    </button>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="w-full text-left px-4 py-3 rounded-lg hover:bg-red-50 text-sm font-medium text-red-600 flex items-center gap-2">
                            <span class="material-symbols-outlined text-base">logout</span>
                            Cerrar sesión
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </header>

    <div class="flex min-h-0 overflow-hidden">
        <!-- Sidebar -->
        <aside id="sidebar-menu" class="fixed lg:relative z-40 inset-y-0 left-0 w-64 -translate-x-full lg:translate-x-0 transition-transform duration-300 ease-in-out bg-white border-r border-slate-200 flex flex-col justify-between">
            <nav class="p-2.5 pt-1 space-y-1 overflow-y-auto">
                @if (in_array($rolActivo, ['admin', 'evaluador'], true))
                <button class="sidebar-link w-full @if($rolActivo !== 'admin') active @endif flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm text-slate-700 transition" onclick="navegarMenu(this, 'usuarios')">
                    <span class="material-symbols-outlined">group</span>
                    Usuarios
                </button>
                @endif

                @if ($rolActivo === 'admin')
                <button class="sidebar-link w-full flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm text-slate-700 transition" onclick="navegarMenu(this, 'empleados')">
                    <span class="material-symbols-outlined">badge</span>
                    Empleados
                </button>
                <button class="sidebar-link w-full flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm text-slate-700 transition" onclick="navegarMenu(this, 'periodos')">
                    <span class="material-symbols-outlined">calendar_today</span>
                    Periodos
                </button>
                <button class="sidebar-link w-full flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm text-slate-700 transition" onclick="navegarMenu(this, 'ponderaciones')">
                    <span class="material-symbols-outlined">settings</span>
                    Ponderaciones
                </button>
                @endif

                @if ($rolActivo !== 'instancia_externa')
                <button class="sidebar-link w-full @if($rolActivo !== 'admin') active @endif flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm text-slate-700 transition" onclick="navegarMenu(this, '{{ $rolActivo === 'evaluador' ? 'evaluaciones-evaluador' : 'evaluaciones' }}')">
                    <span class="material-symbols-outlined">fact_check</span>
                    Evaluaciones
                </button>
                <button class="sidebar-link w-full flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm text-slate-700 transition" onclick="navegarMenu(this, 'reportes')">
                    <span class="material-symbols-outlined">description</span>
                    Exportar PDF
                </button>
                @endif

                @if ($rolActivo === 'instancia_externa')
                <button class="sidebar-link w-full active flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm text-slate-700 transition" onclick="navegarMenu(this, 'instancia-externa')">
                    <span class="material-symbols-outlined">school</span>
                    Notas Componente Académico
                </button>
                @endif
            </nav>

            <div class="p-4 border-t border-slate-100">
                <div class="rounded-xl bg-[#EAF2EF] p-4">
                    <p class="text-xs font-bold text-[#00594E] uppercase tracking-[0.18em]">Sesión activa</p>
                    <p class="text-sm font-semibold text-slate-800 mt-1">{{ $usuario['nombres'] }} {{ $usuario['apellidos'] }}</p>
                    <p class="text-xs text-slate-500">{{ $usuario['correo'] }}</p>
                </div>
            </div>
        </aside>

        <!-- Main Content Grid -->
        <main class="flex-1 min-w-0 p-4 sm:p-6 lg:p-8 overflow-y-auto">
            @if(session('success_periodo') || session('success_ponderacion') || session('success_asignacion') || session('success_import') || session('success_firma'))
                <div class="mb-6 p-4 bg-green-50 border border-green-200 text-green-700 rounded-2xl flex items-center gap-3 text-sm">
                    <span class="material-symbols-outlined">check_circle</span>
                    <p>{{ session('success_periodo') ?? session('success_ponderacion') ?? session('success_asignacion') ?? session('success_import') ?? session('success_firma') }}</p>
                </div>
            @endif

            @if($errors->any())
                <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-700 rounded-2xl flex items-start gap-3 text-sm">
                    <span class="material-symbols-outlined mt-0.5">error</span>
                    <div class="space-y-1">
                        @foreach ($errors->all() as $error)
                            <p>{{ $error }}</p>
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($rolActivo === 'admin')
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
                                    <p id="empleado-cargo" class="text-xs text-slate-500 mt-0.5">Ver�s sus datos ampliados</p>
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
                            <h3 class="text-lg font-bold text-slate-800 mb-2">Asignar Evaluado</h3>
                            <p class="text-xs text-slate-500 mb-4">Vincula un funcionario a su evaluador para que el evaluador pueda abrir la evaluación.</p>
                            <form method="POST" action="{{ route('admin.asignaciones.store') }}" class="space-y-3">
                                @csrf
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-600 uppercase mb-1">Evaluado (Vinculación)</label>
                                    <input type="search" id="buscar-evaluado-asignacion" oninput="filtrarOpcionesAsignacion('buscar-evaluado-asignacion', 'select-evaluado-asignacion')" class="mb-2 w-full text-xs rounded-xl border border-slate-200 p-2.5 bg-white outline-none focus:border-[#00594E]" placeholder="Buscar evaluado por nombre o cargo" />
                                    <select name="id_vinc_evaluado" id="select-evaluado-asignacion" class="w-full text-xs rounded-xl border border-slate-200 p-2.5 bg-white" required>
                                        <option value="">Selecciona un evaluado</option>
                                        @foreach($empleados as $e)
                                            @if($e->id_vinculacion)
                                                <option value="{{ $e->id_vinculacion }}">{{ $e->nombres }} {{ $e->apellidos }} - {{ $e->nombre_cargo }}</option>
                                            @endif
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-600 uppercase mb-1">Evaluador (Vinculaci�n)</label>
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
                                <button type="submit" class="w-full bg-[#B5A160] text-white rounded-xl py-2.5 text-xs font-bold hover:brightness-110 transition shadow-md shadow-[#B5A160]/20">Asignar evaluado</button>
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
                                        <td class="px-4 py-3">
                                            <span class="px-2.5 py-1 text-[10px] font-bold uppercase rounded-full @if($p->estado === 'ABIERTO') bg-green-50 text-green-700 @else bg-gray-100 text-gray-500 @endif">
                                                {{ $p->estado }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-right">
                                            <form method="POST" action="{{ route('admin.periodos.toggle', $p->id_periodo) }}">
                                                @csrf
                                                <button type="submit" class="px-3 py-1.5 rounded-lg border text-xs font-bold hover:bg-slate-50 transition">
                                                    {{ $p->estado === 'ABIERTO' ? 'Cerrar' : 'Abrir' }}
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
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
            @endif
            @if ($rolActivo === 'evaluador')
            <section id="section-usuarios-evaluador" class="section-content hidden space-y-6">
                <div class="grid gap-6 xl:grid-cols-[1fr_1.05fr] items-start">
                    <div class="panel-card rounded-3xl p-6">
                        <div class="flex items-start justify-between gap-4 mb-4">
                            <div>
                                <p class="text-xs font-bold uppercase tracking-[0.22em] text-[#00594E]">Usuarios</p>
                                <h2 class="text-xl font-black text-slate-900">Personas a cargo</h2>
                            </div>
                            <span class="text-[10px] font-bold px-2.5 py-1 rounded-full bg-slate-100 text-slate-500">{{ $evaluadosDisponibles->count() }} disponibles</span>
                        </div>
                        <p class="text-xs text-slate-500 mb-4">Selecciona una tarjeta para ver toda la información del perfil y abrir la Evaluación.</p>
                        <div class="grid gap-3">
                            @forelse($evaluadosDisponibles as $persona)
                                <button type="button" class="evaluado-card text-left p-4 rounded-2xl border border-slate-200 bg-white cursor-pointer hover:border-[#00594E] transition" onclick="seleccionarPersonaEvaluador(this, @js($persona))">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <h4 class="font-bold text-slate-900 text-sm leading-snug">{{ $persona->nombres }} {{ $persona->apellidos }}</h4>
                                            <p class="text-xs text-slate-500 mt-0.5">{{ $persona->cargo }} - {{ $persona->area }}</p>
                                        </div>
                                        <span class="w-10 h-10 rounded-full bg-[#00594E] text-white flex items-center justify-center text-xs font-black">{{ strtoupper(substr($persona->nombres, 0, 1) . substr($persona->apellidos, 0, 1)) }}</span>
                                    </div>
                                    <div class="flex flex-wrap gap-2 mt-3 text-[10px] font-bold uppercase">
                                        <span class="px-2 py-0.5 rounded-full bg-[#EAF2EF] text-[#00594E]">{{ $persona->sistema_evaluacion }}</span>
                                        <span class="px-2 py-0.5 rounded-full bg-slate-100 text-slate-500">{{ $persona->nivel_jerarquico }}</span>
                                    </div>
                                </button>
                            @empty
                                <div class="py-10 text-center text-slate-500 text-xs">No hay personas disponibles bajo tu cargo o área.</div>
                            @endforelse
                        </div>
                    </div>

                    <div class="space-y-6 lg:sticky lg:top-6">
                        <div class="panel-card rounded-3xl p-6">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <p class="text-xs font-bold uppercase tracking-[0.22em] text-[#00594E]">Detalle del perfil</p>
                                    <h2 id="empleado-nombre" class="text-2xl sm:text-3xl font-black text-slate-900 mt-1">Selecciona una persona</h2>
                                    <p id="empleado-cargo" class="text-sm text-slate-500 mt-1">Aquí aparecerá la información del funcionario seleccionado.</p>
                                </div>
                                <div id="empleado-avatar" class="w-14 h-14 rounded-2xl bg-slate-100 text-slate-500 flex items-center justify-center text-sm font-black">--</div>
                            </div>
                            <div class="mt-6 grid gap-3 text-sm">
                                <div class="flex justify-between gap-4 py-2 border-b border-slate-100"><span class="text-slate-500">Cédula</span><span id="empleado-documento" class="font-medium text-slate-800 text-right">-</span></div>
                                <div class="flex justify-between gap-4 py-2 border-b border-slate-100"><span class="text-slate-500">Correo</span><span id="empleado-correo" class="font-medium text-slate-800 text-right">-</span></div>
                                <div class="flex justify-between gap-4 py-2 border-b border-slate-100"><span class="text-slate-500">Área</span><span id="empleado-area" class="font-medium text-slate-800 text-right">-</span></div>
                                <div class="flex justify-between gap-4 py-2 border-b border-slate-100"><span class="text-slate-500">Cargo</span><span id="empleado-cargo-vinc" class="font-medium text-slate-800 text-right">-</span></div>
                                <div class="flex justify-between gap-4 py-2 border-b border-slate-100"><span class="text-slate-500">Tipo de vinculación</span><span id="empleado-vinculacion" class="font-medium text-slate-800 text-right">-</span></div>
                                <div class="flex justify-between gap-4 py-2 border-b border-slate-100"><span class="text-slate-500">Nivel jerárquico</span><span id="empleado-nivel" class="font-medium text-slate-800 text-right">-</span></div>
                                <div class="flex justify-between gap-4 py-2 border-b border-slate-100"><span class="text-slate-500">Sistema</span><span id="empleado-sistema" class="font-medium text-slate-800 text-right">-</span></div>
                                <div class="flex justify-between gap-4 py-2 border-b border-slate-100"><span class="text-slate-500">Fecha ingreso</span><span id="empleado-ingreso" class="font-medium text-slate-800 text-right">-</span></div>
                                <div class="flex justify-between gap-4 py-2"><span class="text-slate-500">Estado</span><span id="empleado-estado" class="font-medium text-slate-800 text-right">-</span></div>
                            </div>
                        </div>

                        <div id="panel-apertura-evaluacion" class="panel-card rounded-3xl p-6 hidden">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <p class="text-xs font-bold uppercase tracking-[0.22em] text-[#B5A160]">Abrir Evaluación</p>
                                    <h3 id="apertura-nombre" class="text-xl font-black text-slate-900 mt-1 leading-snug">Selecciona una persona</h3>
                                    <p id="apertura-detalle" class="text-xs text-slate-500">Tipo de acuerdo y período asignado automáticamente.</p>
                                </div>
                                <span id="apertura-sistema" class="text-[10px] font-black uppercase px-2.5 py-1 rounded-full bg-[#EAF2EF] text-[#00594E]">-</span>
                            </div>
                            <div class="mt-5 grid gap-3 text-xs">
                                <div class="flex justify-between gap-4 py-2 border-b border-slate-50"><span class="text-slate-500">Período</span><span id="apertura-periodo" class="text-slate-800 font-medium text-right">-</span></div>
                                <div class="flex justify-between gap-4 py-2 border-b border-slate-50"><span class="text-slate-500">Vigencia</span><span id="apertura-vigencia" class="text-slate-800 font-medium text-right">-</span></div>
                                <div class="flex justify-between gap-4 py-2 border-b border-slate-50"><span class="text-slate-500">Ciclo</span><span id="apertura-ciclo" class="text-slate-800 font-medium text-right">-</span></div>
                                <div class="flex justify-between gap-4 py-2 border-b border-slate-50"><span class="text-slate-500">Días laborados</span><span class="text-slate-800 font-medium text-right">Opcional</span></div>
                            </div>
                            <form id="form-abrir-evaluacion" method="POST" action="{{ route('evaluador.asignaciones.store') }}" class="mt-5 space-y-3">
                                @csrf
                                <input type="hidden" name="id_vinc_evaluado" id="apertura-id-vinc" />
                                <input type="hidden" name="id_periodo" id="apertura-id-periodo" />
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-600 uppercase mb-1">Tipo de ciclo</label>
                                    <select name="tipo_evaluacion" id="apertura-ciclo-select" class="w-full text-xs rounded-xl border border-slate-200 p-2.5 bg-white" required>
                                        <option value="SEMESTRE_1">Primer Semestre</option>
                                        <option value="SEMESTRE_2">Segundo Semestre</option>
                                        <option value="PARCIAL">Parcial</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-600 uppercase mb-1">D�as laborados</label>
                                    <input type="number" name="dias_laborados" class="w-full text-xs rounded-xl border border-slate-200 p-2.5 bg-white" placeholder="Opcional" />
                                </div>
                                <div id="apertura-ejes-misionales" class="hidden rounded-2xl border border-slate-100 bg-slate-50/50 p-4">
                                    <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wide mb-2">Ejes misionales adicionales</h4>
                                    <p class="text-[10px] text-slate-500 mb-3">Docencia es el eje base. Marca los ejes adicionales que tenga asignados el funcionario.</p>
                                    <div class="flex flex-col gap-2">
                                        <label class="flex items-center gap-2.5 text-xs text-slate-600 cursor-not-allowed">
                                            <input type="checkbox" checked disabled class="rounded border-slate-300 text-[#00594E] focus:ring-[#00594E]" />
                                            <span class="font-semibold text-slate-800">Docencia (eje base)</span>
                                        </label>
                                        <label class="flex items-center gap-2.5 text-xs text-slate-600 cursor-pointer">
                                            <input type="checkbox" name="investigacion" id="apertura-eje-investigacion" value="1" class="rounded border-slate-300 text-[#00594E] focus:ring-[#00594E]" />
                                            <span class="font-semibold text-slate-800">Horas de investigación</span>
                                        </label>
                                        <label class="flex items-center gap-2.5 text-xs text-slate-600 cursor-pointer">
                                            <input type="checkbox" name="proyeccion_social" id="apertura-eje-proyeccion" value="1" class="rounded border-slate-300 text-[#00594E] focus:ring-[#00594E]" />
                                            <span class="font-semibold text-slate-800">Proyección social</span>
                                        </label>
                                    </div>
                                </div>
                                <p id="apertura-aviso-periodo" class="text-[10px] text-slate-500"></p>
                                <button type="submit" class="w-full bg-[#00594E] text-white rounded-xl py-2.5 text-xs font-bold hover:brightness-110 transition shadow-md shadow-[#00594E]/20">Abrir Evaluación</button>
                            </form>
                        </div>
                    </div>
                </div>
            </section>
            @endif

            @if ($rolActivo === 'evaluador')
            <section id="section-evaluaciones-evaluador" class="section-content hidden space-y-6">
                <div class="grid gap-6 lg:grid-cols-[0.8fr_1.2fr]">
                    <div class="panel-card rounded-3xl p-6 h-fit">
                        <div class="flex items-start justify-between gap-4 mb-4">
                            <div>
                                <p class="text-xs font-bold uppercase tracking-[0.22em] text-[#00594E]">Evaluaciones</p>
                                <h2 class="text-xl font-black text-slate-900">Concertaciones por aprobar</h2>
                            </div>
                            <span class="text-[10px] font-bold px-2.5 py-1 rounded-full bg-slate-100 text-slate-500">{{ $evaluacionesEvaluador->count() }} activas</span>
                        </div>
                        <p class="text-xs text-slate-500 mb-4">Selecciona una Evaluación para redactar los compromisos del evaluado y firmar la concertación.</p>
                        <div class="space-y-3">
                            @forelse($evaluacionesEvaluador as $ev)
                                <button type="button" class="evaluacion-evaluador-card w-full text-left p-4 rounded-2xl border border-slate-200 bg-white cursor-pointer hover:border-[#00594E] transition" onclick="abrirConcertacionEvaluador(this, @js($ev))">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <h4 class="font-bold text-slate-900 text-sm leading-snug">{{ $ev->evaluado_nombres }} {{ $ev->evaluado_apellidos }}</h4>
                                            <p class="text-xs text-slate-500 mt-0.5">{{ $ev->evaluado_cargo }} - {{ $ev->evaluado_area }}</p>
                                        </div>
                                        <span class="text-[10px] font-bold uppercase px-2 py-1 rounded-full {{ $ev->evaluador_firmado ? 'bg-[#EAF2EF] text-[#00594E]' : 'bg-amber-50 text-amber-700' }}">
                                            {{ $ev->evaluador_firmado ? 'Firmó' : 'Pendiente' }}
                                        </span>
                                    </div>
                                    <div class="flex justify-between items-center mt-3">
                                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-[#EAF2EF] text-[#00594E]">
                                            {{ $ev->sistema === 'RENDIMIENTO_LABORAL' ? 'RL' : 'AG' }}
                                        </span>
                                        <span class="text-[9px] uppercase tracking-wide font-bold text-slate-400">Fase {{ $ev->fase_actual }}</span>
                                    </div>
                                </button>
                            @empty
                                <div class="py-8 text-center text-slate-500 text-xs">No tienes evaluaciones pendientes.</div>
                            @endforelse
                        </div>
                    </div>

                    <div class="space-y-6 lg:sticky lg:top-6">
                        <div id="panel-concertacion-evaluador" class="panel-card rounded-3xl p-6 hidden">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <p class="text-xs font-bold uppercase tracking-[0.22em] text-[#00594E]">Concertación</p>
                                    <h3 id="concertacion-nombre" class="text-xl font-black text-slate-900 mt-1 leading-snug">Selecciona una Evaluación</h3>
                                    <p id="concertacion-detalle" class="text-xs text-slate-500">Aquí verás las tareas y el estado de la firma del evaluado.</p>
                                </div>
                                <span id="concertacion-sistema" class="text-[10px] font-black uppercase px-2.5 py-1 rounded-full bg-[#EAF2EF] text-[#00594E]">-</span>
                            </div>

                            <div id="ejes-misionales-vista-evaluador" class="mt-4 bg-slate-50/50 p-4 rounded-2xl border border-slate-100 hidden">
                                <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wide mb-2">Ejes misionales adicionales</h4>
                                <div class="flex flex-wrap gap-2 text-[10px] font-bold uppercase">
                                    <span id="eje-vista-investigacion" class="px-2 py-1 rounded-full bg-[#EAF2EF] text-[#00594E] hidden">Horas de investigación</span>
                                    <span id="eje-vista-proyeccion" class="px-2 py-1 rounded-full bg-[#EAF2EF] text-[#00594E] hidden">Proyección social</span>
                                    <span id="eje-vista-ninguno" class="px-2 py-1 rounded-full bg-slate-100 text-slate-500 hidden">Sin ejes adicionales</span>
                                </div>
                            </div>

	                            <div class="my-6 space-y-4">
	                                <div class="flex items-center justify-between gap-4">
	                                    <h4 class="text-sm font-bold text-slate-800 flex items-center gap-2">
	                                        <span class="material-symbols-outlined text-base">format_list_bulleted</span>
	                                        Compromisos propuestos
                                    </h4>
                                    <div class="text-right">
                                        <div id="compromisos-suma-peso-evaluador" class="text-sm font-black text-[#00594E]">0% / 80%</div>
                                        <span id="compromisos-contador-evaluador" class="text-[10px] text-slate-400 font-bold">0 compromisos (mín 7, máx 10)</span>
                                    </div>
	                                </div>
	                                <div id="compromisos-lista-contenedor" class="space-y-3"></div>
	                            </div>

	                            <div class="my-6 pt-4 border-t border-slate-100 space-y-3">
	                                <div class="flex items-center justify-between gap-3">
	                                    <h4 class="text-sm font-bold text-slate-800 flex items-center gap-2">
	                                        <span class="material-symbols-outlined text-base">link</span>
	                                        Evidencias por compromiso
	                                    </h4>
	                                    <span id="evidencias-contador-evaluador" class="text-[10px] font-bold rounded-full px-2.5 py-1 bg-slate-100 text-slate-500">0 registradas</span>
	                                </div>
	                                <div id="evidencias-lista-evaluador" class="space-y-2"></div>
	                            </div>

	                            <div id="calificacion-bloque-evaluador" class="my-6 pt-4 border-t border-slate-100 space-y-3 hidden">
	                                <div class="flex items-center justify-between gap-3">
	                                    <h4 class="text-sm font-bold text-slate-800 flex items-center gap-2">
	                                        <span class="material-symbols-outlined text-base">star</span>
	                                        Calificación de compromisos
	                                    </h4>
	                                </div>
	                                <p class="text-[10px] text-slate-400 font-semibold">Escala 0-100: Deficiente 0-50 · Bajo 51-70 · Aceptable 71-80 · Alto 81-90 · Muy alto 91-100</p>
	                                <div class="flex items-center justify-between gap-3">
	                                    <span id="calificacion-mensaje-evaluador" class="hidden text-xs font-semibold"></span>
	                                    <button type="button" onclick="guardarCalificacionCompromisos()" class="bg-[#00594E] text-white px-4 py-2 rounded-xl text-xs font-bold hover:brightness-110 transition ml-auto">Guardar calificaciones</button>
	                                </div>
	                            </div>

	                            <div id="competencias-bloque-evaluador" class="my-6 pt-4 border-t border-slate-100 space-y-3 hidden">
	                                <h4 class="text-sm font-bold text-slate-800 flex items-center gap-2">
	                                    <span class="material-symbols-outlined text-base">psychology</span>
	                                    Competencias comportamentales
	                                </h4>
	                                <div id="competencias-comunes-evaluador" class="space-y-2"></div>
	                                <div id="competencias-nivel-evaluador" class="space-y-2"></div>
	                                <div class="flex items-center justify-between gap-3">
	                                    <span id="competencias-mensaje-evaluador" class="hidden text-xs font-semibold"></span>
	                                    <button type="button" onclick="guardarCalificacionCompetencias()" class="bg-[#00594E] text-white px-4 py-2 rounded-xl text-xs font-bold hover:brightness-110 transition ml-auto">Guardar competencias</button>
	                                </div>
	                            </div>

	                            <div id="resultado-bloque-evaluador" class="my-6 pt-4 border-t border-slate-100 space-y-3 hidden">
	                                <div class="flex items-center justify-between gap-3">
	                                    <h4 class="text-sm font-bold text-slate-800 flex items-center gap-2">
	                                        <span class="material-symbols-outlined text-base">military_tech</span>
	                                        Resultado de la evaluación
	                                    </h4>
	                                    <div class="flex gap-2">
	                                        <button type="button" onclick="previsualizarCalculoEvaluador()" class="bg-white border border-slate-200 text-slate-700 px-3 py-2 rounded-xl text-xs font-bold hover:border-[#00594E] transition">Ver cálculo</button>
	                                        <button type="button" onclick="calcularNotaFinalEvaluador()" class="bg-[#B5A160] text-white px-3 py-2 rounded-xl text-xs font-bold hover:brightness-110 transition">Calcular nota final</button>
	                                    </div>
	                                </div>
	                                <div id="resultado-contenido-evaluador"></div>
	                            </div>

	                            <div id="compromiso-formulario-evaluador-contenedor" class="bg-slate-50/50 p-4 rounded-2xl border border-slate-100">
                                <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wide mb-3">Nuevo Compromiso</h4>
                                <form id="form-nuevo-compromiso-evaluador" onsubmit="agregarCompromisoEvaluador(event)" class="space-y-3">
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-500 uppercase mb-0.5">Descripción del Compromiso</label>
                                        <textarea id="comp-descripcion-evaluador" class="w-full text-xs rounded-xl border border-slate-200 p-2.5 bg-white outline-none focus:border-[#00594E]" rows="2" placeholder="Describa el compromiso..." required></textarea>
                                    </div>
                                    <div class="grid grid-cols-3 gap-2 items-end">
                                        <div class="col-span-1">
                                            <label class="block text-[10px] font-bold text-slate-500 uppercase mb-0.5">Peso (1% - 15%)</label>
                                            <input type="number" id="comp-peso-evaluador" min="1" max="15" step="0.1" class="w-full text-xs rounded-xl border border-slate-200 p-2.5 bg-white outline-none focus:border-[#00594E]" required />
                                        </div>
                                        <div class="col-span-2">
                                            <label class="block text-[10px] font-bold text-slate-500 uppercase mb-0.5">Metas de Contribución</label>
                                            <input type="text" id="comp-metas-evaluador" class="w-full text-xs rounded-xl border border-slate-200 p-2.5 bg-white outline-none focus:border-[#00594E]" placeholder="Separadas por comas (ej: PDI, Manual)" required />
                                        </div>
                                    </div>
                                    <button type="submit" class="w-full bg-[#00594E] text-white py-2 rounded-xl text-xs font-bold hover:brightness-110 transition">Agregar Compromiso</button>
                                </form>
                            </div>

                            <div class="mt-6 pt-4 border-t border-slate-100 flex items-center justify-between gap-4">
                                <div class="text-xs text-slate-500 leading-tight">Podrás firmar cuando tengas de 7 a 10 compromisos que sumen exactamente el porcentaje objetivo.</div>
                                <form id="form-firmar-evaluacion" method="POST" action="">
                                    @csrf
                                    <button type="submit" id="btn-firmar-evaluador" class="bg-[#00594E] text-white px-6 py-2.5 rounded-xl text-sm font-bold hover:brightness-110 transition disabled:opacity-50" disabled>Firmar concertación</button>
                                </form>
                            </div>
                        </div>

                        <div id="panel-concertacion-evaluador-empty" class="panel-card rounded-3xl p-8 flex flex-col items-center justify-center text-center text-slate-400">
                            <span class="material-symbols-outlined text-5xl text-slate-300 mb-3">assignment</span>
                            <p class="text-sm">Selecciona una Evaluación de la lista para revisar la concertaci�n.</p>
                        </div>
                    </div>
                </div>
            </section>
            @endif

            @if ($rolActivo === 'evaluado')
            <section id="section-evaluaciones" class="section-content @if($rolActivo === 'admin') hidden @endif space-y-6">
                <div class="grid gap-6 lg:grid-cols-[0.8fr_1.2fr]">
                    <div class="panel-card rounded-3xl p-6 h-fit">
                        <h2 class="text-xl font-black text-slate-900 mb-4">Mis evaluaciones</h2>
                        <div class="space-y-3">
                            @forelse($evaluacionesEvaluado as $ev)
                                <div class="evaluacion-card p-4 rounded-2xl border border-slate-200 bg-white cursor-pointer hover:border-[#00594E] transition" onclick="abrirConcertacionEvaluado(this, @js($ev))">
                                    <h4 class="font-bold text-slate-900 text-sm leading-snug">{{ $ev->tipo_nombre }}</h4>
                                    <p class="text-xs text-slate-500 mt-0.5">Evaluador: {{ $ev->evalador_nombres ?? 'Mi Evaluador' }} {{ $ev->evalador_apellidos ?? '' }}</p>
                                    <div class="flex justify-between items-center mt-3">
                                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-[#EAF2EF] text-[#00594E]">
                                            {{ $ev->sistema === 'RENDIMIENTO_LABORAL' ? 'RL' : 'AG' }}
                                        </span>
                                        <span class="text-[9px] uppercase tracking-wide font-bold text-slate-400">Fase {{ $ev->fase_actual }}</span>
                                    </div>
                                </div>
                            @empty
                                <div class="py-6 text-center text-slate-500 text-xs">No tienes evaluaciones registradas.</div>
                            @endforelse
                        </div>
                    </div>

                    <div id="panel-concertacion-evaluado" class="panel-card rounded-3xl p-6 hidden">
                        <div class="pb-4 border-b border-slate-100 flex justify-between items-start gap-4">
                            <div>
                                <span class="text-[9px] font-bold uppercase rounded bg-[#B5A160] text-white px-2 py-0.5">Concertación de Compromisos</span>
                                <h3 id="concertacion-evaluado-tipo" class="text-xl font-black text-slate-900 mt-1 leading-snug">Tipo de Evaluación</h3>
                                <p id="concertacion-evaluado-evaluador" class="text-xs text-slate-500">Evaluador: -</p>
                            </div>
                            <div class="text-right flex flex-col items-end">
                                <div class="text-xs text-slate-500">Progreso Ponderación</div>
                                <div id="compromisos-suma-peso-evaluado" class="text-xl font-black text-[#00594E]">0% / 80%</div>
                                <div id="compromisos-contador-evaluado" class="text-[10px] text-slate-400 font-bold mt-0.5">0 compromisos (mín 7, máx 10)</div>
                            </div>
                        </div>
                        <div id="ejes-misionales-seleccion-evaluado" class="mt-4 bg-slate-50/50 p-4 rounded-2xl border border-slate-100 hidden">
                            <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wide mb-2">Ejes misionales adicionales</h4>
                            <p class="text-[10px] text-slate-500 mb-3">Docencia es el eje base. Estos son los ejes definidos al abrir la evaluación.</p>
                            <div class="flex flex-col gap-2">
                                <label class="flex items-center gap-2.5 text-xs text-slate-600 cursor-not-allowed">
                                    <input type="checkbox" checked disabled class="rounded border-slate-300 text-[#00594E] focus:ring-[#00594E]" />
                                    <span class="font-semibold text-slate-800">Docencia (eje base)</span>
                                </label>
                                <label class="flex items-center gap-2.5 text-xs text-slate-600 cursor-not-allowed">
                                    <input type="checkbox" id="chk-eje-investigacion" disabled class="rounded border-slate-300 text-[#00594E] focus:ring-[#00594E]" />
                                    <span class="font-semibold text-slate-800">Horas de investigación</span>
                                </label>
                                <label class="flex items-center gap-2.5 text-xs text-slate-600 cursor-not-allowed">
                                    <input type="checkbox" id="chk-eje-proyeccion" disabled class="rounded border-slate-300 text-[#00594E] focus:ring-[#00594E]" />
                                    <span class="font-semibold text-slate-800">Proyección social</span>
                                </label>
                            </div>
                        </div>
                        <div class="my-6 space-y-4">
                            <h4 class="text-sm font-bold text-slate-800 flex items-center gap-2">
                                <span class="material-symbols-outlined text-base">format_list_bulleted</span>
                                Compromisos Propuestos por tu Evaluador
                            </h4>
                            <div id="compromisos-lista-evaluado" class="space-y-3"></div>
                        </div>
                        <div class="mt-6 pt-4 border-t border-slate-100 space-y-4">
                            <div class="flex items-center justify-between gap-3">
                                <h4 class="text-sm font-bold text-slate-800 flex items-center gap-2">
                                    <span class="material-symbols-outlined text-base">link</span>
                                    Evidencias
                                </h4>
                                <span id="evidencias-contador-evaluado" class="text-[10px] font-bold rounded-full px-2.5 py-1 bg-slate-100 text-slate-500">0 registradas</span>
                            </div>
                            <div id="evidencia-bloqueada-evaluado" class="hidden rounded-2xl border border-amber-200 bg-amber-50 p-4 text-xs font-semibold text-amber-700">
                                Podrás registrar evidencias cuando el evaluador y tú hayan firmado la concertación.
                            </div>
                            <form id="form-evidencia-evaluado" class="grid gap-3 rounded-2xl border border-slate-100 bg-slate-50/50 p-4" onsubmit="guardarEvidenciaEvaluado(event)">
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-600 uppercase mb-1">Componente</label>
                                    <select name="componente" id="evidencia-componente-evaluado" class="w-full text-xs rounded-xl border border-slate-200 p-2.5 bg-white outline-none focus:border-[#00594E]" onchange="toggleEvidenciaCompromisoSelect()">
                                        <option value="B">B - Compromisos laborales</option>
                                        <option value="C">C - Competencias comunes</option>
                                        <option value="D">D - Competencias nivel jerárquico</option>
                                        <option value="F">F - Plan de formación y capacitación</option>
                                    </select>
                                </div>
                                <div id="evidencia-compromiso-contenedor-evaluado">
                                    <label class="block text-[10px] font-bold text-slate-600 uppercase mb-1">Compromiso</label>
                                    <select name="id_compromiso" id="evidencia-compromiso-evaluado" class="w-full text-xs rounded-xl border border-slate-200 p-2.5 bg-white outline-none focus:border-[#00594E]" required>
                                        <option value="">Selecciona un compromiso</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-600 uppercase mb-1">Descripción</label>
                                    <input type="text" name="descripcion" id="evidencia-descripcion-evaluado" maxlength="500" class="w-full text-xs rounded-xl border border-slate-200 p-2.5 bg-white outline-none focus:border-[#00594E]" placeholder="Ej: Informe mensual, acta o soporte de actividad">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-600 uppercase mb-1">URL de evidencia</label>
                                    <div class="flex flex-col sm:flex-row gap-2">
                                        <input type="url" name="url" id="evidencia-url-evaluado" maxlength="1000" class="w-full text-xs rounded-xl border border-slate-200 p-2.5 bg-white outline-none focus:border-[#00594E]" placeholder="https://..." required>
                                        <button type="submit" id="btn-guardar-evidencia-evaluado" class="bg-[#00594E] text-white px-4 py-2.5 rounded-xl text-xs font-bold hover:brightness-110 transition whitespace-nowrap">Guardar URL</button>
                                    </div>
                                </div>
                                <div id="evidencia-mensaje-evaluado" class="hidden text-xs font-semibold"></div>
                            </form>
                            <div id="evidencias-lista-evaluado" class="space-y-2"></div>
                        </div>

                        <div id="resultado-bloque-evaluado" class="mt-6 pt-4 border-t border-slate-100 space-y-3 hidden">
                            <h4 class="text-sm font-bold text-slate-800 flex items-center gap-2">
                                <span class="material-symbols-outlined text-base">military_tech</span>
                                Resultado de la evaluación
                            </h4>
                            <div id="resultado-contenido-evaluado"></div>
                        </div>

                        <div id="firma-evaluado-seccion" class="mt-6 pt-4 border-t border-slate-100 flex items-center justify-between gap-4">
                            <div class="text-xs text-slate-500 leading-tight">Podrás firmar cuando el evaluador haya firmado la concertación.</div>
                            <form id="form-firmar-evaluado" method="POST" action="">
                                @csrf
                                <button type="submit" id="btn-firmar-evaluado" class="bg-[#00594E] text-white px-6 py-2.5 rounded-xl text-sm font-bold hover:brightness-110 transition disabled:opacity-50" disabled>Firmar Concertación</button>
                            </form>
                        </div>
                    </div>

                    <div id="panel-concertacion-evaluado-empty" class="panel-card rounded-3xl p-8 flex flex-col items-center justify-center text-center text-slate-400">
                        <span class="material-symbols-outlined text-5xl text-slate-300 mb-3">assignment</span>
                        <p class="text-sm">Selecciona una Evaluación de la lista de la izquierda para ver el estado de la concertaci�n.</p>
                    </div>
                </div>
            </section>
            @endif

            @if ($rolActivo === 'instancia_externa')
            <section id="section-instancia-externa" class="section-content space-y-6">
                <div class="grid gap-6 lg:grid-cols-[0.8fr_1.2fr]">
                    <div class="panel-card rounded-3xl p-6 h-fit">
                        <div class="mb-3">
                            <p class="text-xs font-bold uppercase tracking-[0.22em] text-[#00594E]">Instancia Externa</p>
                            <h2 class="text-xl font-black text-slate-900">Evaluados de Acuerdo de Gestión</h2>
                            <p class="text-xs text-slate-500 mt-1">Vicerrectoría de Investigación, Vicerrectoría de Proyección Social y CEDP cargan aquí las notas del componente académico (docencia, investigación, proyección social) para líderes de programa, departamento o director de escuela.</p>
                        </div>
                        <div id="instancia-externa-lista" class="space-y-3">
                            <div class="py-6 text-center text-slate-500 text-xs">Cargando evaluados...</div>
                        </div>
                    </div>

                    <div class="space-y-6 lg:sticky lg:top-6">
                        <div id="panel-instancia-externa" class="panel-card rounded-3xl p-6 hidden">
                            <div class="flex items-start justify-between gap-4 pb-4 border-b border-slate-100">
                                <div>
                                    <p class="text-xs font-bold uppercase tracking-[0.22em] text-[#00594E]">Ejes misionales</p>
                                    <h3 id="instancia-externa-nombre" class="text-xl font-black text-slate-900 mt-1 leading-snug">Selecciona un evaluado</h3>
                                    <p id="instancia-externa-detalle" class="text-xs text-slate-500">-</p>
                                </div>
                            </div>
                            <form id="form-instancia-externa" class="mt-4 space-y-3" onsubmit="guardarNotasInstanciaExterna(event)">
                                <div id="instancia-externa-ejes-contenedor" class="space-y-3"></div>
                                <div class="flex items-center justify-between gap-3 pt-2">
                                    <span id="instancia-externa-mensaje" class="hidden text-xs font-semibold"></span>
                                    <button type="submit" class="bg-[#00594E] text-white px-4 py-2 rounded-xl text-xs font-bold hover:brightness-110 transition ml-auto">Guardar notas</button>
                                </div>
                            </form>
                        </div>

                        <div id="panel-instancia-externa-empty" class="panel-card rounded-3xl p-8 flex flex-col items-center justify-center text-center text-slate-400">
                            <span class="material-symbols-outlined text-5xl text-slate-300 mb-3">school</span>
                            <p class="text-sm">Selecciona un evaluado de la lista para cargar sus notas de componente académico.</p>
                        </div>
                    </div>
                </div>
            </section>
            @endif

        </main>
    </div>
</div>

<div id="password-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-950/55 px-4">
    <div class="w-full max-w-md rounded-3xl bg-white p-6 shadow-2xl">
        <div class="flex items-start justify-between gap-4 mb-5">
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.18em] text-[#00594E]">Seguridad</p>
                <h3 class="text-xl font-black text-slate-900">Cambiar contraseña</h3>
            </div>
            <button type="button" onclick="closePasswordModal()" class="rounded-full p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <form method="POST" action="{{ route('password.update') }}" class="space-y-4">
            @csrf
            <div>
                <label class="block text-sm font-bold text-slate-700 mb-1">Contraseña actual</label>
                <input type="password" name="current_password" class="w-full rounded-xl border border-slate-200 px-4 py-3" required>
            </div>
            <div>
                <label class="block text-sm font-bold text-slate-700 mb-1">Nueva contraseña</label>
                <input type="password" name="password" class="w-full rounded-xl border border-slate-200 px-4 py-3" required>
            </div>
            <div>
                <label class="block text-sm font-bold text-slate-700 mb-1">Confirmar nueva contraseña</label>
                <input type="password" name="password_confirmation" class="w-full rounded-xl border border-slate-200 px-4 py-3" required>
            </div>
            <button type="submit" class="w-full rounded-2xl bg-[#00594E] text-white font-bold py-3">Guardar cambio</button>
        </form>
    </div>
</div>

@if (session('temp_password'))
<div id="temp-password-toast" class="fixed bottom-4 right-4 z-50 max-w-sm rounded-2xl bg-slate-900 text-white p-4 shadow-2xl">
    <p class="text-xs uppercase tracking-[0.18em] text-[#B5A160] font-bold">Contrase?a temporal generada</p>
    <p class="mt-2 text-sm">Entrega esta contrase?a al usuario para su primer acceso.</p>
    <div class="mt-3 rounded-xl bg-white/10 p-3 text-lg font-black tracking-wider">{{ session('temp_password') }}</div>
    <button onclick="document.getElementById('temp-password-toast').remove()" class="mt-3 text-xs font-bold text-white/80">Cerrar</button>
</div>
@endif

<script>
    let selectedEvaluacionId = null;
    let selectedEvaluacionData = null;
    let selectedEvaluacionEjes = {};
    const periodosDisponibles = @js($periodos->map(fn ($p) => [
        'id_periodo' => $p->id_periodo,
        'sistema' => $p->sistema,
        'anio' => $p->anio,
        'semestre' => $p->semestre,
        'estado' => $p->estado,
        'fecha_inicio' => $p->fecha_inicio,
        'fecha_fin' => $p->fecha_fin,
    ])->values());
    const ponderacionesConfig = @js($ponderacionesConfig ?? []);

    function calcularObjetivoCompromisos(ev, ejes = {}) {
        const sistema = String(ev?.sistema || '').trim().toUpperCase();
        const config = ponderacionesConfig[sistema] || ponderacionesConfig.RENDIMIENTO_LABORAL || {};
        let objetivo = parseFloat(config.peso_compromisos ?? 80);

        if (sistema === 'ACUERDO_GESTION' && ev?.aplica_eje_misional) {
            if (!ejes?.investigacion) objetivo += parseFloat(config.peso_investigacion ?? 0);
            if (!ejes?.proyeccion_social) objetivo += parseFloat(config.peso_proyeccion_social ?? 0);
        } else if (sistema === 'ACUERDO_GESTION' && !ev?.aplica_eje_misional) {
            objetivo += parseFloat(config.peso_docencia ?? 0) + parseFloat(config.peso_investigacion ?? 0) + parseFloat(config.peso_proyeccion_social ?? 0);
        }

        return Math.max(0, objetivo);
    }

    function escapeHtml(value) {
        const div = document.createElement('div');
        div.innerText = value ?? '';
        return div.innerHTML;
    }

    function filtrarOpcionesAsignacion(inputId, selectId) {
        const input = document.getElementById(inputId);
        const select = document.getElementById(selectId);
        if (!input || !select) return;

        const termino = input.value.trim().toLowerCase();
        let seleccionVisible = false;

        Array.from(select.options).forEach(option => {
            if (!option.value) {
                option.hidden = false;
                return;
            }

            const coincide = option.text.toLowerCase().includes(termino);
            option.hidden = !coincide;
            if (coincide && option.selected) {
                seleccionVisible = true;
            }
        });

        if (!seleccionVisible) {
            select.value = '';
        }
    }

    function agruparEvidenciasPorCompromiso(evidencias = []) {
        return evidencias.reduce((grupos, evidencia) => {
            const key = String(evidencia.id_compromiso || '');
            if (!grupos[key]) grupos[key] = [];
            grupos[key].push(evidencia);
            return grupos;
        }, {});
    }

    function agruparObservacionesPorCompromiso(observaciones = []) {
        return observaciones.reduce((grupos, observacion) => {
            const key = String(observacion.id_compromiso || '');
            if (!key) return grupos;
            grupos[key] = observacion;
            return grupos;
        }, {});
    }

    function contarEvidencias(evidencias = []) {
        return evidencias.length;
    }

    function badgeEstadoAprobacion(estado) {
        const label = estado || 'PENDIENTE';
        const cls = label === 'APROBADA' ? 'bg-[#EAF2EF] text-[#00594E]' : (label === 'RECHAZADA' ? 'bg-red-50 text-red-600' : 'bg-amber-50 text-amber-700');
        return `<span class="inline-block mt-1 text-[9px] font-bold uppercase px-2 py-0.5 rounded-full ${cls}">${label}</span>`;
    }

    function renderEvidenciasCompactas(evidencias = []) {
        if (!evidencias.length) {
            return '<div class="text-[11px] text-slate-400">Sin evidencias registradas para este compromiso.</div>';
        }

        return evidencias.map(evidencia => `
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 rounded-lg border border-slate-100 bg-slate-50 px-3 py-2">
                <div class="min-w-0">
                    <p class="text-[11px] font-bold text-slate-700 truncate">${escapeHtml(evidencia.descripcion || 'Evidencia registrada')}</p>
                    <p class="text-[10px] text-slate-400">${escapeHtml(evidencia.fecha_inclusion || '')}</p>
                    ${badgeEstadoAprobacion(evidencia.estado_aprobacion)}
                    ${evidencia.estado_aprobacion === 'RECHAZADA' && evidencia.observacion_aprobacion ? `<p class="text-[10px] text-red-600 mt-1">${escapeHtml(evidencia.observacion_aprobacion)}</p>` : ''}
                </div>
                <a class="inline-flex items-center gap-1 text-[11px] font-bold text-[#00594E] hover:underline shrink-0" href="${escapeHtml(evidencia.url_o_ubicacion || '#')}" target="_blank" rel="noopener noreferrer">
                    <span class="material-symbols-outlined text-sm">open_in_new</span>
                    <span>Abrir</span>
                </a>
            </div>
        `).join('');
    }

    function renderEvidenciasEvaluadorAccion(evidencias = []) {
        if (!evidencias.length) {
            return '<div class="text-[11px] text-slate-400">Sin evidencias registradas para este compromiso.</div>';
        }

        return evidencias.map(evidencia => {
            const estado = evidencia.estado_aprobacion || 'PENDIENTE';
            const acciones = estado === 'PENDIENTE' ? `
                <div class="flex gap-1.5 shrink-0">
                    <button type="button" onclick="aprobarEvidencia(${evidencia.id_evidencia}, 'APROBADA')" class="text-[10px] font-bold px-2 py-1 rounded-lg bg-[#EAF2EF] text-[#00594E] hover:brightness-95">Aprobar</button>
                    <button type="button" onclick="aprobarEvidencia(${evidencia.id_evidencia}, 'RECHAZADA')" class="text-[10px] font-bold px-2 py-1 rounded-lg bg-red-50 text-red-600 hover:brightness-95">Rechazar</button>
                </div>` : '';
            return `
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 rounded-lg border border-slate-100 bg-slate-50 px-3 py-2">
                    <div class="min-w-0">
                        <p class="text-[11px] font-bold text-slate-700 truncate">${escapeHtml(evidencia.descripcion || 'Evidencia registrada')}</p>
                        <p class="text-[10px] text-slate-400">${escapeHtml(evidencia.fecha_inclusion || '')}</p>
                        ${badgeEstadoAprobacion(estado)}
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <a class="inline-flex items-center gap-1 text-[11px] font-bold text-[#00594E] hover:underline" href="${escapeHtml(evidencia.url_o_ubicacion || '#')}" target="_blank" rel="noopener noreferrer">
                            <span class="material-symbols-outlined text-sm">open_in_new</span><span>Abrir</span>
                        </a>
                        ${acciones}
                    </div>
                </div>`;
        }).join('');
    }

    function aprobarEvidencia(idEvidencia, decision) {
        if (!selectedEvaluacionId) return;
        const observacion = decision === 'RECHAZADA' ? (prompt('Motivo del rechazo (opcional):') || '') : '';
        fetch(`/evaluaciones/${selectedEvaluacionId}/evidencias/${idEvidencia}/aprobar`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            body: JSON.stringify({ decision, observacion }),
        })
            .then(res => res.json())
            .then(() => { if (selectedEvaluacionData) cargarCompromisosEvaluador(selectedEvaluacionData, selectedEvaluacionEjes); })
            .catch(() => {});
    }

    function renderObservacionEvaluador(compromiso, observacion = null, bloqueadaPorCierre = false) {
        const id = compromiso.id_compromiso;
        const texto = observacion?.texto || '';
        const confirmada = !!observacion?.confirmada;
        const bloqueada = confirmada || bloqueadaPorCierre;
        const estado = confirmada
            ? `Confirmada${observacion?.fecha_confirmacion ? ` el ${escapeHtml(observacion.fecha_confirmacion)}` : ''}`
            : (bloqueadaPorCierre ? 'Disponible tras firmar la concertación' : (texto ? 'Borrador guardado' : 'Sin observación'));
        const botones = confirmada
            ? '<span class="text-[10px] font-bold uppercase text-[#00594E]">No modificable</span>'
            : `
                <button type="submit" class="bg-white border border-slate-200 text-slate-700 px-3 py-2 rounded-xl text-xs font-bold hover:border-[#00594E] transition" ${bloqueadaPorCierre ? 'disabled' : ''}>Guardar</button>
                <button type="button" onclick="confirmarObservacionCompromiso(${id})" class="bg-[#00594E] text-white px-3 py-2 rounded-xl text-xs font-bold hover:brightness-110 transition disabled:opacity-50" ${bloqueadaPorCierre ? 'disabled' : ''}>Confirmar</button>
            `;

        return `
            <form class="mt-4 pt-3 border-t border-slate-100 space-y-2" onsubmit="guardarObservacionCompromiso(event, ${id}, false)">
                <div class="flex items-center justify-between gap-3">
                    <div class="flex items-center gap-1.5 text-[11px] font-bold uppercase text-slate-500">
                        <span class="material-symbols-outlined text-sm">note_alt</span>
                        Observación de este compromiso
                    </div>
                    <span class="text-[10px] font-bold rounded-full px-2.5 py-1 ${confirmada ? 'bg-[#EAF2EF] text-[#00594E]' : 'bg-slate-100 text-slate-500'}">${estado}</span>
                </div>
                <textarea id="observacion-compromiso-${id}" maxlength="2000" rows="3" class="w-full text-xs rounded-xl border border-slate-200 p-2.5 bg-white outline-none focus:border-[#00594E] disabled:bg-slate-50 disabled:text-slate-500" placeholder="Escribe una observación para este compromiso..." ${bloqueada ? 'disabled' : ''}>${escapeHtml(texto)}</textarea>
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                    <span id="observacion-mensaje-${id}" class="hidden text-xs font-semibold"></span>
                    <div class="flex gap-2 justify-end">${botones}</div>
                </div>
            </form>
        `;
    }

    function renderObservacionEvaluado(observacion = null) {
        if (!observacion?.texto || !observacion?.confirmada) {
            return `
                <div class="mt-4 pt-3 border-t border-slate-100">
                    <div class="text-[11px] text-slate-400">Sin observación confirmada del evaluador para este compromiso.</div>
                </div>
            `;
        }

        return `
            <div class="mt-4 pt-3 border-t border-slate-100 space-y-2">
                <div class="flex items-center justify-between gap-3">
                    <div class="flex items-center gap-1.5 text-[11px] font-bold uppercase text-slate-500">
                        <span class="material-symbols-outlined text-sm">note_alt</span>
                        Observación del evaluador
                    </div>
                    <span class="text-[10px] font-bold rounded-full px-2.5 py-1 bg-[#EAF2EF] text-[#00594E]">Confirmada</span>
                </div>
                <p class="text-xs text-slate-700 whitespace-pre-wrap rounded-xl border border-slate-200 bg-white p-3">${escapeHtml(observacion.texto)}</p>
            </div>
        `;
    }

    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar-menu');
        const overlay = document.getElementById('sidebar-overlay');
        if (sidebar) sidebar.classList.toggle('-translate-x-full');
        if (overlay) overlay.classList.toggle('hidden');
    }

    function toggleProfileMenu() {
        const menu = document.getElementById('profile-menu');
        if (menu) menu.classList.toggle('open');
    }

    function openPasswordModal() {
        const modal = document.getElementById('password-modal');
        if (modal) {
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }
        const menu = document.getElementById('profile-menu');
        if (menu) menu.classList.remove('open');
    }

    function closePasswordModal() {
        const modal = document.getElementById('password-modal');
        if (modal) {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }
    }

    function navegarMenu(button, seccion) {
        const activeRole = "{{ $rolActivo }}";
        let targetSeccion = seccion;
        if (activeRole !== 'admin' && (seccion === 'usuarios' || seccion === 'empleados' || seccion === 'periodos' || seccion === 'ponderaciones')) {
            targetSeccion = activeRole === 'evaluador' && seccion === 'usuarios' ? 'usuarios-evaluador' : 'evaluaciones';
        }
        document.querySelectorAll('.section-content').forEach(el => el.classList.add('hidden'));
        const target = document.getElementById(`section-${targetSeccion}`);
        if (target) target.classList.remove('hidden');
        document.querySelectorAll('.sidebar-link').forEach(btn => btn.classList.remove('active'));
        if (button) button.classList.add('active');
        if (window.innerWidth < 1024) toggleSidebar();
    }

    function filtrarEmpleados() {
        const texto = (document.getElementById('buscador-empleados')?.value || '').trim().toLowerCase();
        document.querySelectorAll('.empleado-card').forEach(card => {
            const nombre = card.dataset.nombre || '';
            const cedula = card.dataset.cedula || '';
            const correo = card.dataset.correo || '';
            const match = !texto || nombre.includes(texto) || cedula.includes(texto) || correo.includes(texto);
            card.classList.toggle('hidden', !match);
        });
    }

    function seleccionarEmpleado(card, empleado) {
        const setText = (id, value) => {
            const node = document.getElementById(id);
            if (node) node.innerText = value;
        };
        setText('empleado-avatar', (empleado.nombres?.[0] || '') + (empleado.apellidos?.[0] || ''));
        setText('empleado-nombre', `${empleado.nombres || ''} ${empleado.apellidos || ''}`.trim());
        setText('empleado-cargo', empleado.nombre_cargo || 'Sin cargo');
        setText('empleado-correo', empleado.correo_institucional || 'Sin correo');
        setText('empleado-documento', `${empleado.tipo_documento || ''} ${empleado.documento_identidad || ''}`.trim());
        setText('empleado-area', empleado.nombre_area || 'Sin ?rea');
        setText('empleado-estado', empleado.activo ? 'Activo' : 'Inactivo');
        document.querySelectorAll('.empleado-card').forEach(el => el.classList.remove('ring-2', 'ring-[#00594E]'));
        if (card) card.classList.add('ring-2', 'ring-[#00594E]');
    }

    function seleccionarPersonaEvaluador(card, persona) {
        selectedEvaluacionData = persona;
        const nombreCompleto = `${persona.nombres || ''} ${persona.apellidos || ''}`.trim();
        const sistema = String(persona.sistema_evaluacion || '').trim().toUpperCase();
        const periodo = periodosDisponibles.find(p => p.estado === 'ABIERTO' && String(p.sistema || '').trim().toUpperCase() === sistema);
        const setText = (id, value) => {
            const node = document.getElementById(id);
            if (node) node.innerText = value;
        };
        const panel = document.getElementById('panel-apertura-evaluacion');
        if (panel) panel.classList.remove('hidden');
        setText('empleado-avatar', ((persona.nombres?.[0] || '') + (persona.apellidos?.[0] || '')).toUpperCase() || '--');
        setText('empleado-nombre', nombreCompleto || 'Selecciona una persona');
        setText('empleado-cargo', `${persona.cargo || 'Sin cargo'} - ${persona.area || 'Sin ?rea'}`);
        setText('empleado-correo', persona.correo_cargo || '-');
        setText('empleado-documento', persona.numero_doc || persona.codigo_cargo || '-');
        setText('empleado-area', persona.area || '-');
        setText('empleado-cargo-vinc', persona.cargo || '-');
        setText('empleado-vinculacion', persona.tipo_vinculacion || '-');
        setText('empleado-nivel', persona.nivel_jerarquico || '-');
        setText('empleado-sistema', persona.sistema_evaluacion || '-');
        setText('empleado-ingreso', persona.fecha_ingreso || '-');
        setText('empleado-estado', persona.es_evaluador ? 'Evaluador' : 'Activo');
        setText('apertura-nombre', nombreCompleto || 'Selecciona una persona');
        setText('apertura-detalle', `Tipo de acuerdo: ${persona.sistema_evaluacion || '-'}`);
        setText('apertura-sistema', sistema === 'RENDIMIENTO_LABORAL' ? 'RL' : (sistema === 'ACUERDO_GESTION' ? 'AG' : (persona.sistema_evaluacion || '-')));
        const aperturaIdVinc = document.getElementById('apertura-id-vinc');
        const aperturaIdPeriodo = document.getElementById('apertura-id-periodo');
        const cicloSelect = document.getElementById('apertura-ciclo-select');
        const aperturaPeriodo = document.getElementById('apertura-periodo');
        const aperturaVigencia = document.getElementById('apertura-vigencia');
        const aperturaCiclo = document.getElementById('apertura-ciclo');
        const aperturaAviso = document.getElementById('apertura-aviso-periodo');
        const aperturaEjes = document.getElementById('apertura-ejes-misionales');
        const aperturaEjeInv = document.getElementById('apertura-eje-investigacion');
        const aperturaEjeProy = document.getElementById('apertura-eje-proyeccion');
        if (aperturaIdVinc) aperturaIdVinc.value = persona.id_vinculacion || '';
        if (cicloSelect) cicloSelect.value = 'SEMESTRE_1';
        if (aperturaEjeInv) aperturaEjeInv.checked = false;
        if (aperturaEjeProy) aperturaEjeProy.checked = false;
        if (aperturaEjes) {
            aperturaEjes.classList.toggle('hidden', !(sistema === 'ACUERDO_GESTION' && !!persona.aplica_eje_misional));
        }
        if (periodo) {
            if (aperturaIdPeriodo) aperturaIdPeriodo.value = periodo.id_periodo;
            if (aperturaPeriodo) aperturaPeriodo.innerText = `${periodo.sistema} (${periodo.anio}-${String(periodo.semestre).padStart(2, '0')})`;
            if (aperturaVigencia) aperturaVigencia.innerText = `${periodo.fecha_inicio || '-'} a ${periodo.fecha_fin || '-'}`;
            if (aperturaCiclo && cicloSelect) aperturaCiclo.innerText = cicloSelect.options[cicloSelect.selectedIndex].text;
            if (aperturaAviso) aperturaAviso.innerText = 'El periodo se asigna autom?ticamente seg?n el tipo de acuerdo.';
        } else {
            if (aperturaIdPeriodo) aperturaIdPeriodo.value = '';
            if (aperturaPeriodo) aperturaPeriodo.innerText = 'No hay periodo abierto para este sistema';
            if (aperturaVigencia) aperturaVigencia.innerText = '-';
            if (aperturaCiclo && cicloSelect) aperturaCiclo.innerText = cicloSelect.options[cicloSelect.selectedIndex].text;
            if (aperturaAviso) aperturaAviso.innerText = 'Abre un periodo activo para este sistema antes de iniciar la evaluacion.';
        }
        document.querySelectorAll('.evaluado-card').forEach(el => el.classList.remove('ring-2', 'ring-[#00594E]'));
        if (card) card.classList.add('ring-2', 'ring-[#00594E]');
    }

    function abrirConcertacionEvaluado(card, ev) {
        selectedEvaluacionId = ev.id_evaluacion;
        selectedEvaluacionData = ev;
        const panel = document.getElementById('panel-concertacion-evaluado');
        const empty = document.getElementById('panel-concertacion-evaluado-empty');
        if (empty) empty.classList.add('hidden');
        if (panel) panel.classList.remove('hidden');
        const tipo = document.getElementById('concertacion-evaluado-tipo');
        const evaluador = document.getElementById('concertacion-evaluado-evaluador');
        const form = document.getElementById('form-firmar-evaluado');
        const evidenciaForm = document.getElementById('form-evidencia-evaluado');
        const evidenciaMensaje = document.getElementById('evidencia-mensaje-evaluado');
        if (tipo) tipo.innerText = ev.tipo_nombre || 'Tipo de evaluacion';
        if (evaluador) evaluador.innerText = `Evaluador: ${ev.evalador_nombres || 'Mi Evaluador'} ${ev.evalador_apellidos || ''}`.trim();
        if (form) form.action = `/evaluaciones/${ev.id_evaluacion}/firmar`;
        if (evidenciaForm) evidenciaForm.reset();
        if (evidenciaMensaje) {
            evidenciaMensaje.classList.add('hidden');
            evidenciaMensaje.innerText = '';
        }
        const axesView = document.getElementById('ejes-misionales-seleccion-evaluado');
        const chkInv = document.getElementById('chk-eje-investigacion');
        const chkProj = document.getElementById('chk-eje-proyeccion');
        if (axesView) axesView.classList.add('hidden');
        if (chkInv) chkInv.checked = false;
        if (chkProj) chkProj.checked = false;
        fetch(`/evaluaciones/${ev.id_evaluacion}/ejes`)
            .then(res => res.json())
            .then(ejes => {
                const aplica = ev.sistema === 'ACUERDO_GESTION' && !!ev.aplica_eje_misional;
                if (aplica && axesView) {
                    axesView.classList.remove('hidden');
                    if (chkInv) chkInv.checked = !!ejes.investigacion;
                    if (chkProj) chkProj.checked = !!ejes.proyeccion_social;
                }
                cargarCompromisosEvaluado(ev);
            })
            .catch(() => cargarCompromisosEvaluado(ev));
        document.querySelectorAll('.evaluacion-card').forEach(el => el.classList.remove('ring-2', 'ring-[#00594E]'));
        if (card) card.classList.add('ring-2', 'ring-[#00594E]');
    }

    function abrirConcertacionEvaluador(card, ev) {
        selectedEvaluacionId = ev.id_evaluacion;
        selectedEvaluacionData = ev;
        selectedEvaluacionEjes = {};

        const panel = document.getElementById('panel-concertacion-evaluador');
        const empty = document.getElementById('panel-concertacion-evaluador-empty');
        if (empty) empty.classList.add('hidden');
        if (panel) panel.classList.remove('hidden');

        const setText = (id, value) => {
            const node = document.getElementById(id);
            if (node) node.innerText = value;
        };

        setText('concertacion-nombre', `${ev.evaluado_nombres || ''} ${ev.evaluado_apellidos || ''}`.trim() || 'Selecciona una evaluacion');
        setText('concertacion-detalle', `${ev.evaluado_cargo || '-'} - ${ev.evaluado_area || '-'}`);
        setText('concertacion-sistema', ev.sistema === 'RENDIMIENTO_LABORAL' ? 'RL' : 'AG');

        const axesView = document.getElementById('ejes-misionales-vista-evaluador');
        const ejeInv = document.getElementById('eje-vista-investigacion');
        const ejeProj = document.getElementById('eje-vista-proyeccion');
        const ejeNinguno = document.getElementById('eje-vista-ninguno');
        if (axesView) axesView.classList.add('hidden');
        if (ejeInv) ejeInv.classList.add('hidden');
        if (ejeProj) ejeProj.classList.add('hidden');
        if (ejeNinguno) ejeNinguno.classList.add('hidden');

        fetch(`/evaluaciones/${ev.id_evaluacion}/ejes`)
            .then(res => res.json())
            .then(ejes => {
                selectedEvaluacionEjes = ejes || {};
                if (ev.sistema === 'ACUERDO_GESTION' && ev.aplica_eje_misional && axesView) {
                    axesView.classList.remove('hidden');
                    if (ejeInv && ejes.investigacion) ejeInv.classList.remove('hidden');
                    if (ejeProj && ejes.proyeccion_social) ejeProj.classList.remove('hidden');
                    if (ejeNinguno && !ejes.investigacion && !ejes.proyeccion_social) ejeNinguno.classList.remove('hidden');
                }
                cargarCompromisosEvaluador(ev, ejes);
            })
            .catch(() => {
                selectedEvaluacionEjes = {};
                cargarCompromisosEvaluador(ev, {});
            });

        document.querySelectorAll('.evaluacion-evaluador-card').forEach(el => el.classList.remove('ring-2', 'ring-[#00594E]'));
        if (card) card.classList.add('ring-2', 'ring-[#00594E]');
    }

    function cargarCompromisosEvaluador(ev, ejes = selectedEvaluacionEjes) {
        if (!selectedEvaluacionId) return;
        fetch(`/evaluaciones/${selectedEvaluacionId}/compromisos`)
	            .then(res => res.json())
	            .then(payload => {
	                const compromisos = payload.compromisos || [];
	                const evidencias = payload.evidencias || [];
	                const observaciones = payload.observaciones || [];
	                const estado = payload.estado || {};
	                const contenedor = document.getElementById('compromisos-lista-contenedor');
	                const badge = document.getElementById('concertacion-sistema');
	                if (!contenedor) return;
	                contenedor.innerHTML = '';

                let targetWeight = calcularObjetivoCompromisos(ev, ejes);
                const weightInput = document.getElementById('comp-peso-evaluador');
                if (weightInput) {
                    weightInput.min = 1;
                    weightInput.max = 15;
                    weightInput.step = 0.1;
                    weightInput.placeholder = 'De 1% a 15%';
                }

                if (badge) {
                    if (estado.congelada) {
                        badge.innerText = 'Cerrada';
                        badge.className = 'text-[10px] font-black uppercase px-2.5 py-1 rounded-full bg-slate-200 text-slate-700';
                    } else if (estado.evaluador_firmado) {
                        badge.innerText = 'Firmada';
                        badge.className = 'text-[10px] font-black uppercase px-2.5 py-1 rounded-full bg-[#EAF2EF] text-[#00594E]';
                    } else {
                        badge.innerText = 'Pendiente';
                        badge.className = 'text-[10px] font-black uppercase px-2.5 py-1 rounded-full bg-amber-50 text-amber-700';
                    }
                }

                let sumaPesos = 0;
                const yaFirmado = !!estado.congelada || !!estado.evaluador_firmado;
                const evidenciasPorCompromiso = agruparEvidenciasPorCompromiso(evidencias);
                const observacionesPorCompromiso = agruparObservacionesPorCompromiso(observaciones);
                compromisos.forEach(c => {
                    sumaPesos += parseFloat(c.porcentaje_peso || 0);
                    const div = document.createElement('div');
                    div.className = 'p-4 rounded-xl border bg-white';
                    const metasHtml = (c.metas || []).map(m => `<span class="bg-[#EAF2EF] text-[#00594E] text-[10px] font-bold px-2 py-0.5 rounded-full">${escapeHtml(m)}</span>`).join(' ');
                    const deleteBtn = yaFirmado ? '' : `<button type="button" class="text-red-500 hover:text-red-700 mt-1 flex items-center justify-center" onclick="eliminarCompromisoEvaluador(${c.id_compromiso})"><span class="material-symbols-outlined text-lg">delete</span></button>`;
                    const evidenciasHtml = renderEvidenciasEvaluadorAccion(evidenciasPorCompromiso[String(c.id_compromiso)] || []);
                    const observacionHtml = renderObservacionEvaluador(c, observacionesPorCompromiso[String(c.id_compromiso)], !estado.congelada);
                    const calificacionHtml = estado.congelada ? `
                        <div class="mt-4 pt-3 border-t border-slate-100 flex items-center gap-2">
                            <label class="text-[10px] font-bold text-slate-500 uppercase">Calificación (0-100)</label>
                            <input type="number" min="0" max="100" step="0.01" class="calificacion-compromiso-input w-24 text-xs rounded-lg border border-slate-200 p-1.5" data-id-compromiso="${c.id_compromiso}" value="${c.calificacion_definitiva ?? ''}" />
                        </div>` : '';
                    div.innerHTML = `
                        <div class="flex justify-between items-start gap-4">
                            <div class="flex-1">
                                <div class="flex items-center gap-2">
                                    <span class="w-5 h-5 rounded-full bg-slate-100 flex items-center justify-center text-xs font-black text-slate-500">${c.numero_orden}</span>
                                    <span class="font-bold text-slate-800 text-sm">${c.porcentaje_peso}% peso</span>
                                </div>
                                <p class="text-xs text-slate-600 mt-1.5">${escapeHtml(c.descripcion)}</p>
                                <div class="flex flex-wrap gap-1 mt-2.5">${metasHtml}</div>
                            </div>
                            ${deleteBtn}
                        </div>
                        <div class="mt-4 pt-3 border-t border-slate-100 space-y-2">
                            <div class="flex items-center gap-1.5 text-[11px] font-bold uppercase text-slate-500">
                                <span class="material-symbols-outlined text-sm">link</span>
                                Evidencias de este compromiso
                            </div>
                            <div class="space-y-2">${evidenciasHtml}</div>
                        </div>
                        ${calificacionHtml}
                        ${observacionHtml}
                    `;
                    contenedor.appendChild(div);
                });

                const contador = compromisos.length;
                const sumaNode = document.getElementById('compromisos-suma-peso-evaluador');
	                const contadorNode = document.getElementById('compromisos-contador-evaluador');
	                if (sumaNode) sumaNode.innerText = `${sumaPesos}% / ${targetWeight}%`;
	                if (contadorNode) contadorNode.innerText = `${contador} compromisos (mín 7, máx 10)`;

	                renderEvidenciasLectura(evidencias, 'evidencias-lista-evaluador', 'evidencias-contador-evaluador');

	                const formContainer = document.getElementById('compromiso-formulario-evaluador-contenedor');
	                if (formContainer) formContainer.classList.toggle('hidden', yaFirmado);

                ['calificacion-bloque-evaluador', 'competencias-bloque-evaluador', 'resultado-bloque-evaluador'].forEach(id => {
                    const bloque = document.getElementById(id);
                    if (bloque) bloque.classList.toggle('hidden', !estado.congelada);
                });
                if (estado.congelada) {
                    cargarCalificacionYResultado(ev);
                }

                const btnFirmar = document.getElementById('btn-firmar-evaluador');
                const okToSign = contador >= 7 && contador <= 10 && Math.abs(sumaPesos - targetWeight) < 0.01 && !yaFirmado;
                if (btnFirmar) {
                    btnFirmar.disabled = !okToSign;
                    btnFirmar.innerText = yaFirmado ? 'Firmado' : 'Firmar concertación';
                }
                const form = document.getElementById('form-firmar-evaluacion');
                if (form) form.action = `/evaluaciones/${ev.id_evaluacion}/firmar`;
            })
            .catch(() => {});
    }

    // --- S5: Calificación de compromisos/competencias y resultado consolidado ---

    function cargarCalificacionYResultado(ev) {
        if (!selectedEvaluacionId) return;
        const sistema = String(ev.sistema || '').trim().toUpperCase();
        const nivel = String(ev.evaluado_nivel_jerarquico || '').trim().toUpperCase();

        fetch(`/catalogo/competencias?sistema=${encodeURIComponent(sistema)}&nivel=${encodeURIComponent(nivel)}`)
            .then(res => res.json())
            .then(catalogo => {
                fetch(`/evaluaciones/${selectedEvaluacionId}/competencias`)
                    .then(res => res.json())
                    .then(payload => renderCompetenciasEvaluador(catalogo, payload.competencias || []))
                    .catch(() => renderCompetenciasEvaluador(catalogo, []));
            })
            .catch(() => {});

        previsualizarCalculoEvaluador();
    }

    function renderCompetenciasEvaluador(catalogo, existentes = []) {
        const comunes = catalogo.comun || [];
        const nivel = catalogo.nivel_jerarquico || [];
        const notasExistentes = existentes.reduce((acc, c) => {
            acc[`${c.tipo}::${c.nombre_competencia}`] = c.calificacion_definitiva;
            return acc;
        }, {});

        const renderLista = (items, tipo) => items.map(item => {
            const key = `${tipo}::${item.nombre}`;
            const valor = notasExistentes[key] ?? '';
            return `
                <div class="p-3 rounded-xl border border-slate-100 bg-white">
                    <p class="text-xs font-bold text-slate-800">${escapeHtml(item.nombre)}</p>
                    <p class="text-[10px] text-slate-500 mt-0.5">${escapeHtml(item.afirmacion || '')}</p>
                    <div class="mt-2 flex items-center gap-2">
                        <label class="text-[10px] font-bold text-slate-500 uppercase">Calificación (0-100)</label>
                        <input type="number" min="0" max="100" step="0.01" class="competencia-input w-24 text-xs rounded-lg border border-slate-200 p-1.5" data-tipo="${tipo}" data-nombre="${escapeHtml(item.nombre)}" value="${valor}" />
                    </div>
                </div>`;
        }).join('');

        const comunesNode = document.getElementById('competencias-comunes-evaluador');
        const nivelNode = document.getElementById('competencias-nivel-evaluador');
        if (comunesNode) comunesNode.innerHTML = '<p class="text-[10px] font-bold text-slate-400 uppercase mb-1">Comunes</p>' + (renderLista(comunes, 'COMUN') || '<p class="text-[11px] text-slate-400">Sin catálogo disponible.</p>');
        if (nivelNode) nivelNode.innerHTML = '<p class="text-[10px] font-bold text-slate-400 uppercase mb-1">Nivel jerárquico</p>' + (renderLista(nivel, 'NIVEL_JERARQUICO') || '<p class="text-[11px] text-slate-400">Sin catálogo disponible.</p>');
    }

    function guardarCalificacionCompromisos() {
        if (!selectedEvaluacionId) return;
        const compromisos = Array.from(document.querySelectorAll('.calificacion-compromiso-input'))
            .filter(input => input.value !== '')
            .map(input => ({ id_compromiso: parseInt(input.dataset.idCompromiso, 10), calificacion_definitiva: parseFloat(input.value) }));

        const msg = document.getElementById('calificacion-mensaje-evaluador');
        fetch(`/evaluaciones/${selectedEvaluacionId}/calificar-compromisos`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            body: JSON.stringify({ compromisos }),
        })
            .then(res => res.json())
            .then(payload => {
                if (msg) { msg.classList.remove('hidden'); msg.className = 'text-xs font-semibold text-[#00594E]'; msg.innerText = payload.message || 'Calificaciones guardadas.'; }
                previsualizarCalculoEvaluador();
            })
            .catch(() => {
                if (msg) { msg.classList.remove('hidden'); msg.className = 'text-xs font-semibold text-red-600'; msg.innerText = 'No se pudo guardar.'; }
            });
    }

    function guardarCalificacionCompetencias() {
        if (!selectedEvaluacionId) return;
        const competencias = Array.from(document.querySelectorAll('.competencia-input'))
            .filter(input => input.value !== '')
            .map(input => ({ nombre_competencia: input.dataset.nombre, tipo: input.dataset.tipo, calificacion_definitiva: parseFloat(input.value) }));

        const msg = document.getElementById('competencias-mensaje-evaluador');
        fetch(`/evaluaciones/${selectedEvaluacionId}/calificar-competencias`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            body: JSON.stringify({ competencias }),
        })
            .then(res => res.json())
            .then(payload => {
                if (msg) { msg.classList.remove('hidden'); msg.className = 'text-xs font-semibold text-[#00594E]'; msg.innerText = payload.message || 'Competencias guardadas.'; }
                previsualizarCalculoEvaluador();
            })
            .catch(() => {
                if (msg) { msg.classList.remove('hidden'); msg.className = 'text-xs font-semibold text-red-600'; msg.innerText = 'No se pudo guardar.'; }
            });
    }

    function renderResultado(calculo, containerId) {
        const cont = document.getElementById(containerId);
        if (!cont) return;
        if (!calculo || calculo.error) {
            cont.innerHTML = '<div class="text-xs text-slate-400">Aún no hay datos suficientes para calcular la nota.</div>';
            return;
        }
        const categoriaLabel = {
            SOBRESALIENTE: 'Sobresaliente (91-100)',
            BUENO: 'Bueno (81-90)',
            APROBADO_MEJORA: 'Aprobado - Susceptible de mejora (71-80)',
            NO_SATISFACTORIO: 'No satisfactorio (0-70)',
        }[calculo.categoria] || calculo.categoria || '-';
        const categoriaClass = {
            SOBRESALIENTE: 'bg-[#EAF2EF] text-[#00594E]',
            BUENO: 'bg-blue-50 text-blue-700',
            APROBADO_MEJORA: 'bg-amber-50 text-amber-700',
            NO_SATISFACTORIO: 'bg-red-50 text-red-600',
        }[calculo.categoria] || 'bg-slate-100 text-slate-600';

        const ejesHtml = calculo.subtotal_ejes_total ? `
            <div class="flex justify-between text-xs text-slate-600"><span>Ejes misionales</span><span class="font-bold">${calculo.subtotal_ejes_total}</span></div>
        ` : '';

        const prorrateoHtml = (calculo.nota_prorrateo !== null && calculo.nota_prorrateo !== undefined) ? `
            <div class="mt-3 pt-3 border-t border-slate-100 text-xs text-slate-600">
                <p class="font-bold text-slate-700">Evaluación eventual (RF3)</p>
                <p>Días laborados: ${calculo.dias_laborados ?? '-'} · Factor: ${calculo.factor_prorrateo ?? '-'}</p>
                <p>Nota antes de prorrateo: ${calculo.nota_final} → Nota con prorrateo: <span class="font-black text-[#00594E]">${calculo.nota_prorrateo}</span></p>
            </div>
        ` : '';

        cont.innerHTML = `
            <div class="rounded-2xl border border-slate-100 bg-slate-50/60 p-4 space-y-2">
                <div class="flex items-center justify-between">
                    <span class="text-3xl font-black text-slate-900">${calculo.nota_definitiva ?? '-'}</span>
                    <span class="text-[10px] font-black uppercase px-3 py-1.5 rounded-full ${categoriaClass}">${categoriaLabel}</span>
                </div>
                <div class="space-y-1 pt-2 border-t border-slate-100">
                    <div class="flex justify-between text-xs text-slate-600"><span>Compromisos (${calculo.pesos?.compromisos ?? '-'}%)</span><span class="font-bold">${calculo.subtotal_compromisos ?? '-'}</span></div>
                    <div class="flex justify-between text-xs text-slate-600"><span>Competencias comunes (${calculo.pesos?.comun ?? '-'}%)</span><span class="font-bold">${calculo.subtotal_comun ?? '-'}</span></div>
                    <div class="flex justify-between text-xs text-slate-600"><span>Competencias nivel jerárquico (${calculo.pesos?.nivel_jerarquico ?? '-'}%)</span><span class="font-bold">${calculo.subtotal_nivel ?? '-'}</span></div>
                    ${ejesHtml}
                </div>
                ${prorrateoHtml}
            </div>
        `;
    }

    function previsualizarCalculoEvaluador() {
        if (!selectedEvaluacionId) return;
        fetch(`/evaluaciones/${selectedEvaluacionId}/calculo`)
            .then(res => res.json())
            .then(calculo => renderResultado(calculo, 'resultado-contenido-evaluador'))
            .catch(() => {});
    }

    function calcularNotaFinalEvaluador() {
        if (!selectedEvaluacionId) return;
        fetch(`/evaluaciones/${selectedEvaluacionId}/calcular-final`, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        })
            .then(res => res.json())
            .then(payload => {
                if (payload.calculo) renderResultado(payload.calculo, 'resultado-contenido-evaluador');
                else if (payload.error) alert(payload.error);
            })
            .catch(() => {});
    }

    function toggleEvidenciaCompromisoSelect() {
        const componente = document.getElementById('evidencia-componente-evaluado')?.value || 'B';
        const contenedor = document.getElementById('evidencia-compromiso-contenedor-evaluado');
        const select = document.getElementById('evidencia-compromiso-evaluado');
        const esB = componente === 'B';
        if (contenedor) contenedor.classList.toggle('hidden', !esB);
        if (select) {
            select.required = esB;
            if (!esB) select.value = '';
        }
    }

    function cargarCompromisosEvaluado(ev) {
        if (!selectedEvaluacionId) return;
        fetch(`/evaluaciones/${selectedEvaluacionId}/compromisos`)
            .then(res => res.json())
            .then(payload => {
                const compromisos = payload.compromisos || [];
                const evidencias = payload.evidencias || [];
                const observaciones = payload.observaciones || [];
                const estado = payload.estado || {};
                const contenedor = document.getElementById('compromisos-lista-evaluado');
                if (!contenedor) return;
                contenedor.innerHTML = '';
                let sumaPesos = 0;
                const contador = compromisos.length;
                let targetWeight = calcularObjetivoCompromisos(ev, {
                    investigacion: document.getElementById('chk-eje-investigacion')?.checked || false,
                    proyeccion_social: document.getElementById('chk-eje-proyeccion')?.checked || false
                });
                const evidenciasPorCompromiso = agruparEvidenciasPorCompromiso(evidencias);
                const observacionesPorCompromiso = agruparObservacionesPorCompromiso(observaciones);
                compromisos.forEach(c => {
                    sumaPesos += parseFloat(c.porcentaje_peso || 0);
                    const div = document.createElement('div');
                    div.className = 'p-4 rounded-xl border bg-white';
                    const metasHtml = (c.metas || []).map(m => `<span class="bg-[#EAF2EF] text-[#00594E] text-[10px] font-bold px-2 py-0.5 rounded-full">${escapeHtml(m)}</span>`).join(' ');
                    const evidenciasHtml = renderEvidenciasCompactas(evidenciasPorCompromiso[String(c.id_compromiso)] || []);
                    const observacionHtml = renderObservacionEvaluado(observacionesPorCompromiso[String(c.id_compromiso)]);
                    div.innerHTML = `
                        <div class="flex items-center gap-2">
                            <span class="w-5 h-5 rounded-full bg-slate-100 flex items-center justify-center text-xs font-black text-slate-500">${c.numero_orden}</span>
                            <span class="font-bold text-slate-800 text-sm">${c.porcentaje_peso}% peso</span>
                        </div>
                        <p class="text-xs text-slate-600 mt-1.5">${escapeHtml(c.descripcion)}</p>
                        <div class="flex flex-wrap gap-1 mt-2.5">${metasHtml}</div>
                        <div class="mt-4 pt-3 border-t border-slate-100 space-y-2">
                            <div class="flex items-center gap-1.5 text-[11px] font-bold uppercase text-slate-500">
                                <span class="material-symbols-outlined text-sm">link</span>
                                Evidencias de este compromiso
                            </div>
                            <div class="space-y-2">${evidenciasHtml}</div>
                        </div>
                        ${observacionHtml}
                    `;
                    contenedor.appendChild(div);
                });
                actualizarOpcionesCompromisoEvidencia(compromisos);
                const sumaNode = document.getElementById('compromisos-suma-peso-evaluado');
                const contadorNode = document.getElementById('compromisos-contador-evaluado');
                if (sumaNode) sumaNode.innerText = `${sumaPesos}% / ${targetWeight}%`;
                if (contadorNode) contadorNode.innerText = `${contador} compromisos (mín 7, máx 10)`;

                const concertacionFirmada = !!estado.congelada;
                const formEvidencia = document.getElementById('form-evidencia-evaluado');
                const avisoEvidenciaBloqueada = document.getElementById('evidencia-bloqueada-evaluado');
                if (formEvidencia) formEvidencia.classList.toggle('hidden', !concertacionFirmada);
                if (avisoEvidenciaBloqueada) avisoEvidenciaBloqueada.classList.toggle('hidden', concertacionFirmada);

                renderEvidenciasEvaluado(evidencias);

                const btnFirmar = document.getElementById('btn-firmar-evaluado');
                const locked = !!estado.congelada || !!estado.evaluado_firmado;
                const okToSign = !!estado.evaluador_firmado && !locked;
                if (btnFirmar) {
                    btnFirmar.disabled = !okToSign;
                    btnFirmar.innerText = locked ? 'Firmado' : 'Firmar Concertación';
                }

                const resultadoBloque = document.getElementById('resultado-bloque-evaluado');
                if (resultadoBloque) resultadoBloque.classList.toggle('hidden', !concertacionFirmada);
                if (concertacionFirmada) cargarResultadoEvaluado();
            });
    }

    function cargarResultadoEvaluado() {
        if (!selectedEvaluacionId) return;
        fetch(`/evaluaciones/${selectedEvaluacionId}/calculo`)
            .then(res => res.json())
            .then(calculo => renderResultado(calculo, 'resultado-contenido-evaluado'))
            .catch(() => {});
    }

    function renderEvidenciasLectura(evidencias, listaId, contadorId) {
        evidencias = evidencias || [];
        const lista = document.getElementById(listaId);
        const contador = document.getElementById(contadorId);
        if (contador) contador.innerText = `${contarEvidencias(evidencias)} registradas`;
        if (!lista) return;

        lista.innerHTML = '';
        if (!evidencias.length) {
            const empty = document.createElement('div');
            empty.className = 'rounded-xl border border-dashed border-slate-200 bg-white p-4 text-xs text-slate-500 text-center';
            empty.innerText = 'Aún no hay evidencias asociadas a compromisos.';
            lista.appendChild(empty);
            return;
        }

        evidencias.forEach(evidencia => {
            const item = document.createElement('div');
            item.className = 'flex flex-col sm:flex-row sm:items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white p-3';

            const content = document.createElement('div');
            content.className = 'min-w-0';

            const description = document.createElement('p');
            description.className = 'text-xs font-bold text-slate-800 truncate';
            description.innerText = evidencia.descripcion || 'Evidencia registrada';

            const date = document.createElement('p');
            date.className = 'text-[10px] text-slate-400 mt-0.5';
            const componenteLabel = evidencia.componente && evidencia.componente !== 'B' ? ` · Componente ${evidencia.componente}` : '';
            date.innerText = (evidencia.fecha_inclusion || '') + componenteLabel;

            const estado = document.createElement('span');
            estado.innerHTML = badgeEstadoAprobacion(evidencia.estado_aprobacion);

            const link = document.createElement('a');
            link.className = 'inline-flex items-center justify-center gap-1 text-xs font-bold text-[#00594E] hover:underline shrink-0';
            link.href = evidencia.url_o_ubicacion || '#';
            link.target = '_blank';
            link.rel = 'noopener noreferrer';
            link.innerHTML = '<span class="material-symbols-outlined text-base">open_in_new</span><span>Abrir URL</span>';

            content.appendChild(description);
            content.appendChild(date);
            content.appendChild(estado);
            if (evidencia.estado_aprobacion === 'RECHAZADA' && evidencia.observacion_aprobacion) {
                const motivo = document.createElement('p');
                motivo.className = 'text-[10px] text-red-600 mt-1';
                motivo.innerText = evidencia.observacion_aprobacion;
                content.appendChild(motivo);
            }
            item.appendChild(content);
            item.appendChild(link);
            lista.appendChild(item);
        });
    }

    function renderEvidenciasEvaluado(evidencias) {
        renderEvidenciasLectura(evidencias, 'evidencias-lista-evaluado', 'evidencias-contador-evaluado');
    }

    function actualizarOpcionesCompromisoEvidencia(compromisos = []) {
        const select = document.getElementById('evidencia-compromiso-evaluado');
        if (!select) return;

        const valorActual = select.value;
        select.innerHTML = '<option value="">Selecciona un compromiso</option>';

        compromisos.forEach(c => {
            const option = document.createElement('option');
            option.value = c.id_compromiso;
            option.innerText = `Compromiso ${c.numero_orden} - ${c.porcentaje_peso}%`;
            select.appendChild(option);
        });

        if ([...select.options].some(option => option.value === valorActual)) {
            select.value = valorActual;
        }
    }

    function mostrarMensajeEvidencia(texto, ok = true) {
        const mensaje = document.getElementById('evidencia-mensaje-evaluado');
        if (!mensaje) return;
        mensaje.classList.remove('hidden', 'text-red-600', 'text-[#00594E]');
        mensaje.classList.add(ok ? 'text-[#00594E]' : 'text-red-600');
        mensaje.innerText = texto;
    }

    function guardarEvidenciaCompromiso(e, compromisoId, origen = 'evaluado') {
        e.preventDefault();
        if (!selectedEvaluacionId) return;

        const form = e.target;
        const button = form?.querySelector('button[type="submit"]');
        const descripcion = form?.querySelector('[name="descripcion"]')?.value || '';
        const url = form?.querySelector('[name="url"]')?.value || '';
        const componente = form?.querySelector('[name="componente"]')?.value || 'B';
        if (!form || !url.trim() || (componente === 'B' && !compromisoId)) return;

        if (button) {
            button.disabled = true;
            button.innerText = 'Guardando...';
        }

        fetch(`/evaluaciones/${selectedEvaluacionId}/evidencias`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ id_compromiso: componente === 'B' ? compromisoId : null, componente, descripcion, url })
        })
            .then(async res => {
                const payload = await res.json().catch(() => ({}));
                if (!res.ok) {
                    const error = payload.message || Object.values(payload.errors || {})?.[0]?.[0] || 'No se pudo guardar la evidencia.';
                    throw new Error(error);
                }
                form.reset();
                if (origen === 'evaluado') {
                    toggleEvidenciaCompromisoSelect();
                    mostrarMensajeEvidencia('Evidencia registrada correctamente.');
                    if (selectedEvaluacionData) cargarCompromisosEvaluado(selectedEvaluacionData);
                } else if (selectedEvaluacionData) {
                    cargarCompromisosEvaluador(selectedEvaluacionData);
                }
            })
            .catch(error => {
                if (origen === 'evaluado') {
                    mostrarMensajeEvidencia(error.message, false);
                } else {
                    alert(error.message);
                }
            })
            .finally(() => {
                if (button) {
                    button.disabled = false;
                    button.innerText = 'Guardar URL';
                }
            });
    }

    function guardarEvidenciaEvaluado(e) {
        const compromisoId = document.getElementById('evidencia-compromiso-evaluado')?.value || '';
        guardarEvidenciaCompromiso(e, compromisoId, 'evaluado');
    }

    function mostrarMensajeObservacionCompromiso(idCompromiso, texto, ok = true) {
        const mensaje = document.getElementById(`observacion-mensaje-${idCompromiso}`);
        if (!mensaje) return;
        mensaje.classList.remove('hidden', 'text-red-600', 'text-[#00594E]');
        mensaje.classList.add(ok ? 'text-[#00594E]' : 'text-red-600');
        mensaje.innerText = texto;
    }

    function guardarObservacionCompromiso(e, idCompromiso, confirmar = false) {
        e.preventDefault();
        if (!selectedEvaluacionId) return;

        const form = e.target;
        const button = form?.querySelector(confirmar ? 'button[type="button"]' : 'button[type="submit"]');
        const textarea = document.getElementById(`observacion-compromiso-${idCompromiso}`);
        const texto = textarea?.value || '';
        if (!texto.trim()) return;

        if (button) {
            button.disabled = true;
            button.innerText = confirmar ? 'Confirmando...' : 'Guardando...';
        }

        fetch(`/evaluaciones/${selectedEvaluacionId}/observaciones`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ id_compromiso: idCompromiso, texto, confirmar })
        })
            .then(async res => {
                const payload = await res.json().catch(() => ({}));
                if (!res.ok) {
                    const error = payload.message || Object.values(payload.errors || {})?.[0]?.[0] || 'No se pudo guardar la observación.';
                    throw new Error(error);
                }
                mostrarMensajeObservacionCompromiso(idCompromiso, confirmar ? 'Observación confirmada correctamente.' : 'Observación guardada correctamente.');
                if (selectedEvaluacionData) cargarCompromisosEvaluador(selectedEvaluacionData);
            })
            .catch(error => {
                mostrarMensajeObservacionCompromiso(idCompromiso, error.message, false);
            })
            .finally(() => {
                if (button) {
                    button.disabled = false;
                    button.innerText = confirmar ? 'Confirmar' : 'Guardar';
                }
            });
    }

    function confirmarObservacionCompromiso(idCompromiso) {
        const textarea = document.getElementById(`observacion-compromiso-${idCompromiso}`);
        const form = textarea?.closest('form');
        if (!textarea?.value?.trim() || !form) return;
        guardarObservacionCompromiso({ preventDefault: () => {}, target: form }, idCompromiso, true);
    }

    function guardarEjesMisionales() {
        if (!selectedEvaluacionId) return;
        const investigacion = document.getElementById('chk-eje-investigacion')?.checked || false;
        const proyeccion = document.getElementById('chk-eje-proyeccion')?.checked || false;
        fetch(`/evaluaciones/${selectedEvaluacionId}/ejes`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ investigacion: investigacion, proyeccion_social: proyeccion })
        }).then(() => {
            if (selectedEvaluacionData) cargarCompromisosEvaluado(selectedEvaluacionData);
        });
    }

    function agregarCompromisoEvaluador(e) {
        e.preventDefault();
        if (!selectedEvaluacionId) return;
        fetch(`/evaluaciones/${selectedEvaluacionId}/compromisos`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({
                descripcion: document.getElementById('comp-descripcion-evaluador')?.value || '',
                porcentaje_peso: parseFloat(document.getElementById('comp-peso-evaluador')?.value || '0'),
                metas: (document.getElementById('comp-metas-evaluador')?.value || '').split(',').map(m => m.trim()).filter(Boolean)
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.error) {
                alert(data.error);
            } else {
                document.getElementById('form-nuevo-compromiso-evaluador')?.reset();
                if (selectedEvaluacionData) cargarCompromisosEvaluador(selectedEvaluacionData);
            }
        });
    }

    function eliminarCompromisoEvaluador(id) {
        if (!confirm('¿Deseas eliminar este compromiso?')) return;
        fetch(`/compromisos/${id}`, {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
        })
        .then(res => res.json())
        .then(data => {
            if (data.error) {
                alert(data.error);
            } else if (selectedEvaluacionData) {
                cargarCompromisosEvaluador(selectedEvaluacionData);
            }
        });
    }

    // --- S4: Módulo Instancia Externa (Vicerrectoría Investigación / Proyección Social / CEDP) ---

    let selectedEvalExternaId = null;

    const EJE_LABELS = {
        DOCENCIA: 'Docencia',
        INVESTIGACION: 'Investigación',
        PROYECCION_SOCIAL: 'Proyección Social',
    };

    function cargarListaInstanciaExterna() {
        const contenedor = document.getElementById('instancia-externa-lista');
        if (!contenedor) return;
        fetch('/instancia-externa/evaluaciones')
            .then(res => res.json())
            .then(payload => {
                const evaluaciones = payload.evaluaciones || [];
                contenedor.innerHTML = '';
                if (!evaluaciones.length) {
                    contenedor.innerHTML = '<div class="py-8 text-center text-slate-500 text-xs">No hay evaluados de Acuerdo de Gestión con ejes misionales habilitados.</div>';
                    return;
                }
                evaluaciones.forEach(ev => {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'evaluacion-externa-card w-full text-left p-4 rounded-2xl border border-slate-200 bg-white cursor-pointer hover:border-[#00594E] transition';
                    const ejesTexto = (ev.ejes_activos || []).map(e => EJE_LABELS[e] || e).join(' · ');
                    const cargados = (ev.calificaciones || []).length;
                    btn.innerHTML = `
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h4 class="font-bold text-slate-900 text-sm leading-snug">${escapeHtml(ev.evaluado_nombres || '')} ${escapeHtml(ev.evaluado_apellidos || '')}</h4>
                                <p class="text-xs text-slate-500 mt-0.5">${escapeHtml(ev.evaluado_cargo || '')} - ${escapeHtml(ev.evaluado_area || '')}</p>
                            </div>
                            <span class="text-[10px] font-bold uppercase px-2 py-1 rounded-full ${cargados ? 'bg-[#EAF2EF] text-[#00594E]' : 'bg-amber-50 text-amber-700'}">${cargados ? cargados + ' nota(s)' : 'Sin notas'}</span>
                        </div>
                        <div class="flex justify-between items-center mt-3">
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-[#EAF2EF] text-[#00594E]">AG</span>
                            <span class="text-[9px] uppercase tracking-wide font-bold text-slate-400">${escapeHtml(ejesTexto)}</span>
                        </div>
                    `;
                    btn.onclick = () => abrirInstanciaExterna(btn, ev);
                    contenedor.appendChild(btn);
                });
            })
            .catch(() => {
                contenedor.innerHTML = '<div class="py-8 text-center text-red-500 text-xs">No se pudo cargar el listado.</div>';
            });
    }

    function abrirInstanciaExterna(card, ev) {
        selectedEvalExternaId = ev.id_evaluacion;

        const panel = document.getElementById('panel-instancia-externa');
        const empty = document.getElementById('panel-instancia-externa-empty');
        if (empty) empty.classList.add('hidden');
        if (panel) panel.classList.remove('hidden');

        const setText = (id, value) => {
            const node = document.getElementById(id);
            if (node) node.innerText = value;
        };
        setText('instancia-externa-nombre', `${ev.evaluado_nombres || ''} ${ev.evaluado_apellidos || ''}`.trim());
        setText('instancia-externa-detalle', `${ev.evaluado_cargo || '-'} - ${ev.evaluado_area || '-'}`);

        const notasExistentes = (ev.calificaciones || []).reduce((acc, c) => {
            acc[c.eje] = c;
            return acc;
        }, {});

        const contenedor = document.getElementById('instancia-externa-ejes-contenedor');
        if (contenedor) {
            contenedor.innerHTML = (ev.ejes_activos || []).map(eje => {
                const existente = notasExistentes[eje];
                return `
                    <div class="p-3 rounded-xl border border-slate-100 bg-slate-50/50">
                        <p class="text-xs font-bold text-slate-800">${EJE_LABELS[eje] || eje}</p>
                        ${existente ? `<p class="text-[10px] text-slate-400 mt-0.5">Última carga: ${escapeHtml(existente.fecha_ingreso || '')} (${escapeHtml(existente.origen || '-')})</p>` : ''}
                        <div class="mt-2 flex items-center gap-2">
                            <label class="text-[10px] font-bold text-slate-500 uppercase">Calificación (0-100)</label>
                            <input type="number" min="0" max="100" step="0.01" class="eje-externa-input w-24 text-xs rounded-lg border border-slate-200 p-1.5" data-eje="${eje}" value="${existente?.calificacion ?? ''}" />
                        </div>
                        <textarea class="eje-externa-observacion mt-2 w-full text-xs rounded-lg border border-slate-200 p-2" rows="2" data-eje="${eje}" placeholder="Observaciones (opcional)">${escapeHtml(existente?.observaciones || '')}</textarea>
                    </div>`;
            }).join('') || '<p class="text-xs text-slate-400">Este evaluado no tiene ejes misionales activos.</p>';
        }

        document.querySelectorAll('.evaluacion-externa-card').forEach(el => el.classList.remove('ring-2', 'ring-[#00594E]'));
        if (card) card.classList.add('ring-2', 'ring-[#00594E]');
    }

    function guardarNotasInstanciaExterna(e) {
        e.preventDefault();
        if (!selectedEvalExternaId) return;

        const ejes = Array.from(document.querySelectorAll('.eje-externa-input'))
            .filter(input => input.value !== '')
            .map(input => {
                const eje = input.dataset.eje;
                const observacion = document.querySelector(`.eje-externa-observacion[data-eje="${eje}"]`)?.value || '';
                return { tipo_eje: eje, calificacion: parseFloat(input.value), observacion };
            });

        const msg = document.getElementById('instancia-externa-mensaje');
        if (!ejes.length) {
            if (msg) { msg.classList.remove('hidden'); msg.className = 'text-xs font-semibold text-red-600'; msg.innerText = 'Ingresa al menos una calificación.'; }
            return;
        }

        fetch(`/evaluaciones/${selectedEvalExternaId}/ejes-externa`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            body: JSON.stringify({ ejes }),
        })
            .then(async res => {
                const payload = await res.json().catch(() => ({}));
                if (!res.ok) throw new Error(payload.message || 'No se pudo guardar.');
                if (msg) { msg.classList.remove('hidden'); msg.className = 'text-xs font-semibold text-[#00594E]'; msg.innerText = payload.message || 'Notas guardadas.'; }
                cargarListaInstanciaExterna();
            })
            .catch(error => {
                if (msg) { msg.classList.remove('hidden'); msg.className = 'text-xs font-semibold text-red-600'; msg.innerText = error.message; }
            });
    }

    window.addEventListener('DOMContentLoaded', () => {
        const activeRole = "{{ $rolActivo }}";
        if (activeRole === 'admin') {
            navegarMenu(null, 'usuarios');
        } else if (activeRole === 'evaluador') {
            navegarMenu(null, 'evaluaciones-evaluador');
        } else if (activeRole === 'instancia_externa') {
            navegarMenu(null, 'instancia-externa');
        } else {
            navegarMenu(null, 'evaluaciones');
        }
        if (activeRole === 'evaluador') {
            const firstEvaluacion = document.querySelector('.evaluacion-evaluador-card');
            if (firstEvaluacion) firstEvaluacion.click();
        }
        if (activeRole === 'instancia_externa') {
            cargarListaInstanciaExterna();
        }
        const firstCard = document.querySelector('.empleado-card');
        if (activeRole === 'admin' && firstCard) {
            const raw = {
                nombres: firstCard.querySelector('h3')?.innerText?.split(' ').slice(0, -1).join(' ') || '',
                apellidos: firstCard.querySelector('h3')?.innerText?.split(' ').slice(-1).join(' ') || '',
                nombre_cargo: firstCard.dataset.cargo || '',
                correo_institucional: firstCard.dataset.correo || '',
                documento_identidad: firstCard.dataset.cedula || '',
                tipo_documento: '',
                nombre_area: firstCard.dataset.area || '',
                activo: (firstCard.dataset.estado || '').toLowerCase() === 'activo'
            };
            seleccionarEmpleado(firstCard, raw);
        }
    });
</script>
@endsection
