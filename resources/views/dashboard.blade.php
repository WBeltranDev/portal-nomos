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
        background: linear-gradient(135deg, #00473d 0%, #00594e 100%);
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

    .evaluado-tab-btn,
    .evaluador-tab-btn {
        display: inline-flex;
        align-items: center;
        gap: 0.375rem;
        padding: 0.55rem 1.1rem;
        border-radius: 0.75rem;
        font-size: 0.78rem;
        font-weight: 700;
        color: #64748b;
        background: #f1f5f9;
        border: 1px solid transparent;
        transition: all 0.15s ease;
        cursor: pointer;
        white-space: nowrap;
    }

    .evaluado-tab-btn:hover,
    .evaluador-tab-btn:hover {
        color: #00594E;
        background: #e6f2f0;
    }

    .evaluado-tab-btn.active,
    .evaluador-tab-btn.active {
        background: #00594E;
        color: white;
        box-shadow: 0 4px 12px rgba(0, 89, 78, 0.25);
    }

    .evaluado-tab-panel,
    .evaluador-tab-panel {
        animation: fadeIn 0.2s ease;
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(4px); }
        to { opacity: 1; transform: translateY(0); }
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
            <img src="/logo.png" alt="SERAG" class="h-9 sm:h-10 w-auto object-contain drop-shadow -mt-1" />
            <div class="hidden sm:flex flex-col">
                <span class="text-[10px] sm:text-xs font-bold uppercase tracking-[0.18em] text-[#B5A160]">SERAG</span>
                <span class="text-xs sm:text-sm font-semibold text-white/90">Sistema de Evaluación del Desempeño</span>
            </div>
            <div class="sm:hidden text-xs font-semibold text-white/90">SERAG</div>
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
                <button class="sidebar-link w-full flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm text-slate-700 transition" onclick="navegarMenu(this, 'recursos-admin')">
                    <span class="material-symbols-outlined">gavel</span>
                    Recursos y planes
                </button>
                <button class="sidebar-link w-full flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm text-slate-700 transition" onclick="navegarMenu(this, 'traslados')">
                    <span class="material-symbols-outlined">swap_horiz</span>
                    Traslados
                </button>
                @endif

                @if ($rolActivo !== 'instancia_externa')
                <button class="sidebar-link w-full @if($rolActivo !== 'admin') active @endif flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm text-slate-700 transition" onclick="navegarMenu(this, '{{ $rolActivo === 'evaluador' ? 'evaluaciones-evaluador' : 'evaluaciones' }}')">
                    <span class="material-symbols-outlined">fact_check</span>
                    Evaluaciones
                </button>
                @if ($rolActivo === 'evaluado')
                <button class="sidebar-link w-full flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm text-slate-700 transition" onclick="navegarMenu(this, 'reportes')">
                    <span class="material-symbols-outlined">description</span>
                    Exportar PDF
                </button>
                @endif
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
            @if(session('success_periodo') || session('success_ponderacion') || session('success_asignacion') || session('success_import') || session('success_firma') || session('success_traslado'))
                <div class="mb-6 p-4 bg-green-50 border border-green-200 text-green-700 rounded-2xl flex items-center gap-3 text-sm">
                    <span class="material-symbols-outlined">check_circle</span>
                    <p>{{ session('success_periodo') ?? session('success_ponderacion') ?? session('success_asignacion') ?? session('success_import') ?? session('success_firma') ?? session('success_traslado') }}</p>
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

            <!-- SECTION: RECURSOS Y PLANES (Admin / Talento Humano - S6) -->
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
                        <p class="text-sm text-slate-500 mb-4">Registra el traslado de un funcionario a otra dependencia con cambio de evaluador. Si hay un periodo abierto se genera automáticamente una evaluación PARCIAL prorrateada por los días laborados en la dependencia origen.</p>
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
                                <label class="block text-[10px] font-bold text-slate-600 uppercase mb-1">Motivo (opcional)</label>
                                <input type="text" name="motivo" class="w-full text-xs rounded-xl border border-slate-200 p-2.5 bg-white outline-none focus:border-[#00594E]" placeholder="Motivo del traslado" />
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-600 uppercase mb-1">Nombre o referencia del funcionario para la evaluación parcial</label>
                                <input type="text" name="referencia" maxlength="200" class="w-full text-xs rounded-xl border border-slate-200 p-2.5 bg-white outline-none focus:border-[#00594E]" placeholder="Ej. Juan Pérez — Traslado a Secretaría General" />
                                <p class="text-[9px] text-slate-400 mt-1">Identifica la evaluación PARCIAL que se genera en el periodo abierto con el nombre o referencia del funcionario.</p>
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
                @if($planesPendientesEvaluador->isNotEmpty())
                <div class="panel-card rounded-3xl p-5 border-amber-200 border">
                    <div class="flex items-start gap-3">
                        <span class="material-symbols-outlined text-amber-600 mt-0.5">warning</span>
                        <div class="min-w-0">
                            <p class="text-sm font-black text-slate-800">Acción requerida: planes de mejoramiento pendientes</p>
                            <p class="text-xs text-slate-500 mt-1">Tienes <b>{{ $planesPendientesEvaluador->count() }}</b> evaluación(es) con plan de mejoramiento sin concertar ni firmar. Debes resolverlas antes de cerrar el ciclo:</p>
                            <div class="mt-2 space-y-1.5">
                                @foreach($planesPendientesEvaluador as $pp)
                                <div class="flex items-center justify-between gap-3 rounded-xl border border-amber-100 bg-amber-50/60 px-3 py-2 text-xs">
                                    <span class="font-bold text-slate-700">{{ $pp->evaluado_nombres }} {{ $pp->evaluado_apellidos }}</span>
                                    <span class="text-[10px] font-bold uppercase text-amber-700">{{ $pp->sistema === 'RENDIMIENTO_LABORAL' ? 'RL' : 'AG' }} · {{ $pp->categoria_final }}</span>
                                </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
                @endif

                <div id="bloque-recursos-mios-evaluador" class="panel-card rounded-3xl p-6 hidden">
                    <div class="flex items-center justify-between gap-3 mb-3">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-[0.22em] text-[#00594E]">Recursos</p>
                            <h3 class="text-lg font-black text-slate-900">Apelaciones por decidir</h3>
                        </div>
                        <span id="recursos-mios-contador" class="text-[10px] font-bold rounded-full px-2.5 py-1 bg-slate-100 text-slate-500">0</span>
                    </div>
                    <p class="text-xs text-slate-500 mb-4">Como superior jerárquico del evaluador, debes decidir las apelaciones radicadas contra las evaluaciones de tu equipo.</p>
                    <div id="recursos-mios-lista" class="space-y-2"></div>
                </div>

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
                                            @if($ev->tipo_nombre === 'PARCIAL' && $ev->referencia)
                                                <p class="text-[10px] font-semibold text-[#00594E] mt-1">{{ $ev->referencia }}</p>
                                            @endif
                                            <div class="mt-2 space-y-0.5 text-[10px] text-slate-500">
                                                <p><span class="font-semibold text-slate-600">Período de evaluación:</span> {{ $ev->anio }} · Semestre {{ $ev->semestre }}</p>
                                                <p><span class="font-semibold text-slate-600">Fechas de calificación:</span> {{ \Carbon\Carbon::parse($ev->fecha_inicio)->format('d/m/Y') }} al {{ \Carbon\Carbon::parse($ev->fecha_fin)->format('d/m/Y') }}</p>
                                            </div>
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

                            <div class="flex gap-2 flex-wrap mt-4">
                                <button type="button" id="tabbtn-evaluador-compromisos" onclick="cambiarTabEvaluador('compromisos')" class="evaluador-tab-btn active">Compromisos</button>
                                <button type="button" id="tabbtn-evaluador-competencias" onclick="cambiarTabEvaluador('competencias')" class="evaluador-tab-btn">Competencias</button>
                                <button type="button" id="tabbtn-evaluador-ejes" onclick="cambiarTabEvaluador('ejes')" class="evaluador-tab-btn hidden">Ejes misionales</button>
                                <button type="button" id="tabbtn-evaluador-recursos" onclick="cambiarTabEvaluador('recursos')" class="evaluador-tab-btn">Recursos</button>
                            </div>

                            <div id="tab-evaluador-compromisos" class="evaluador-tab-panel">
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

                                <div id="seccion-firmar-evaluador" class="mt-6 pt-4 border-t border-slate-100 flex items-center justify-between gap-4">
                                <div class="text-xs text-slate-500 leading-tight">Podrás firmar cuando tengas de 7 a 10 compromisos que sumen exactamente el porcentaje objetivo.</div>

                                <form id="form-firmar-evaluacion" method="POST" action="" onsubmit="firmarConcertacion(event, 'evaluador')" class="shrink-0">
                                @csrf
                                <button type="submit" id="btn-firmar-evaluador" class="bg-[#00594E] text-white px-6 py-2.5 rounded-xl text-sm font-bold hover:brightness-110 transition disabled:opacity-50" disabled>Firmar concertación</button>
                                </form>
                                </div>

                                <div id="firmas-concertacion-evaluador" class="mt-3 hidden"></div>
                            </div>

                            <div id="tab-evaluador-competencias" class="evaluador-tab-panel hidden">
                                <p id="aviso-competencias-evaluador" class="mt-4 rounded-xl border border-dashed border-slate-200 bg-white p-4 text-xs text-slate-500 text-center">Las competencias se habilitarán cuando la concertación esté firmada y congelada.</p>
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
                            </div>

                            <div id="tab-evaluador-ejes" class="evaluador-tab-panel hidden">
                                <p id="aviso-ejes-evaluador" class="mt-4 rounded-xl border border-dashed border-slate-200 bg-white p-4 text-xs text-slate-500 text-center">Los ejes misionales se habilitarán cuando la concertación esté firmada y congelada.</p>
                                <div id="ejes-misionales-bloque-evaluador" class="my-6 pt-4 border-t border-slate-100 space-y-3 hidden">
                                    <div class="flex items-center justify-between gap-3">
                                        <h4 class="text-sm font-bold text-slate-800 flex items-center gap-2">
                                            <span class="material-symbols-outlined text-base">school</span>
                                            Ejes misionales
                                        </h4>
                                    </div>
                                    <p class="text-[10px] text-slate-400 font-semibold">El pliego indica que el evaluador consolida estas notas (recibidas de las instancias externas cuando aplique) y las socializa al evaluado.</p>
                                    <div id="ejes-misionales-inputs-evaluador" class="space-y-3"></div>
                                    <div class="flex items-center justify-between gap-3">
                                        <span id="ejes-misionales-mensaje-evaluador" class="hidden text-xs font-semibold"></span>
                                        <button type="button" id="btn-guardar-ejes-evaluador" onclick="guardarEjesMisionalesEvaluador()" class="bg-[#00594E] text-white px-4 py-2 rounded-xl text-xs font-bold hover:brightness-110 transition ml-auto disabled:opacity-50 disabled:cursor-not-allowed">Guardar ejes misionales</button>
                                    </div>
                                </div>
                            </div>

                            <div id="tab-evaluador-recursos" class="evaluador-tab-panel hidden">
                                <!-- S6: Recursos recibidos (reposición dirigida al evaluador) -->
                                <div id="bloque-recursos-evaluador" class="my-6 pt-4 border-t border-slate-100 space-y-3 hidden">
                                    <div class="flex items-center justify-between gap-3">
                                        <h4 class="text-sm font-bold text-slate-800 flex items-center gap-2">
                                            <span class="material-symbols-outlined text-base">gavel</span>
                                            Recursos recibidos
                                        </h4>
                                        <span id="recursos-contador-evaluador" class="text-[10px] font-bold rounded-full px-2.5 py-1 bg-slate-100 text-slate-500">0</span>
                                    </div>
                                    <div id="recursos-lista-evaluador" class="space-y-2"></div>
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
                                        <button type="button" id="btn-calcular-nota-final" onclick="calcularNotaFinalEvaluador()" class="bg-[#B5A160] text-white px-3 py-2 rounded-xl text-xs font-bold hover:brightness-110 transition disabled:opacity-50 disabled:cursor-not-allowed">Calcular nota final</button>
                                    </div>
                                </div>
                                <div id="resultado-contenido-evaluador"></div>
                            </div>

                            <!-- S6: Plan de mejoramiento condicionado (evaluador) -->
                            <div id="bloque-plan-mejoramiento-evaluador" class="my-6 pt-4 border-t border-slate-100 space-y-3 hidden">
                                <div class="flex items-center justify-between gap-3">
                                    <h4 class="text-sm font-bold text-slate-800 flex items-center gap-2">
                                        <span class="material-symbols-outlined text-base">trending_up</span>
                                        Plan de mejoramiento
                                    </h4>
                                    <span id="plan-estado-evaluador" class="text-[10px] font-bold uppercase rounded-full px-2.5 py-1 bg-amber-50 text-amber-700 hidden">Pendiente</span>
                                </div>
                                <div id="plan-aviso-evaluador" class="hidden rounded-xl border border-amber-200 bg-amber-50 p-3 text-[11px] font-semibold text-amber-700 flex items-center gap-2">
                                    <span class="material-symbols-outlined text-base">warning</span>
                                    <span>Esta evaluación requiere plan de mejoramiento. Tu flujo queda bloqueado hasta concertarlo y firmarlo.</span>
                                </div>
                                <form id="form-plan-mejoramiento-evaluador" onsubmit="guardarPlanMejoramiento(event)" class="grid gap-3 rounded-2xl border border-slate-100 bg-slate-50/50 p-4">
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-600 uppercase mb-1">Temas del plan de mejoramiento</label>
                                        <textarea id="plan-temas-evaluador" rows="4" class="w-full text-xs rounded-xl border border-slate-200 p-2.5 bg-white outline-none focus:border-[#00594E]" placeholder="Describe los temas, acciones, metas y plazos del plan de mejoramiento..." required></textarea>
                                    </div>
                                    <div class="flex items-center justify-between gap-3">
                                        <span id="plan-mensaje-evaluador" class="hidden text-xs font-semibold"></span>
                                        <button type="submit" id="btn-guardar-plan-evaluador" class="bg-[#00594E] text-white px-4 py-2 rounded-xl text-xs font-bold hover:brightness-110 transition ml-auto">Guardar plan</button>
                                    </div>
                                </form>
                                <div class="flex items-center justify-between gap-3">
                                    <p class="text-xs text-slate-500 leading-tight">Cuando el plan esté guardado, el evaluado podrá revisarlo y firmarlo.</p>
                                    <button type="button" id="btn-firmar-plan-evaluador" onclick="firmarPlanMejoramiento('evaluador')" class="bg-[#B5A160] text-white px-4 py-2 rounded-xl text-xs font-bold hover:brightness-110 transition hidden">Firmar plan de mejoramiento</button>
                                </div>
                                <div id="plan-firmas-evaluador" class="grid sm:grid-cols-2 gap-2 text-xs"></div>
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
                                    @if($ev->tipo_nombre === 'PARCIAL' && $ev->referencia)
                                        <p class="text-[10px] font-semibold text-[#00594E] mt-1">{{ $ev->referencia }}</p>
                                    @endif
                                    <p class="text-xs text-slate-500 mt-0.5">Evaluador: {{ $ev->evalador_nombres ?? 'Mi Evaluador' }} {{ $ev->evalador_apellidos ?? '' }}</p>
                                    <div class="mt-2 space-y-0.5 text-[10px] text-slate-500">
                                        <p><span class="font-semibold text-slate-600">Período de evaluación:</span> {{ $ev->anio }} · Semestre {{ $ev->semestre }}</p>
                                        <p><span class="font-semibold text-slate-600">Fechas de calificación:</span> {{ \Carbon\Carbon::parse($ev->fecha_inicio)->format('d/m/Y') }} al {{ \Carbon\Carbon::parse($ev->fecha_fin)->format('d/m/Y') }}</p>
                                    </div>
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

                        <!-- S6: Tabs del evaluado -->
                        <div class="mt-5 flex flex-wrap gap-2">
                            <button type="button" id="tabbtn-evaluado-compromisos" onclick="cambiarTabEvaluado('compromisos')" class="evaluado-tab-btn active">Compromisos</button>
                            <button type="button" id="tabbtn-evaluado-competencias" onclick="cambiarTabEvaluado('competencias')" class="evaluado-tab-btn">Competencias</button>
                            <button type="button" id="tabbtn-evaluado-ejes" onclick="cambiarTabEvaluado('ejes')" class="evaluado-tab-btn hidden">Ejes misionales</button>
                            <button type="button" id="tabbtn-evaluado-recursos" onclick="cambiarTabEvaluado('recursos')" class="evaluado-tab-btn">Recursos</button>
                        </div>

                        <!-- Tab: Compromisos -->
                        <div id="tab-evaluado-compromisos" class="evaluado-tab-panel">
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

                            <div id="firma-evaluado-seccion" class="mt-6 pt-4 border-t border-slate-100 space-y-3">
                                <div id="seccion-firmar-evaluado" class="flex items-center justify-between gap-4">
                                    <div class="text-xs text-slate-500 leading-tight">Podrás firmar cuando el evaluador haya firmado la concertación.</div>
                                    <form id="form-firmar-evaluado" method="POST" action="" onsubmit="firmarConcertacion(event, 'evaluado')" class="shrink-0">
                                        @csrf
                                        <button type="submit" id="btn-firmar-evaluado" class="bg-[#00594E] text-white px-6 py-2.5 rounded-xl text-sm font-bold hover:brightness-110 transition disabled:opacity-50" disabled>Firmar Concertación</button>
                                    </form>
                                </div>


                                <div id="firmas-concertacion-evaluado" class="mt-3 hidden"></div>
                            </div>

                            <!-- S6: Plan de mejoramiento condicionado - evaluado -->
                            <div id="bloque-plan-mejoramiento-evaluado" class="mt-6 pt-4 border-t border-slate-100 space-y-3 hidden">
                                <div class="flex items-center justify-between gap-3">
                                    <h4 class="text-sm font-bold text-slate-800 flex items-center gap-2">
                                        <span class="material-symbols-outlined text-base">trending_up</span>
                                        Plan de mejoramiento
                                    </h4>
                                    <span id="plan-estado-evaluado" class="text-[10px] font-bold uppercase rounded-full px-2.5 py-1 bg-amber-50 text-amber-700 hidden">Pendiente</span>
                                </div>
                                <div id="plan-aviso-evaluado" class="hidden rounded-xl border border-amber-200 bg-amber-50 p-3 text-[11px] font-semibold text-amber-700 flex items-center gap-2">
                                    <span class="material-symbols-outlined text-base">warning</span>
                                    <span>De acuerdo con tu calificación, debes concertar y firmar un plan de mejoramiento con tu evaluador.</span>
                                </div>
                                <div id="plan-contenido-evaluado"></div>
                                <div class="flex items-center justify-between gap-3">
                                    <p id="plan-firmas-evaluado" class="text-xs text-slate-500"></p>
                                    <button type="button" id="btn-firmar-plan-evaluado" onclick="firmarPlanMejoramiento('evaluado')" class="bg-[#B5A160] text-white px-4 py-2 rounded-xl text-xs font-bold hover:brightness-110 transition hidden">Firmar plan de mejoramiento</button>
                                </div>
                            </div>
                        </div>

                        <!-- Tab: Competencias -->
                        <div id="tab-evaluado-competencias" class="evaluado-tab-panel hidden mt-6">
                            <div class="flex items-center justify-between gap-3">
                                <h4 class="text-sm font-bold text-slate-800 flex items-center gap-2">
                                    <span class="material-symbols-outlined text-base">psychology</span>
                                    Competencias evaluadas
                                </h4>
                            </div>
                            <p class="text-[10px] text-slate-400 font-semibold mt-1">Calificaciones de las competencias comunes y de nivel jerárquico registradas por tu evaluador.</p>
                            <div id="competencias-lista-evaluado" class="mt-4 space-y-4"></div>
                        </div>

                        <!-- Tab: Ejes misionales -->
                        <div id="tab-evaluado-ejes" class="evaluado-tab-panel hidden mt-6">
                            <div class="flex items-center justify-between gap-3">
                                <h4 class="text-sm font-bold text-slate-800 flex items-center gap-2">
                                    <span class="material-symbols-outlined text-base">donut_small</span>
                                    Calificaciones de ejes misionales
                                </h4>
                            </div>
                            <p class="text-[10px] text-slate-400 font-semibold mt-1">Docencia (eje base), investigación y proyección social registradas por la instancia externa.</p>
                            <div id="ejes-lista-evaluado" class="mt-4 space-y-3"></div>
                        </div>

                        <!-- Tab: Recursos -->
                        <div id="tab-evaluado-recursos" class="evaluado-tab-panel hidden mt-6">
                            <!-- S6: Recursos en línea (reposición / apelación) - evaluado -->
                            <div id="bloque-recursos-evaluado" class="space-y-3">
                                <div class="flex items-center justify-between gap-3">
                                    <h4 class="text-sm font-bold text-slate-800 flex items-center gap-2">
                                        <span class="material-symbols-outlined text-base">gavel</span>
                                        Recursos: reposición y apelación
                                    </h4>
                                    <span id="recursos-contador-evaluado" class="text-[10px] font-bold rounded-full px-2.5 py-1 bg-slate-100 text-slate-500">0</span>
                                </div>
                                <p class="text-[10px] text-slate-400 font-semibold">Reposición: revisa el mismo evaluador. Apelación: conoce el superior jerárquico del evaluador (o Talento Humano).</p>
                                <div id="aviso-recursos-no-calificada" class="hidden rounded-xl border border-amber-200 bg-amber-50 p-3 text-[11px] font-semibold text-amber-700 flex items-center gap-2">
                                    <span class="material-symbols-outlined text-base">info</span>
                                    <span>Podrás radicar recursos cuando la evaluación haya sido calificada.</span>
                                </div>
                                <form id="form-recurso-evaluado" onsubmit="radicarRecurso(event)" class="grid gap-3 rounded-2xl border border-slate-100 bg-slate-50/50 p-4">
                                    <div class="grid sm:grid-cols-2 gap-3">
                                        <div>
                                            <label class="block text-[10px] font-bold text-slate-600 uppercase mb-1">Tipo de recurso</label>
                                            <select name="tipo_recurso" id="recurso-tipo-evaluado" class="w-full text-xs rounded-xl border border-slate-200 p-2.5 bg-white outline-none focus:border-[#00594E]">
                                                <option value="REPOSICION">Reposición</option>
                                                <option value="APELACION">Apelación</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-bold text-slate-600 uppercase mb-1">Número de folios</label>
                                            <input type="number" name="numero_folios" id="recurso-folios-evaluado" min="1" max="200" class="w-full text-xs rounded-xl border border-slate-200 p-2.5 bg-white outline-none focus:border-[#00594E]" required />
                                        </div>
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-600 uppercase mb-1">Motivación del recurso</label>
                                        <textarea name="motivacion" id="recurso-motivacion-evaluado" rows="3" maxlength="3000" class="w-full text-xs rounded-xl border border-slate-200 p-2.5 bg-white outline-none focus:border-[#00594E]" placeholder="Argumentos y hechos que sustentan el recurso..." required></textarea>
                                    </div>
                                    <div class="flex items-center justify-between gap-3">
                                        <span id="recurso-mensaje-evaluado" class="hidden text-xs font-semibold"></span>
                                        <button type="submit" class="bg-[#00594E] text-white px-4 py-2 rounded-xl text-xs font-bold hover:brightness-110 transition ml-auto">Radicar recurso</button>
                                    </div>
                                </form>
                                <div id="recursos-lista-evaluado" class="space-y-2"></div>
                            </div>
                        </div>
                    </div>

                    <div id="panel-concertacion-evaluado-empty" class="panel-card rounded-3xl p-8 flex flex-col items-center justify-center text-center text-slate-400">
                        <span class="material-symbols-outlined text-5xl text-slate-300 mb-3">assignment</span>
                        <p class="text-sm">Selecciona una Evaluación de la lista de la izquierda para ver el estado de la concertaci�n.</p>
                    </div>
                </div>
            </section>
            @endif

            @if ($rolActivo === 'evaluado')
            <section id="section-reportes" class="section-content hidden space-y-6">
                <div class="panel-card rounded-3xl p-6">
                    <div class="mb-4">
                        <p class="text-xs font-bold uppercase tracking-[0.22em] text-[#00594E]">Exportar PDF</p>
                        <h2 class="text-xl font-black text-slate-900 mt-1">Informes institucionales de evaluación</h2>
                        <p class="text-xs text-slate-500 mt-1">Descarga los informes oficiales en PDF de tus evaluaciones ya calificadas. El informe anual promedia los semestres A y B del periodo.</p>
                    </div>

                    @php
                        $informesDisponibles = $evaluacionesEvaluado->filter(fn ($e) => $e->estado === 'CALIFICADA');
                    @endphp

                    @if($informesDisponibles->isEmpty())
                        <div class="py-10 text-center text-slate-500 text-sm">
                            <span class="material-symbols-outlined text-4xl text-slate-300 mb-2 block">picture_as_pdf</span>
                            Aún no tienes informes disponibles. Cuando una de tus evaluaciones sea calificada, aparecerá aquí para descargarla.
                        </div>
                    @else
                        <div class="overflow-x-auto rounded-2xl border border-slate-100">
                            <table class="w-full text-left text-xs">
                                <thead>
                                    <tr class="bg-[#EAF2EF] text-[#00594E] uppercase tracking-wide">
                                        <th class="px-4 py-3 font-black">Sistema</th>
                                        <th class="px-4 py-3 font-black">Evaluación</th>
                                        <th class="px-4 py-3 font-black">Periodo</th>
                                        <th class="px-4 py-3 font-black">Evaluador</th>
                                        <th class="px-4 py-3 font-black text-right">Descargar</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($informesDisponibles as $ev)
                                        <tr class="border-t border-slate-100 odd:bg-white even:bg-slate-50/50">
                                            <td class="px-4 py-3">
                                                <span class="font-bold px-2 py-0.5 rounded-full bg-[#EAF2EF] text-[#00594E]">
                                                    {{ $ev->sistema === 'RENDIMIENTO_LABORAL' ? 'RL' : 'AG' }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 font-bold text-slate-800">{{ $ev->tipo_nombre }}</td>
                                            <td class="px-4 py-3 text-slate-600">
                                                {{ \Carbon\Carbon::parse($ev->fecha_inicio)->format('d/m/Y') }} al {{ \Carbon\Carbon::parse($ev->fecha_fin)->format('d/m/Y') }}
                                            </td>
                                            <td class="px-4 py-3 text-slate-600">{{ $ev->evaluador_nombres }} {{ $ev->evaluador_apellidos }}</td>
                                            <td class="px-4 py-3">
                                                <div class="flex justify-end gap-2">
                                                    <a href="/evaluaciones/{{ $ev->id_evaluacion }}/informe" class="inline-flex items-center gap-1.5 rounded-lg bg-[#00594E] text-white px-3 py-1.5 text-[11px] font-bold hover:brightness-110 transition">
                                                        <span class="material-symbols-outlined text-sm">picture_as_pdf</span> PDF semestral
                                                    </a>
                                                    <a href="/evaluaciones/{{ $ev->id_evaluacion }}/informe-anual" class="inline-flex items-center gap-1.5 rounded-lg bg-[#B5A160] text-white px-3 py-1.5 text-[11px] font-bold hover:brightness-110 transition">
                                                        <span class="material-symbols-outlined text-sm">picture_as_pdf</span> PDF anual
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
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
    let selectedEstadoEvaluacion = null;
    let selectedEvaluacionData = null;
    let selectedEvaluacionEjes = {};
    let selectedPlanData = null;
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

    function contarAsignados() {
        const contador = document.getElementById('contador-asignacion');
        if (!contador) return;
        const total = document.querySelectorAll('#lista-evaluados-asignacion input[name="id_vinc_evaluado[]"]:checked').length;
        contador.innerText = total + (total === 1 ? ' seleccionada' : ' seleccionadas');
    }

    function filtrarCheckboxAsignacion() {
        const input = document.getElementById('buscar-evaluado-asignacion');
        const termino = (input?.value || '').trim().toLowerCase();
        document.querySelectorAll('#lista-evaluados-asignacion .checkbox-evaluado').forEach(item => {
            item.hidden = !termino || !item.dataset.buscar.includes(termino);
        });
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

    function renderEvidenciasEvaluadorAccion(evidencias = [], bloqueada = false) {
        if (!evidencias.length) {
            return '<div class="text-[11px] text-slate-400">Sin evidencias registradas para este compromiso.</div>';
        }

        return evidencias.map(evidencia => {
            const estado = evidencia.estado_aprobacion || 'PENDIENTE';
            const acciones = (estado === 'PENDIENTE' && !bloqueada) ? `
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

        let observacion = '';
        if (decision === 'RECHAZADA') {
            const motivo = prompt('Motivo del rechazo:');
            if (motivo === null) return; // el evaluador canceló el cuadro: no se rechaza nada
            if (!motivo.trim()) { alert('Debes indicar un motivo para rechazar la evidencia.'); return; }
            observacion = motivo.trim();
            if (!confirm('¿Confirmas rechazar esta evidencia? Esta decisión quedará registrada y no podrás deshacerla desde aquí.')) return;
        } else {
            if (!confirm('¿Confirmas aprobar esta evidencia? Esta decisión quedará registrada y no podrás deshacerla desde aquí.')) return;
        }

        fetch(`/evaluaciones/${selectedEvaluacionId}/evidencias/${idEvidencia}/aprobar`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            body: JSON.stringify({ decision, observacion }),
        })
            .then(async res => {
                const payload = await res.json().catch(() => ({}));
                if (!res.ok) { alert(payload.message || 'No se pudo registrar la decisión.'); return; }
                if (selectedEvaluacionData) cargarCompromisosEvaluador(selectedEvaluacionData, selectedEvaluacionEjes);
            })
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
        const tabBtnEjes = document.getElementById('tabbtn-evaluado-ejes');
        if (tabBtnEjes) tabBtnEjes.classList.add('hidden');
        const tabBtnCompromisos = document.getElementById('tabbtn-evaluado-compromisos');
        if (tabBtnCompromisos) tabBtnCompromisos.classList.add('active');
        cambiarTabEvaluado('compromisos');
        fetch(`/evaluaciones/${ev.id_evaluacion}/ejes`)
            .then(res => res.json())
            .then(ejes => {
                const aplica = ev.sistema === 'ACUERDO_GESTION' && !!ev.aplica_eje_misional;
                if (aplica && axesView) {
                    axesView.classList.remove('hidden');
                    if (chkInv) chkInv.checked = !!ejes.investigacion;
                    if (chkProj) chkProj.checked = !!ejes.proyeccion_social;
                }
                if (aplica && tabBtnEjes) tabBtnEjes.classList.remove('hidden');
                cargarCompromisosEvaluado(ev);
            })
            .catch(() => cargarCompromisosEvaluado(ev));
        cargarCompetenciasEvaluado();
        cargarEjesEvaluado(ev);
        cargarRecursosEvaluado(ev);
        cargarPlanMejoramientoEvaluado(ev);
        document.querySelectorAll('.evaluacion-card').forEach(el => el.classList.remove('ring-2', 'ring-[#00594E]'));
        if (card) card.classList.add('ring-2', 'ring-[#00594E]');
    }

    function cambiarTabEvaluado(tab) {
        const tabs = ['compromisos', 'competencias', 'ejes', 'recursos'];
        tabs.forEach(t => {
            const panel = document.getElementById(`tab-evaluado-${t}`);
            if (panel) panel.classList.toggle('hidden', t !== tab);
            const btn = document.getElementById(`tabbtn-evaluado-${t}`);
            if (btn) btn.classList.toggle('active', t === tab);
        });
    }

    function cambiarTabEvaluador(tab) {
        const tabs = ['compromisos', 'competencias', 'ejes', 'recursos'];
        tabs.forEach(t => {
            const panel = document.getElementById(`tab-evaluador-${t}`);
            if (panel) panel.classList.toggle('hidden', t !== tab);
            const btn = document.getElementById(`tabbtn-evaluador-${t}`);
            if (btn) btn.classList.toggle('active', t === tab);
        });
    }

    function cargarCompetenciasEvaluado() {
        if (!selectedEvaluacionId) return;
        const contenedor = document.getElementById('competencias-lista-evaluado');
        if (!contenedor) return;
        contenedor.innerHTML = '<div class="text-xs text-slate-400">Cargando competencias...</div>';
        fetch(`/evaluaciones/${selectedEvaluacionId}/competencias`)
            .then(res => res.json())
            .then(payload => {
                const competencias = payload.competencias || [];
                if (!competencias.length) {
                    contenedor.innerHTML = '<div class="rounded-xl border border-dashed border-slate-200 bg-white p-4 text-xs text-slate-500 text-center">El evaluador aún no ha calificado competencias.</div>';
                    return;
                }
                const comun = competencias.filter(c => c.tipo === 'COMUN');
                const nivel = competencias.filter(c => c.tipo === 'NIVEL_JERARQUICO');
                const renderGrupo = (titulo, items, color) => {
                    if (!items.length) return '';
                    return `
                        <div class="rounded-2xl border border-slate-100 bg-slate-50/60 p-4 space-y-2">
                            <p class="text-[10px] font-black uppercase tracking-wide ${color}">${titulo}</p>
                            ${items.map(c => `
                                <div class="flex items-center justify-between gap-3 rounded-xl border border-slate-100 bg-white p-3">
                                    <div class="min-w-0">
                                        <p class="text-xs font-bold text-slate-800">${escapeHtml(c.nombre_competencia)}</p>
                                    </div>
                                    <span class="text-sm font-black text-[#00594E] shrink-0">${c.calificacion_definitiva ?? '-'}</span>
                                </div>
                            `).join('')}
                        </div>`;
                };
                contenedor.innerHTML = renderGrupo('Competencias comunes', comun, 'text-[#00594E]')
                    + renderGrupo('Competencias de nivel jerárquico', nivel, 'text-[#B5A160]');
            })
            .catch(() => {
                contenedor.innerHTML = '<div class="text-xs text-slate-400">No se pudieron cargar las competencias.</div>';
            });
    }

    function cargarEjesEvaluado(ev) {
        if (!selectedEvaluacionId) return;
        const contenedor = document.getElementById('ejes-lista-evaluado');
        if (!contenedor) return;
        const etiquetas = {
            DOCENCIA: 'Docencia',
            INVESTIGACION: 'Horas de investigación',
            PROYECCION_SOCIAL: 'Proyección social',
        };
        fetch(`/evaluaciones/${selectedEvaluacionId}/calculo`)
            .then(res => res.json())
            .then(calculo => {
                const ejesActivos = calculo.ejes_activos || [];
                const notas = calculo.notas_ejes_raw || {};
                const pesos = calculo.pesos?.ejes || {};
                if (!ejesActivos.length) {
                    contenedor.innerHTML = '<div class="rounded-xl border border-dashed border-slate-200 bg-white p-4 text-xs text-slate-500 text-center">Esta evaluación no tiene ejes misionales activos.</div>';
                    return;
                }
                contenedor.innerHTML = ejesActivos.map(eje => {
                    const nota = notas[eje];
                    const peso = pesos[eje];
                    return `
                        <div class="flex items-center justify-between gap-3 rounded-2xl border border-slate-100 bg-slate-50/60 p-4">
                            <div class="flex items-center gap-2.5 min-w-0">
                                <span class="material-symbols-outlined text-base text-[#00594E]">donut_small</span>
                                <div class="min-w-0">
                                    <p class="text-xs font-bold text-slate-800">${etiquetas[eje] || eje}</p>
                                    <p class="text-[10px] text-slate-400">Peso ${peso ?? '-'}%</p>
                                </div>
                            </div>
                            <span class="text-sm font-black text-[#00594E] shrink-0">${nota !== undefined ? nota : '-'}</span>
                        </div>`;
                }).join('');
            })
            .catch(() => {
                contenedor.innerHTML = '<div class="text-xs text-slate-400">No se pudieron cargar los ejes misionales.</div>';
            });
    }

    function abrirConcertacionEvaluador(card, ev) {
        selectedEvaluacionId = ev.id_evaluacion;
        selectedEvaluacionData = ev;
        selectedEvaluacionEjes = {};

        const panel = document.getElementById('panel-concertacion-evaluador');
        const empty = document.getElementById('panel-concertacion-evaluador-empty');
        if (empty) empty.classList.add('hidden');
        if (panel) panel.classList.remove('hidden');

        const tabBtnEjes = document.getElementById('tabbtn-evaluador-ejes');
        if (tabBtnEjes) tabBtnEjes.classList.add('hidden');
        cambiarTabEvaluador('compromisos');

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
                if (ev.sistema === 'ACUERDO_GESTION' && ev.aplica_eje_misional && tabBtnEjes) {
                    tabBtnEjes.classList.remove('hidden');
                }
                cargarCompromisosEvaluador(ev, ejes);
            })
            .catch(() => {
                selectedEvaluacionEjes = {};
                cargarCompromisosEvaluador(ev, {});
            });

        cargarRecursosEvaluador(ev);
        cargarPlanMejoramientoEvaluador(ev);
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
	                selectedEstadoEvaluacion = estado;
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
                    const evidenciasHtml = renderEvidenciasEvaluadorAccion(evidenciasPorCompromiso[String(c.id_compromiso)] || [], estado.calificada);
                    const observacionHtml = renderObservacionEvaluador(c, observacionesPorCompromiso[String(c.id_compromiso)], !estado.congelada);
                    const calificacionHtml = estado.congelada ? `
                        <div class="mt-4 pt-3 border-t border-slate-100 flex items-center gap-2">
                            <label class="text-[10px] font-bold text-slate-500 uppercase">Calificación (0-100)</label>
                            <input type="number" min="0" max="100" step="0.01" class="calificacion-compromiso-input w-24 text-xs rounded-lg border border-slate-200 p-1.5 disabled:bg-slate-100 disabled:text-slate-500" data-id-compromiso="${c.id_compromiso}" value="${c.calificacion_definitiva ?? ''}" onblur="clampCalificacion(this)" ${estado.calificada ? 'disabled' : ''} />
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
                const avisoCompetencias = document.getElementById('aviso-competencias-evaluador');
                if (avisoCompetencias) avisoCompetencias.classList.toggle('hidden', !!estado.congelada);
                document.querySelectorAll('#calificacion-bloque-evaluador button, #competencias-bloque-evaluador button, #ejes-misionales-bloque-evaluador button').forEach(btn => {
                    btn.disabled = !!estado.calificada;
                    btn.classList.toggle('opacity-50', !!estado.calificada);
                    btn.classList.toggle('cursor-not-allowed', !!estado.calificada);
                });
                const btnCalcularFinal = document.getElementById('btn-calcular-nota-final');
                if (btnCalcularFinal) btnCalcularFinal.disabled = !!estado.calificada;
                if (estado.congelada) {
                    cargarCalificacionYResultado(ev, !!estado.calificada);
                }

                const seccionFirmarEvaluador = document.getElementById('seccion-firmar-evaluador');
                if (seccionFirmarEvaluador) {
                    seccionFirmarEvaluador.classList.toggle('hidden', yaFirmado);
                }

                const btnFirmar = document.getElementById('btn-firmar-evaluador');
                const okToSign = contador >= 7 && contador <= 10 && Math.abs(sumaPesos - targetWeight) < 0.01 && !yaFirmado;
                if (btnFirmar) {
                    btnFirmar.disabled = !okToSign;
                    btnFirmar.innerText = yaFirmado ? 'Firmado' : 'Firmar concertación';
                }

                const form = document.getElementById('form-firmar-evaluacion');
                if (form) form.action = `/evaluaciones/${ev.id_evaluacion}/firmar`;
                renderFirmasConcertacion(estado, 'firmas-concertacion-evaluador');
            })
            .catch(() => {});
    }

    // --- S5: Calificación de compromisos/competencias y resultado consolidado ---

    function cargarCalificacionYResultado(ev, bloqueada = false) {
        if (!selectedEvaluacionId) return;
        const sistema = String(ev.sistema || '').trim().toUpperCase();
        const nivel = String(ev.evaluado_nivel_jerarquico || '').trim().toUpperCase();

        fetch(`/catalogo/competencias?sistema=${encodeURIComponent(sistema)}&nivel=${encodeURIComponent(nivel)}`)
            .then(res => res.json())
            .then(catalogo => {
                fetch(`/evaluaciones/${selectedEvaluacionId}/competencias`)
                    .then(res => res.json())
                    .then(payload => renderCompetenciasEvaluador(catalogo, payload.competencias || [], bloqueada))
                    .catch(() => renderCompetenciasEvaluador(catalogo, [], bloqueada));
            })
            .catch(() => {});

        previsualizarCalculoEvaluador();
    }

    function renderCompetenciasEvaluador(catalogo, existentes = [], bloqueada = false) {
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
                        <input type="number" min="0" max="100" step="0.01" class="competencia-input w-24 text-xs rounded-lg border border-slate-200 p-1.5 disabled:bg-slate-100 disabled:text-slate-500" data-tipo="${tipo}" data-nombre="${escapeHtml(item.nombre)}" value="${valor}" onblur="clampCalificacion(this)" ${bloqueada ? 'disabled' : ''} />
                    </div>
                </div>`;
        }).join('');

        const comunesNode = document.getElementById('competencias-comunes-evaluador');
        const nivelNode = document.getElementById('competencias-nivel-evaluador');
        if (comunesNode) comunesNode.innerHTML = '<p class="text-[10px] font-bold text-slate-400 uppercase mb-1">Comunes</p>' + (renderLista(comunes, 'COMUN') || '<p class="text-[11px] text-slate-400">Sin catálogo disponible.</p>');
        if (nivelNode) nivelNode.innerHTML = '<p class="text-[10px] font-bold text-slate-400 uppercase mb-1">Nivel jerárquico</p>' + (renderLista(nivel, 'NIVEL_JERARQUICO') || '<p class="text-[11px] text-slate-400">Sin catálogo disponible.</p>');
    }

    function clampCalificacion(input) {
        if (input.value === '') return;
        let val = parseFloat(input.value);
        if (isNaN(val)) { input.value = ''; return; }
        if (val > 100) val = 100;
        if (val < 0) val = 0;
        input.value = val;
    }

    function parseErrorMessage(payload, fallback) {
        if (payload && payload.errors) {
            const primero = Object.values(payload.errors)[0];
            if (Array.isArray(primero) && primero[0]) return primero[0];
        }
        return (payload && payload.message) || fallback;
    }

    function fetchJson(url, options = {}) {
        options.headers = Object.assign({}, options.headers, {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        });
        return fetch(url, options);
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
            .then(async res => {
                const payload = await res.json().catch(() => ({}));
                if (!res.ok) throw new Error(parseErrorMessage(payload, 'No se pudo guardar. Revisa que las calificaciones estén entre 0 y 100.'));
                if (msg) { msg.classList.remove('hidden'); msg.className = 'text-xs font-semibold text-[#00594E]'; msg.innerText = payload.message || 'Calificaciones guardadas.'; }
                previsualizarCalculoEvaluador();
            })
            .catch(error => {
                if (msg) { msg.classList.remove('hidden'); msg.className = 'text-xs font-semibold text-red-600'; msg.innerText = error.message; }
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
            .then(async res => {
                const payload = await res.json().catch(() => ({}));
                if (!res.ok) throw new Error(parseErrorMessage(payload, 'No se pudo guardar. Revisa que las calificaciones estén entre 0 y 100.'));
                if (msg) { msg.classList.remove('hidden'); msg.className = 'text-xs font-semibold text-[#00594E]'; msg.innerText = payload.message || 'Competencias guardadas.'; }
                previsualizarCalculoEvaluador();
            })
            .catch(error => {
                if (msg) { msg.classList.remove('hidden'); msg.className = 'text-xs font-semibold text-red-600'; msg.innerText = error.message; }
            });
    }

    function formatPendientes(pendientes = {}) {
        const items = [];
        if (pendientes.compromisos_sin_calificar) {
            items.push(`${pendientes.compromisos_sin_calificar} compromiso(s) sin calificación`);
        }
        (pendientes.competencias_comunes_faltantes || []).forEach(n => items.push(`Competencia común: ${n}`));
        (pendientes.competencias_nivel_faltantes || []).forEach(n => items.push(`Competencia de nivel: ${n}`));
        (pendientes.ejes_faltantes || []).forEach(e => items.push(`Eje misional: ${EJE_LABELS[e] || e}`));
        return items;
    }

    function renderResultado(calculo, containerId) {
        const cont = document.getElementById(containerId);
        if (!cont) return;
        if (!calculo || calculo.error) {
            cont.innerHTML = '<div class="text-xs text-slate-400">Aún no hay datos suficientes para calcular la nota.</div>';
            return;
        }

        const pendientesItems = formatPendientes(calculo.pendientes || {});
        const bloqueadaHtml = calculo.estado === 'CALIFICADA' ? `
            <div class="rounded-xl border border-[#00594E]/20 bg-[#EAF2EF] p-3 text-[11px] font-semibold text-[#00594E] flex items-center gap-2">
                <span class="material-symbols-outlined text-base">lock</span>
                Esta evaluación ya fue calificada y calculada. Las notas y evidencias quedaron congeladas.
            </div>
        ` : (pendientesItems.length ? `
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-[11px] font-semibold text-amber-700">
                <p class="flex items-center gap-2 mb-1"><span class="material-symbols-outlined text-base">warning</span>Falta calificar antes de poder cerrar la evaluación:</p>
                <ul class="list-disc list-inside space-y-0.5 font-normal">${pendientesItems.map(i => `<li>${escapeHtml(i)}</li>`).join('')}</ul>
            </div>
        ` : '');

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

        const pdfHtml = (calculo.estado === 'CALIFICADA' && containerId === 'resultado-contenido-evaluado') ? `
            <div class="mt-3 flex flex-wrap gap-2">
                <a href="/evaluaciones/${selectedEvaluacionId}/informe" class="inline-flex items-center gap-2 rounded-xl bg-[#00594E] text-white px-4 py-2 text-xs font-bold hover:brightness-110 transition">
                    <span class="material-symbols-outlined text-sm">picture_as_pdf</span> Descargar PDF semestral
                </a>
                <a href="/evaluaciones/${selectedEvaluacionId}/informe-anual" class="inline-flex items-center gap-2 rounded-xl bg-[#B5A160] text-white px-4 py-2 text-xs font-bold hover:brightness-110 transition">
                    <span class="material-symbols-outlined text-sm">picture_as_pdf</span> Descargar PDF anual
                </a>
            </div>
        ` : '';

        let notificacionHtml = '';
        if (calculo.estado === 'CALIFICADA') {
            const estadoNotif = selectedEstadoEvaluacion || {};
            if (estadoNotif.notificacion_firmada) {
                if (estadoNotif.renuencia_notificacion) {
                    const tList = (estadoNotif.testigos_notificacion || []).map(t => `<p class="text-[11px] text-amber-800 font-medium">• <b>${escapeHtml(t.nombre_testigo || t.nombre)}</b> (${escapeHtml(t.cargo_testigo || t.cargo)})</p>`).join('');
                    notificacionHtml = `
                        <div class="mt-4 p-4 rounded-2xl border border-amber-200 bg-amber-50/70 space-y-2">
                            <div class="flex items-center gap-2">
                                <span class="material-symbols-outlined text-amber-600">verified_user</span>
                                <p class="text-xs font-bold text-amber-900">Notificación registrada con renuencia del evaluado</p>
                            </div>
                            <p class="text-[11px] text-amber-700">El evaluado se rehusó a firmar la notificación de su calificación. Se registraron los siguientes testigos:</p>
                            <div class="pt-1 space-y-1">
                                ${tList}
                            </div>
                        </div>
                    `;
                } else {
                    notificacionHtml = `
                        <div class="mt-4 p-4 rounded-2xl border border-emerald-200 bg-emerald-50/70 flex items-center justify-between gap-3">
                            <div class="flex items-center gap-2">
                                <span class="material-symbols-outlined text-emerald-600">check_circle</span>
                                <div>
                                    <p class="text-xs font-bold text-emerald-900">Notificación de la calificación firmada por el evaluado</p>
                                    <p class="text-[10px] text-emerald-700">Fecha: ${escapeHtml(estadoNotif.fecha_notificacion || 'Registrada')}</p>
                                </div>
                            </div>
                            <span class="text-[10px] font-bold uppercase rounded-full px-2.5 py-1 bg-emerald-100 text-emerald-800">Firmada</span>
                        </div>
                    `;
                }
            } else {
                notificacionHtml = `
                    <div class="mt-4 p-4 rounded-2xl border border-slate-200 bg-white space-y-3 shadow-sm">
                        <div class="flex items-center justify-between">
                            <h5 class="text-xs font-bold text-slate-800 uppercase tracking-wide flex items-center gap-1.5">
                                <span class="material-symbols-outlined text-base text-[#00594E]">draw</span>
                                Notificación de la Calificación (Nota)
                            </h5>
                            <span class="text-[10px] font-bold uppercase rounded-full px-2.5 py-1 bg-amber-50 text-amber-700">Pendiente de firma</span>
                        </div>
                        <p class="text-xs text-slate-600 leading-snug">El evaluado debe firmar la notificación de su calificación.</p>

                        <div class="flex items-center justify-between gap-3 pt-1">
                            <span id="msg-notif-calificacion" class="hidden text-xs font-semibold"></span>
                            <button type="button" id="btn-notif-calificacion" onclick="firmarNotificacionCalificacion()" class="ml-auto bg-[#00594E] text-white px-5 py-2 rounded-xl text-xs font-bold hover:brightness-110 transition">
                                Firmar notificación
                            </button>
                        </div>
                    </div>
                `;
            }
        }

        cont.innerHTML = `
            ${bloqueadaHtml}
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
            ${pdfHtml}
            ${notificacionHtml}
        `;
    }

    function previsualizarCalculoEvaluador() {
        if (!selectedEvaluacionId) return;
        fetch(`/evaluaciones/${selectedEvaluacionId}/calculo`)
            .then(res => res.json())
            .then(calculo => {
                renderResultado(calculo, 'resultado-contenido-evaluador');
                renderEjesMisionalesEvaluador(calculo);
            })
            .catch(() => {});
    }

    function renderEjesMisionalesEvaluador(calculo) {
        const bloque = document.getElementById('ejes-misionales-bloque-evaluador');
        const contenedor = document.getElementById('ejes-misionales-inputs-evaluador');
        const btn = document.getElementById('btn-guardar-ejes-evaluador');
        if (!bloque || !contenedor) return;

        const ejesActivos = calculo?.ejes_activos || [];
        if (!ejesActivos.length) {
            bloque.classList.add('hidden');
            const avisoEjes = document.getElementById('aviso-ejes-evaluador');
            if (avisoEjes) avisoEjes.classList.remove('hidden');
            return;
        }
        bloque.classList.remove('hidden');
        const avisoEjes = document.getElementById('aviso-ejes-evaluador');
        if (avisoEjes) avisoEjes.classList.add('hidden');

        const bloqueada = calculo.estado === 'CALIFICADA';
        const notas = calculo.notas_ejes_raw || {};

        contenedor.innerHTML = ejesActivos.map(eje => `
            <div class="p-3 rounded-xl border border-slate-100 bg-slate-50/50">
                <p class="text-xs font-bold text-slate-800">${EJE_LABELS[eje] || eje}</p>
                <div class="mt-2 flex items-center gap-2">
                    <label class="text-[10px] font-bold text-slate-500 uppercase">Calificación (0-100)</label>
                    <input type="number" min="0" max="100" step="0.01" class="eje-misional-evaluador-input w-24 text-xs rounded-lg border border-slate-200 p-1.5 disabled:bg-slate-100 disabled:text-slate-500" data-eje="${eje}" value="${notas[eje] ?? ''}" onblur="clampCalificacion(this)" ${bloqueada ? 'disabled' : ''} />
                </div>
            </div>
        `).join('');

        if (btn) btn.disabled = bloqueada;
    }

    function guardarEjesMisionalesEvaluador() {
        if (!selectedEvaluacionId) return;
        const ejes = Array.from(document.querySelectorAll('.eje-misional-evaluador-input'))
            .filter(input => input.value !== '')
            .map(input => ({ tipo_eje: input.dataset.eje, calificacion: parseFloat(input.value) }));

        const msg = document.getElementById('ejes-misionales-mensaje-evaluador');
        if (!ejes.length) {
            if (msg) { msg.classList.remove('hidden'); msg.className = 'text-xs font-semibold text-red-600'; msg.innerText = 'Ingresa al menos una calificación.'; }
            return;
        }

        fetch(`/evaluaciones/${selectedEvaluacionId}/calificar-ejes`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            body: JSON.stringify({ ejes }),
        })
            .then(async res => {
                const payload = await res.json().catch(() => ({}));
                if (!res.ok) throw new Error(parseErrorMessage(payload, 'No se pudo guardar. Revisa que las calificaciones estén entre 0 y 100.'));
                if (msg) { msg.classList.remove('hidden'); msg.className = 'text-xs font-semibold text-[#00594E]'; msg.innerText = payload.message || 'Ejes misionales guardados.'; }
                previsualizarCalculoEvaluador();
            })
            .catch(error => {
                if (msg) { msg.classList.remove('hidden'); msg.className = 'text-xs font-semibold text-red-600'; msg.innerText = error.message; }
            });
    }

    function calcularNotaFinalEvaluador() {
        if (!selectedEvaluacionId) return;
        if (!confirm('¿Confirmas calcular y guardar la nota final? Una vez calculada, la evaluación queda cerrada: no podrás modificar calificaciones ni evidencias después.')) return;

        fetch(`/evaluaciones/${selectedEvaluacionId}/calcular-final`, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        })
            .then(async res => {
                const payload = await res.json().catch(() => ({}));
                if (payload.calculo) {
                    renderResultado(payload.calculo, 'resultado-contenido-evaluador');
                    if (payload.success && selectedEvaluacionData) cargarCompromisosEvaluador(selectedEvaluacionData, selectedEvaluacionEjes);
                } else if (payload.pendientes) {
                    alert('Faltan calificaciones por registrar antes de calcular la nota final:\n\n' + formatPendientes(payload.pendientes).map(i => '• ' + i).join('\n'));
                } else if (payload.error) {
                    alert(payload.error);
                }
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

                const seccionFirmarEvaluado = document.getElementById('seccion-firmar-evaluado');
                if (seccionFirmarEvaluado) {
                    seccionFirmarEvaluado.classList.toggle('hidden', locked);
                }


                renderFirmasConcertacion(estado, 'firmas-concertacion-evaluado');

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
                    let badgeTexto, badgeClass;
                    if (!ev.id_evaluacion) {
                        badgeTexto = 'Sin evaluación abierta';
                        badgeClass = 'bg-slate-100 text-slate-500';
                    } else if (!ev.concertacion_firmada) {
                        badgeTexto = 'Concertación pendiente';
                        badgeClass = 'bg-amber-50 text-amber-700';
                    } else if (cargados) {
                        badgeTexto = cargados + ' nota(s)';
                        badgeClass = 'bg-[#EAF2EF] text-[#00594E]';
                    } else {
                        badgeTexto = 'Sin notas';
                        badgeClass = 'bg-amber-50 text-amber-700';
                    }
                    btn.innerHTML = `
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h4 class="font-bold text-slate-900 text-sm leading-snug">${escapeHtml(ev.evaluado_nombres || '')} ${escapeHtml(ev.evaluado_apellidos || '')}</h4>
                                <p class="text-xs text-slate-500 mt-0.5">${escapeHtml(ev.evaluado_cargo || '')} - ${escapeHtml(ev.evaluado_area || '')}</p>
                            </div>
                            <span class="text-[10px] font-bold uppercase px-2 py-1 rounded-full ${badgeClass}">${badgeTexto}</span>
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

        let avisoTexto = null;
        if (!ev.id_evaluacion) {
            avisoTexto = 'Aún no se ha creado la evaluación de este periodo para esta persona. El evaluador debe abrirla primero (asignar el periodo) antes de poder cargar notas.';
        } else if (!ev.concertacion_firmada) {
            avisoTexto = 'La evaluación ya existe, pero la concertación de compromisos todavía no ha sido firmada por evaluador y evaluado. Podrás cargar las notas una vez se firme.';
        } else if (ev.estado === 'CALIFICADA') {
            avisoTexto = 'Esta evaluación ya fue calificada y calculada; las notas quedaron congeladas.';
        }
        const bloqueada = !!avisoTexto;
        const contenedor = document.getElementById('instancia-externa-ejes-contenedor');
        if (contenedor) {
            const avisoBloqueada = avisoTexto ? `
                <div class="rounded-xl border border-slate-200 bg-slate-100 p-3 text-[11px] font-semibold text-slate-600 flex items-center gap-2">
                    <span class="material-symbols-outlined text-base">${ev.estado === 'CALIFICADA' ? 'lock' : 'info'}</span>
                    ${escapeHtml(avisoTexto)}
                </div>` : '';
            contenedor.innerHTML = avisoBloqueada + ((ev.ejes_activos || []).map(eje => {
                const existente = notasExistentes[eje];
                return `
                    <div class="p-3 rounded-xl border border-slate-100 bg-slate-50/50">
                        <p class="text-xs font-bold text-slate-800">${EJE_LABELS[eje] || eje}</p>
                        ${existente ? `<p class="text-[10px] text-slate-400 mt-0.5">Última carga: ${escapeHtml(existente.fecha_ingreso || '')} (${escapeHtml(existente.origen || '-')})</p>` : ''}
                        <div class="mt-2 flex items-center gap-2">
                            <label class="text-[10px] font-bold text-slate-500 uppercase">Calificación (0-100)</label>
                            <input type="number" min="0" max="100" step="0.01" class="eje-externa-input w-24 text-xs rounded-lg border border-slate-200 p-1.5 disabled:bg-slate-100 disabled:text-slate-500" data-eje="${eje}" value="${existente?.calificacion ?? ''}" onblur="clampCalificacion(this)" ${bloqueada ? 'disabled' : ''} />
                        </div>
                        <textarea class="eje-externa-observacion mt-2 w-full text-xs rounded-lg border border-slate-200 p-2 disabled:bg-slate-100 disabled:text-slate-500" rows="2" data-eje="${eje}" placeholder="Observaciones (opcional)" ${bloqueada ? 'disabled' : ''}>${escapeHtml(existente?.observaciones || '')}</textarea>
                    </div>`;
            }).join('') || '<p class="text-xs text-slate-400">Este evaluado no tiene ejes misionales activos.</p>');
        }

        const btnGuardarExterna = document.querySelector('#form-instancia-externa button[type="submit"]');
        if (btnGuardarExterna) {
            btnGuardarExterna.disabled = bloqueada;
            btnGuardarExterna.classList.toggle('opacity-50', bloqueada);
            btnGuardarExterna.classList.toggle('cursor-not-allowed', bloqueada);
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
                if (!res.ok) throw new Error(parseErrorMessage(payload, 'No se pudo guardar. Revisa que las calificaciones estén entre 0 y 100.'));
                if (msg) { msg.classList.remove('hidden'); msg.className = 'text-xs font-semibold text-[#00594E]'; msg.innerText = payload.message || 'Notas guardadas.'; }
                cargarListaInstanciaExterna();
            })
            .catch(error => {
                if (msg) { msg.classList.remove('hidden'); msg.className = 'text-xs font-semibold text-red-600'; msg.innerText = error.message; }
            });
    }

    // --- S6: Renuencia, recursos (reposición/apelación) y plan de mejoramiento ---

    function toggleRenuenciaNotificacion() {}

    function firmarNotificacionCalificacion() {
        if (!selectedEvaluacionId) return;
        const msg = document.getElementById('msg-notif-calificacion');
        const btn = document.getElementById('btn-notif-calificacion');

        if (!confirm('¿Confirmas firmar la notificación de la calificación recibida?')) {
            return;
        }

        if (btn) btn.disabled = true;

        fetchJson(`/evaluaciones/${selectedEvaluacionId}/firmar-notificacion`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
            },
            body: JSON.stringify({ renuencia, testigos })
        })
        .then(payload => {
            mostrarMensaje(msg, payload.message || 'Procesado con éxito.', true);
            if (selectedEvaluacionData) {
                if (typeof cargarCompromisosEvaluado === 'function') cargarCompromisosEvaluado(selectedEvaluacionData);
                if (typeof cargarCompromisosEvaluador === 'function') cargarCompromisosEvaluador(selectedEvaluacionData);
            }
        })
        .catch(err => {
            mostrarMensaje(msg, err.message || 'Ocurrió un error al registrar.', false);
            if (btn) btn.disabled = false;
        });
    }

    function firmarConcertacion(e, rol) {
        if (!confirm('¿Confirmas firmar la concertación? Una vez que ambas partes firmen, los compromisos y sus porcentajes quedarán bloqueados y no se podrán editar.')) {
            e.preventDefault();
            return false;
        }
        return true;
    }

    function mostrarMensaje(el, texto, ok) {
        if (!el) return;
        el.classList.remove('hidden');
        el.className = 'text-xs font-semibold ' + (ok ? 'text-[#00594E]' : 'text-red-600');
        el.innerText = texto;
    }

    function renderFirmasConcertacion(estado, contenedorId) {
        const contenedor = document.getElementById(contenedorId);
        if (!contenedor) return;
        const tarjetaFirma = (rol, label, firmado) => {
            const badge = firmado
                ? `<span class="text-[10px] font-bold uppercase rounded-full px-2 py-0.5 bg-emerald-50 text-emerald-700">Firmado</span>`
                : `<span class="text-[10px] font-bold uppercase rounded-full px-2 py-0.5 bg-slate-100 text-slate-500">Sin firmar</span>`;
            return `<div class="rounded-xl border border-slate-100 bg-white p-3">
                <div class="flex items-center justify-between gap-2">
                    <p class="text-[10px] font-bold text-slate-500 uppercase">${label}</p>
                    ${badge}
                </div>
            </div>`;
        };
        contenedor.innerHTML = `
            <div class="rounded-2xl border border-slate-100 bg-slate-50/50 p-3 grid sm:grid-cols-2 gap-2">
                <p class="text-[10px] font-bold uppercase tracking-wide text-slate-500 col-span-full">Estado de la concertación</p>
                ${tarjetaFirma('evaluador', 'Evaluador', !!estado.evaluador_firmado)}
                ${tarjetaFirma('evaluado', 'Evaluado', !!estado.evaluado_firmado)}
            </div>`;
        contenedor.classList.remove('hidden');
    }

    function badgeDecisionRecurso(decision) {
        if (decision === 'PENDIENTE') return '<span class="text-[10px] font-bold uppercase rounded-full px-2.5 py-1 bg-amber-50 text-amber-700">Pendiente</span>';
        if (decision === 'APROBADO') return '<span class="text-[10px] font-bold uppercase rounded-full px-2.5 py-1 bg-emerald-50 text-emerald-700">Aprobado</span>';
        return '<span class="text-[10px] font-bold uppercase rounded-full px-2.5 py-1 bg-red-50 text-red-600">Negado</span>';
    }

    function renderTarjetaRecurso(r, contexto) {
        const tipo = r.tipo_recurso === 'REPOSICION' ? 'Reposición' : 'Apelación';
        let acciones = '';
        if (contexto === 'evaluador' && r.decision === 'PENDIENTE' && (r.tipo_recurso === 'REPOSICION' || r.tipo_recurso === 'APELACION')) {
            acciones = `
                <div class="mt-3 grid grid-cols-2 gap-2">
                    <select id="decision-${r.id_recurso}" class="w-full text-xs rounded-xl border border-slate-200 p-2 bg-white outline-none focus:border-[#00594E]">
                        <option value="APROBADO">Aprobado</option>
                        <option value="NEGADO">Negado</option>
                    </select>
                    <button onclick="decidirRecurso(${r.id_recurso})" class="bg-[#00594E] text-white px-3 py-2 rounded-xl text-xs font-bold hover:brightness-110 transition">Decidir</button>
                </div>`;
        }
        if (contexto === 'admin' && r.decision === 'PENDIENTE') {
            acciones = `
                <div class="mt-3 grid grid-cols-2 gap-2">
                    <select id="decision-admin-${r.id_recurso}" class="w-full text-xs rounded-xl border border-slate-200 p-2 bg-white outline-none focus:border-[#00594E]">
                        <option value="APROBADO">Aprobado</option>
                        <option value="NEGADO">Negado</option>
                    </select>
                    <button onclick="decidirRecursoAdmin(${r.id_recurso})" class="bg-[#00594E] text-white px-3 py-2 rounded-xl text-xs font-bold hover:brightness-110 transition">Decidir</button>
                </div>`;
        }
        return `
            <div class="rounded-2xl border border-slate-100 bg-white p-4 space-y-2">
                <div class="flex items-center justify-between gap-3">
                    <div class="flex items-center gap-2 min-w-0">
                        <span class="material-symbols-outlined text-base text-[#00594E]">gavel</span>
                        <div class="min-w-0">
                            <p class="text-xs font-black text-slate-800 truncate">${escapeHtml(r.numero_radicado || 'Sin radicado')} · ${tipo}</p>
                            <p class="text-[10px] text-slate-400">Radicado ${escapeHtml(r.fecha_recurso || '')} · ${r.numero_folios || 0} folios</p>
                        </div>
                    </div>
                    ${badgeDecisionRecurso(r.decision)}
                </div>
                ${r.receptor_nombres ? `<p class="text-[10px] font-bold text-slate-500 uppercase">Receptor: ${escapeHtml(r.receptor_nombres)} ${escapeHtml(r.receptor_apellidos || '')}</p>` : ''}
                ${r.evaluado_nombres ? `<p class="text-[10px] font-bold text-slate-500 uppercase">Evaluado: ${escapeHtml(r.evaluado_nombres)} ${escapeHtml(r.evaluado_apellidos || '')}${r.evaluado_cargo ? ' · ' + escapeHtml(r.evaluado_cargo) : ''}</p>` : ''}
                <p class="text-xs text-slate-600 whitespace-pre-wrap">${escapeHtml(r.motivacion || '')}</p>
                ${acciones}
            </div>`;
    }

    function cargarRecursosEvaluado(ev) {
        const bloque = document.getElementById('bloque-recursos-evaluado');
        if (!bloque || !ev) return;
        fetchJson(`/evaluaciones/${ev.id_evaluacion}/recursos`)
            .then(res => res.json())
            .then(payload => {
                const recursos = payload.recursos || [];
                const estado = payload.estado;
                const puedeRadicar = estado === 'CALIFICADA';
                const tienePendiente = recursos.some(r => r.decision === 'PENDIENTE');
                const aviso = document.getElementById('aviso-recursos-no-calificada');
                if (aviso) {
                    aviso.classList.toggle('hidden', puedeRadicar && !tienePendiente);
                    aviso.innerHTML = tienePendiente
                        ? '<span class="material-symbols-outlined text-base">hourglass_top</span><span>Ya tienes un recurso pendiente por decidir. Podrás radicar otro cuando este sea resuelto.</span>'
                        : '<span class="material-symbols-outlined text-base">info</span><span>Podrás radicar recursos cuando la evaluación haya sido calificada.</span>';
                }
                const form = document.getElementById('form-recurso-evaluado');
                if (form) form.classList.toggle('hidden', !puedeRadicar || tienePendiente);
                const contador = document.getElementById('recursos-contador-evaluado');
                if (contador) contador.innerText = String(recursos.length);
                const lista = document.getElementById('recursos-lista-evaluado');
                if (lista) {
                    if (!recursos.length) {
                        lista.innerHTML = '<div class="rounded-xl border border-dashed border-slate-200 bg-white p-4 text-xs text-slate-500 text-center">Aún no hay recursos radicados contra esta evaluación.</div>';
                    } else {
                        lista.innerHTML = recursos.map(r => renderTarjetaRecurso(r, 'evaluado')).join('');
                    }
                }
                const mensaje = document.getElementById('recurso-mensaje-evaluado');
                if (mensaje) mensaje.classList.add('hidden');
            })
            .catch(() => {});
    }

    function radicarRecurso(e) {
        e.preventDefault();
        if (!selectedEvaluacionId) return;
        const mensaje = document.getElementById('recurso-mensaje-evaluado');
        const tipo = document.getElementById('recurso-tipo-evaluado')?.value || 'REPOSICION';
        const folios = parseInt(document.getElementById('recurso-folios-evaluado')?.value || '0', 10);
        const motivacion = (document.getElementById('recurso-motivacion-evaluado')?.value || '').trim();
        if (!folios || folios < 1) { mostrarMensaje(mensaje, 'Indica el número de folios del recurso.', false); return; }
        if (!motivacion) { mostrarMensaje(mensaje, 'Escribe la motivación del recurso.', false); return; }
        fetchJson(`/evaluaciones/${selectedEvaluacionId}/recursos`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            body: JSON.stringify({ tipo_recurso: tipo, numero_folios: folios, motivacion }),
        })
            .then(async res => {
                const payload = await res.json().catch(() => ({}));
                if (!res.ok) throw new Error(parseErrorMessage(payload, 'No se pudo radicar el recurso.'));
                mostrarMensaje(mensaje, payload.message || 'Recurso radicado.', true);
                e.target.reset();
                cargarRecursosEvaluado(selectedEvaluacionData);
            })
            .catch(error => mostrarMensaje(mensaje, error.message, false));
    }

    function cargarRecursosEvaluador(ev) {
        const bloque = document.getElementById('bloque-recursos-evaluador');
        if (!bloque || !ev) return;
        fetchJson(`/evaluaciones/${ev.id_evaluacion}/recursos`)
            .then(res => res.json())
            .then(payload => {
                const recursos = (payload.recursos || []).filter(r => r.tipo_recurso === 'REPOSICION');
                const contador = document.getElementById('recursos-contador-evaluador');
                if (contador) contador.innerText = String(recursos.length);
                const lista = document.getElementById('recursos-lista-evaluador');
                if (lista) {
                    if (!recursos.length) {
                        lista.innerHTML = '<div class="rounded-xl border border-dashed border-slate-200 bg-white p-4 text-xs text-slate-500 text-center">No hay recursos de reposición radicados contra esta evaluación.</div>';
                    } else {
                        lista.innerHTML = recursos.map(r => renderTarjetaRecurso(r, 'evaluador')).join('');
                    }
                }
                bloque.classList.remove('hidden');
            })
            .catch(() => {});
    }

    function cargarRecursosMiosEvaluador() {
        const bloque = document.getElementById('bloque-recursos-mios-evaluador');
        if (!bloque) return;
        fetchJson('/recursos/mios')
            .then(res => res.json())
            .then(payload => {
                const recursos = payload.recursos || [];
                const contador = document.getElementById('recursos-mios-contador');
                if (contador) contador.innerText = String(recursos.length);
                const lista = document.getElementById('recursos-mios-lista');
                if (lista) {
                    if (!recursos.length) {
                        lista.innerHTML = '<div class="rounded-xl border border-dashed border-slate-200 bg-white p-4 text-xs text-slate-500 text-center">No tienes apelaciones pendientes por decidir.</div>';
                    } else {
                        lista.innerHTML = recursos.map(r => renderTarjetaRecurso(r, 'evaluador')).join('');
                    }
                }
                bloque.classList.toggle('hidden', !recursos.length);
            })
            .catch(() => {});
    }

    function enviarDecisionRecurso(id, decision, motivacion) {
        fetchJson(`/recursos/${id}/decision`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            body: JSON.stringify({ decision, motivacion }),
        })
            .then(async res => {
                const payload = await res.json().catch(() => ({}));
                if (!res.ok) throw new Error(parseErrorMessage(payload, 'No se pudo registrar la decisión.'));
                alert(payload.message || 'Decisión registrada.');
                if (selectedEvaluacionData) {
                    cargarRecursosEvaluado(selectedEvaluacionData);
                    cargarRecursosEvaluador(selectedEvaluacionData);
                }
                cargarRecursosAdmin();
                cargarRecursosMiosEvaluador();
            })
            .catch(error => alert(error.message));
    }

    function decidirRecurso(id) {
        const decision = document.getElementById(`decision-${id}`)?.value;
        const motivacion = prompt('Escribe la motivación de tu decisión sobre el recurso:');
        if (motivacion === null) return;
        if (!motivacion.trim()) { alert('La motivación es obligatoria para decidir el recurso.'); return; }
        enviarDecisionRecurso(id, decision, motivacion);
    }

    function decidirRecursoAdmin(id) {
        const decision = document.getElementById(`decision-admin-${id}`)?.value;
        const motivacion = prompt('Escribe la motivación de la decisión (Talento Humano) sobre el recurso:');
        if (motivacion === null) return;
        if (!motivacion.trim()) { alert('La motivación es obligatoria para decidir el recurso.'); return; }
        enviarDecisionRecurso(id, decision, motivacion);
    }

    function renderFirmasPlan(plan) {
        if (!plan) return '<span class="text-[10px] text-slate-400">El plan aún no ha sido creado.</span>';
        const row = (label, firmado, fecha) => `
            <div class="rounded-xl border border-slate-100 bg-white p-3">
                <p class="text-[10px] font-bold text-slate-500 uppercase">${label}</p>
                <p class="text-xs font-bold mt-1 ${firmado ? 'text-emerald-700' : 'text-amber-700'}">${firmado ? 'Firmado' : 'Sin firmar'}${fecha ? ` · ${escapeHtml(fecha)}` : ''}</p>
            </div>`;
        return row('Evaluador', !!plan.firmado_evaluador, plan.fecha_firma_evaluador)
            + row('Evaluado', !!plan.firmado_evaluado, plan.fecha_firma_evaluado);
    }

    function estadoPlanClass(plan) {
        const concertado = plan && plan.firmado_evaluador && plan.firmado_evaluado;
        return 'text-[10px] font-bold uppercase rounded-full px-2.5 py-1 '
            + (concertado ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700');
    }

    function cargarPlanMejoramientoEvaluador(ev) {
        const bloque = document.getElementById('bloque-plan-mejoramiento-evaluador');
        if (!bloque || !ev) return;
        fetchJson(`/evaluaciones/${ev.id_evaluacion}/plan-mejoramiento`)
            .then(res => res.json())
            .then(payload => {
                const requiere = !!payload.requiere_plan;
                bloque.classList.toggle('hidden', !requiere);
                if (!requiere) return;
                const plan = payload.plan;
                selectedPlanData = plan;
                const aviso = document.getElementById('plan-aviso-evaluador');
                if (aviso) aviso.classList.toggle('hidden', !payload.bloqueado);
                const estado = document.getElementById('plan-estado-evaluador');
                if (estado) {
                    estado.classList.remove('hidden');
                    estado.innerText = plan && plan.firmado_evaluador && plan.firmado_evaluado ? 'CONCERTADO' : (plan ? (plan.estado || 'PENDIENTE') : 'PENDIENTE');
                    estado.className = estadoPlanClass(plan);
                }
                const textarea = document.getElementById('plan-temas-evaluador');
                if (textarea && plan) textarea.value = plan.descripcion_temas || '';
                const editable = !plan || !plan.firmado_evaluador;
                const form = document.getElementById('form-plan-mejoramiento-evaluador');
                if (form) form.classList.toggle('hidden', !editable);
                const btnFirmar = document.getElementById('btn-firmar-plan-evaluador');
                if (btnFirmar) {
                    btnFirmar.classList.toggle('hidden', !(plan && !plan.firmado_evaluador));
                    btnFirmar.classList.toggle('opacity-50', !(plan && !plan.firmado_evaluador));
                }
                const firmas = document.getElementById('plan-firmas-evaluador');
                if (firmas) firmas.innerHTML = renderFirmasPlan(plan);
            })
            .catch(() => {});
    }

    function guardarPlanMejoramiento(e) {
        e.preventDefault();
        if (!selectedEvaluacionId) return;
        const mensaje = document.getElementById('plan-mensaje-evaluador');
        const temas = (document.getElementById('plan-temas-evaluador')?.value || '').trim();
        if (!temas) { mostrarMensaje(mensaje, 'Describe los temas del plan de mejoramiento.', false); return; }
        fetchJson(`/evaluaciones/${selectedEvaluacionId}/plan-mejoramiento`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            body: JSON.stringify({ descripcion_temas: temas }),
        })
            .then(async res => {
                const payload = await res.json().catch(() => ({}));
                if (!res.ok) throw new Error(parseErrorMessage(payload, 'No se pudo guardar el plan.'));
                mostrarMensaje(mensaje, payload.message || 'Plan guardado. Ya puedes firmarlo.', true);
                if (selectedEvaluacionData) {
                    cargarPlanMejoramientoEvaluador(selectedEvaluacionData);
                }
                setTimeout(() => { if (mensaje) mensaje.classList.add('hidden'); }, 4000);
            })
            .catch(error => mostrarMensaje(mensaje, error.message, false));
    }

    function cargarPlanMejoramientoEvaluado(ev) {
        const bloque = document.getElementById('bloque-plan-mejoramiento-evaluado');
        if (!bloque || !ev) return;
        fetchJson(`/evaluaciones/${ev.id_evaluacion}/plan-mejoramiento`)
            .then(res => res.json())
            .then(payload => {
                const requiere = !!payload.requiere_plan;
                bloque.classList.toggle('hidden', !requiere);
                if (!requiere) return;
                const plan = payload.plan;
                selectedPlanData = plan;
                const estado = document.getElementById('plan-estado-evaluado');
                if (estado) {
                    estado.classList.remove('hidden');
                    estado.innerText = plan && plan.firmado_evaluador && plan.firmado_evaluado ? 'CONCERTADO' : (plan ? (plan.estado || 'PENDIENTE') : 'PENDIENTE');
                    estado.className = estadoPlanClass(plan);
                }
                const contenido = document.getElementById('plan-contenido-evaluado');
                if (contenido) {
                    if (!plan) {
                        contenido.innerHTML = '<div class="rounded-xl border border-dashed border-slate-200 bg-white p-4 text-xs text-slate-500 text-center">Tu evaluador aún no ha redactado el plan de mejoramiento. Se habilitará cuando esté listo.</div>';
                    } else if (!plan.firmado_evaluador) {
                        contenido.innerHTML = `
                            <div class="rounded-2xl border border-amber-200 bg-amber-50/60 p-4 space-y-2">
                                <p class="text-xs font-bold text-amber-900">Plan de mejoramiento redactado por tu evaluador</p>
                                <p class="text-xs text-slate-700 whitespace-pre-wrap">${escapeHtml(plan.descripcion_temas || '')}</p>
                                <p class="text-[11px] text-amber-700 font-semibold pt-1 flex items-center gap-1">
                                    <span class="material-symbols-outlined text-sm">schedule</span>
                                    Tu evaluador debe firmar este plan para habilitar tu botón de firma.
                                </p>
                            </div>`;
                    } else {
                        contenido.innerHTML = `
                            <div class="rounded-2xl border border-slate-100 bg-slate-50/50 p-4">
                                <p class="text-[10px] font-bold text-slate-500 uppercase mb-2">Temas del plan</p>
                                <p class="text-xs text-slate-700 whitespace-pre-wrap">${escapeHtml(plan.descripcion_temas || '')}</p>
                            </div>`;
                    }
                }
                const puedeFirmar = plan && !!plan.firmado_evaluador && !plan.firmado_evaluado;
                const btnFirmar = document.getElementById('btn-firmar-plan-evaluado');
                if (btnFirmar) {
                    btnFirmar.classList.toggle('hidden', !puedeFirmar);
                    btnFirmar.classList.toggle('opacity-50', !puedeFirmar);
                }
                const firmas = document.getElementById('plan-firmas-evaluado');
                if (firmas) firmas.innerHTML = renderFirmasPlan(plan);
            })
            .catch(() => {});
    }

    function firmarPlanMejoramiento(rol) {
        if (!selectedEvaluacionId) return;
        fetchJson(`/evaluaciones/${selectedEvaluacionId}/plan-mejoramiento`)
            .then(res => res.json())
            .then(payload => {
                const plan = payload.plan;
                if (!plan || !plan.id_plan) {
                    alert('Primero se debe guardar el plan de mejoramiento antes de firmarlo.');
                    return;
                }
                if (!confirm('¿Confirmas firmar el plan de mejoramiento?')) return;
                fetchJson(`/plan-mejoramiento/${plan.id_plan}/firmar`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    body: '{}',
                })
                .then(async res => {
                    const pData = await res.json().catch(() => ({}));
                    if (!res.ok) throw new Error(parseErrorMessage(pData, 'No se pudo firmar el plan.'));
                    alert(pData.message || 'Firma registrada con éxito.');
                    if (selectedEvaluacionData) {
                        if (typeof cargarPlanMejoramientoEvaluador === 'function') cargarPlanMejoramientoEvaluador(selectedEvaluacionData);
                        if (typeof cargarPlanMejoramientoEvaluado === 'function') cargarPlanMejoramientoEvaluado(selectedEvaluacionData);
                    }
                })
                .catch(error => alert(error.message));
            })
            .catch(() => alert('Ocurrió un error al obtener la información del plan.'));
    }

    function cargarRecursosAdmin() {
        const lista = document.getElementById('recursos-admin-lista');
        const contador = document.getElementById('recursos-admin-contador');
        if (!lista) return;
        fetchJson('/recursos')
            .then(res => res.json())
            .then(payload => {
                const recursos = payload.recursos || [];
                if (contador) contador.innerText = recursos.length + (recursos.length === 1 ? ' recurso' : ' recursos');
                if (!recursos.length) {
                    lista.innerHTML = '<div class="col-span-full rounded-xl border border-dashed border-slate-200 bg-white p-8 text-xs text-slate-500 text-center">No hay recursos radicados.</div>';
                    return;
                }
                lista.innerHTML = recursos.map(r => `
                    <div class="rounded-2xl border border-slate-100 bg-white p-4 space-y-2">
                        <div class="flex items-center justify-between gap-3">
                            <div class="flex items-center gap-2 min-w-0">
                                <span class="material-symbols-outlined text-base text-[#00594E]">gavel</span>
                                <div class="min-w-0">
                                    <p class="text-xs font-black text-slate-800 truncate">${escapeHtml(r.numero_radicado || 'Sin radicado')} · ${r.tipo_recurso === 'REPOSICION' ? 'Reposición' : 'Apelación'}</p>
                                    <p class="text-[10px] text-slate-400">${escapeHtml(r.evaluado_nombres || '')} ${escapeHtml(r.evaluado_apellidos || '')} vs. ${escapeHtml(r.evaluador_nombres || '')} ${escapeHtml(r.evaluador_apellidos || '')}</p>
                                </div>
                            </div>
                            ${badgeDecisionRecurso(r.decision)}
                        </div>
                        <p class="text-xs text-slate-600 whitespace-pre-wrap">${escapeHtml(r.motivacion || '')}</p>
                        ${r.decision === 'PENDIENTE' ? `
                        <div class="pt-2 border-t border-slate-100 grid sm:grid-cols-2 gap-2">
                            <select id="decision-admin-${r.id_recurso}" class="w-full text-xs rounded-xl border border-slate-200 p-2 bg-white outline-none focus:border-[#00594E]">
                                <option value="APROBADO">Aprobado</option>
                                <option value="NEGADO">Negado</option>
                            </select>
                            <button onclick="decidirRecursoAdmin(${r.id_recurso})" class="bg-[#00594E] text-white px-3 py-2 rounded-xl text-xs font-bold hover:brightness-110 transition">Registrar decisión</button>
                        </div>` : ''}
                    </div>`).join('');
            })
            .catch(() => {});
    }

    function cargarPlanesAdmin() {
        const lista = document.getElementById('planes-admin-lista');
        if (!lista) return;
        fetchJson('/planes-mejoramiento')
            .then(res => res.json())
            .then(payload => {
                const planes = payload.planes || [];
                if (!planes.length) {
                    lista.innerHTML = '<div class="col-span-full rounded-xl border border-dashed border-slate-200 bg-white p-8 text-xs text-slate-500 text-center">No hay planes de mejoramiento registrados.</div>';
                    return;
                }
                lista.innerHTML = planes.map(p => `
                    <div class="rounded-2xl border border-slate-100 bg-white p-4 space-y-2">
                        <div class="flex items-center justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-xs font-black text-slate-800 truncate">${escapeHtml(p.evaluado_nombres || '')} ${escapeHtml(p.evaluado_apellidos || '')}</p>
                                <p class="text-[10px] text-slate-400">Evaluador: ${escapeHtml(p.evaluador_nombres || '')} ${escapeHtml(p.evaluador_apellidos || '')} · ${p.sistema === 'RENDIMIENTO_LABORAL' ? 'RL' : 'AG'}</p>
                            </div>
                            <span class="text-[10px] font-bold uppercase rounded-full px-2.5 py-1 ${p.firmado_evaluador && p.firmado_evaluado ? 'bg-emerald-50 text-emerald-700' : p.firmado_evaluador ? 'bg-[#EAF2EF] text-[#00594E]' : 'bg-amber-50 text-amber-700'}">${p.firmado_evaluador && p.firmado_evaluado ? 'Concertado' : p.firmado_evaluador ? 'Firmado evaluador' : 'Pendiente'}</span>
                        </div>
                        <p class="text-xs text-slate-600">Nota final: <b>${escapeHtml(String(p.calificacion_final ?? ''))}</b> · ${escapeHtml(p.categoria_final || '')}</p>
                        <p class="text-xs text-slate-600 whitespace-pre-wrap">${escapeHtml(p.descripcion_temas || '')}</p>
                        <div class="grid sm:grid-cols-2 gap-2">${renderFirmasPlan(p)}</div>
                    </div>`).join('');
            })
            .catch(() => {});
    }

    function cargarRenuenciasAdmin() {
        const lista = document.getElementById('renuencias-admin-lista');
        if (!lista) return;
        fetchJson('/renuencias')
            .then(res => res.json())
            .then(payload => {
                const renuencias = payload.renuencias || [];
                if (!renuencias.length) {
                    lista.innerHTML = '<div class="col-span-full rounded-xl border border-dashed border-slate-200 bg-white p-8 text-xs text-slate-500 text-center">No hay renuencias a la firma de notificación registradas.</div>';
                    return;
                }
                lista.innerHTML = renuencias.map(r => `
                    <div class="rounded-2xl border border-slate-100 bg-white p-4 space-y-2">
                        <div class="flex items-center justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-xs font-black text-slate-800 truncate">${escapeHtml(r.evaluado_nombres || '')} ${escapeHtml(r.evaluado_apellidos || '')}</p>
                                <p class="text-[10px] text-slate-400">Evaluador: ${escapeHtml(r.evaluador_nombres || '')} ${escapeHtml(r.evaluador_apellidos || '')} · ${r.sistema === 'RENDIMIENTO_LABORAL' ? 'RL' : 'AG'} · ${r.tipo_evaluacion === 'SEMESTRE_1' ? 'Semestre 1' : 'Otro'}</p>
                            </div>
                            <span class="text-[10px] font-bold uppercase rounded-full px-2.5 py-1 bg-amber-50 text-amber-700">${r.tipo_firma === 'NOTIFICACION_EVALUADO' ? 'Renuencia de notificación' : (r.tipo_firma === 'CONCERTACION_EVALUADOR' ? 'Evaluador renunció' : 'Evaluado renunció')}</span>
                        </div>
                        <p class="text-[10px] text-slate-400">Firma registrada: ${escapeHtml(r.fecha_firma || '')}</p>
                        <div class="rounded-xl border border-slate-100 bg-slate-50/50 p-3 space-y-1">
                            <p class="text-[9px] font-bold uppercase text-slate-400">Testigos</p>
                            ${(r.testigos || []).map(t => `<p class="text-xs text-slate-700">• ${escapeHtml(t.nombre_testigo)} — ${escapeHtml(t.cargo_testigo)}</p>`).join('')}
                        </div>
                    </div>`).join('');
            })
            .catch(() => {});
    }

    function mostrarEvaluadorActualTraslado() {
        const select = document.getElementById('select-funcionario-traslado');
        const box = document.getElementById('evaluador-origen-box');
        const texto = document.getElementById('evaluador-origen-texto');
        if (!select || !box || !texto) return;
        const idVinc = select.value;
        if (!idVinc) {
            box.classList.add('hidden');
            texto.innerText = '-';
            return;
        }
        fetchJson(`/admin/traslados/evaluador-actual/${idVinc}`)
            .then(res => res.json())
            .then(ev => {
                if (!ev) {
                    box.classList.remove('hidden');
                    texto.innerText = 'Sin evaluador asignado';
                    return;
                }
                box.classList.remove('hidden');
                texto.innerText = `${ev.nombres || ''} ${ev.apellidos || ''} — ${ev.cargo || 'Sin cargo'} (${ev.area || 'Sin área'})`;
            })
            .catch(() => {
                box.classList.remove('hidden');
                texto.innerText = 'Sin evaluador asignado';
            });
    }

    function cargarTrasladosAdmin() {
        const lista = document.getElementById('traslados-admin-lista');
        const contador = document.getElementById('traslados-admin-contador');
        if (!lista) return;
        fetchJson('/admin/traslados')
            .then(res => res.json())
            .then(traslados => {
                const items = Array.isArray(traslados) ? traslados : [];
                if (contador) contador.innerText = `${items.length} registro${items.length === 1 ? '' : 's'}`;
                if (!items.length) {
                    lista.innerHTML = '<div class="rounded-xl border border-dashed border-slate-200 bg-white p-8 text-xs text-slate-500 text-center">No hay traslados registrados.</div>';
                    return;
                }
                lista.innerHTML = items.map(t => `
                    <div class="rounded-2xl border border-slate-100 bg-white p-4 space-y-2">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-xs font-black text-slate-800 truncate">${escapeHtml(t.funcionario_nombres || '')} ${escapeHtml(t.funcionario_apellidos || '')}</p>
                                <p class="text-[10px] text-slate-400">Fecha: ${escapeHtml(t.fecha_traslado || '')}${t.resolucion ? ` · ${escapeHtml(t.resolucion)}` : ''}</p>
                            </div>
                            <span class="text-[10px] font-bold uppercase rounded-full px-2.5 py-1 bg-[#EAF2EF] text-[#00594E]">${t.dias_laborados ? t.dias_laborados + ' días' : 'Sin parcial'}</span>
                        </div>
                        <div class="grid grid-cols-2 gap-2 rounded-xl border border-slate-100 bg-slate-50/50 p-3">
                            <div>
                                <p class="text-[9px] font-bold uppercase text-slate-400">Origen</p>
                                <p class="text-xs text-slate-700 truncate">${escapeHtml(t.area_origen || '—')}${t.cargo_origen ? ` · ${escapeHtml(t.cargo_origen)}` : ''}</p>
                                <p class="text-[10px] text-slate-500 truncate">Evaluador: ${escapeHtml(t.origen_nombres || '')} ${escapeHtml(t.origen_apellidos || '')}</p>
                            </div>
                            <div>
                                <p class="text-[9px] font-bold uppercase text-slate-400">Nuevo</p>
                                <p class="text-xs text-slate-700 truncate">${escapeHtml(t.area_nuevo || '—')}${t.cargo_nuevo ? ` · ${escapeHtml(t.cargo_nuevo)}` : ''}</p>
                                <p class="text-[10px] text-slate-500 truncate">Evaluador: ${escapeHtml(t.nuevo_nombres || '')} ${escapeHtml(t.nuevo_apellidos || '')}</p>
                            </div>
                        </div>
                        ${t.referencia ? `<p class="text-[10px] font-semibold text-[#00594E]">${escapeHtml(t.referencia)}</p>` : ''}
                        ${t.motivo ? `<p class="text-[10px] text-slate-400 italic">${escapeHtml(t.motivo)}</p>` : ''}
                    </div>`).join('');
            })
            .catch(() => {
                if (contador) contador.innerText = 'Error';
            });
    }

    window.addEventListener('DOMContentLoaded', () => {
        const activeRole = "{{ $rolActivo }}";
        if (activeRole === 'admin') {
            navegarMenu(null, 'usuarios');
            cargarRecursosAdmin();
            cargarPlanesAdmin();
            cargarRenuenciasAdmin();
            cargarTrasladosAdmin();
        } else if (activeRole === 'evaluador') {
            navegarMenu(null, 'evaluaciones-evaluador');
            cargarRecursosMiosEvaluador();
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
