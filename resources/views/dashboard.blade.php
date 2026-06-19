@extends('layouts.app')

@section('content')
<style>
    body {
        overflow: hidden !important;
        height: 100vh !important;
        margin: 0;
        background: linear-gradient(180deg, #f4f7f5 0%, #eef3f0 100%);
        padding-top: 0;
        border: 0;
    }

    .panel-shell {
        display: grid;
        grid-template-rows: 1fr;
        height: 100vh;
        width: 100%;
        margin: 0;
        padding: 0;
        padding-top: 4.25rem;
    }

    .app-header {
        position: fixed;
        inset: 0 0 auto 0;
        z-index: 40;
        background: linear-gradient(135deg, #00352e 0%, #004037 45%, #00594e 100%);
        color: white;
        box-shadow: 0 10px 30px rgba(0, 64, 55, 0.22);
        border: 0;
        margin: 0;
        padding-top: 0;
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
        color: #004037;
        font-weight: 700;
    }

    .panel-card {
        background: rgba(255,255,255,0.85);
        backdrop-filter: blur(12px);
        border: 1px solid rgba(15,23,42,0.08);
        box-shadow: 0 12px 40px rgba(15,23,42,0.08);
    }

</style>

<div class="panel-shell">
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
                    <div class="w-10 h-10 rounded-full bg-[#B5A160] text-[#00352e] font-black flex items-center justify-center shadow-lg">
                        {{ strtoupper(substr($usuario['nombres'] ?? 'U', 0, 1) . substr($usuario['apellidos'] ?? 'X', 0, 1)) }}
                    </div>
                    <span class="material-symbols-outlined text-white/80 text-base hidden sm:block">expand_more</span>
                </button>

                <div id="profile-menu" class="profile-menu p-2">
                    <div class="px-4 py-3 border-b border-slate-100">
                        <p class="text-sm font-bold text-slate-800">{{ $usuario['nombres'] }} {{ $usuario['apellidos'] }}</p>
                        <p class="text-xs text-slate-500">{{ $usuario['correo'] }}</p>
                        <p class="text-[10px] uppercase tracking-[0.18em] text-[#004037] font-bold mt-1">{{ $rolActivo }}</p>
                    </div>
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
        <div id="sidebar-overlay" onclick="toggleSidebar()" class="fixed inset-0 bg-black/40 z-30 hidden lg:hidden"></div>

        <aside id="sidebar-menu" class="fixed lg:relative z-40 inset-y-0 left-0 w-64 -translate-x-full lg:translate-x-0 transition-transform duration-300 ease-in-out bg-white border-r border-slate-200 flex flex-col justify-between">
            <nav class="p-2.5 pt-1 space-y-1 overflow-y-auto">
                <button class="sidebar-link active w-full flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm text-slate-700 transition" onclick="navegarMenu(this, 'usuarios')">
                    <span class="material-symbols-outlined">group</span>
                    Usuarios
                </button>
                <button class="sidebar-link w-full flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm text-slate-700 transition" onclick="navegarMenu(this, 'empleados')">
                    <span class="material-symbols-outlined">badge</span>
                    Empleados
                </button>
                <button type="button" class="w-full flex items-center justify-between gap-3 px-4 py-2.5 rounded-xl text-sm text-slate-700 transition hover:bg-slate-50" onclick="toggleAcuerdos()">
                    <span class="flex items-center gap-3">
                        <span class="material-symbols-outlined">assignment</span>
                        Acuerdos
                    </span>
                    <span id="acuerdos-chevron" class="material-symbols-outlined text-base transition-transform">expand_more</span>
                </button>
                <div id="acuerdos-submenu" class="hidden pl-4 space-y-1">
                    <button class="sidebar-link w-full flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm text-slate-700 transition" onclick="navegarMenu(this, 'rl')">
                        <span class="material-symbols-outlined">assignment</span>
                        RL
                    </button>
                    <button class="sidebar-link w-full flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm text-slate-700 transition" onclick="navegarMenu(this, 'ag')">
                        <span class="material-symbols-outlined">gavel</span>
                        AG
                    </button>
                </div>
                <button class="sidebar-link w-full flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm text-slate-700 transition" onclick="navegarMenu(this, 'evaluaciones')">
                    <span class="material-symbols-outlined">fact_check</span>
                    Evaluación
                </button>
                <button class="sidebar-link w-full flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm text-slate-700 transition" onclick="navegarMenu(this, 'reportes')">
                    <span class="material-symbols-outlined">description</span>
                    Exportar PDF
                </button>
            </nav>

            <div class="p-4 border-t border-slate-100">
                <div class="rounded-xl bg-[#EAF2EF] p-4">
                    <p class="text-xs font-bold text-[#004037] uppercase tracking-[0.18em]">Sesión activa</p>
                    <p class="text-sm font-semibold text-slate-800 mt-1">{{ $usuario['nombres'] }} {{ $usuario['apellidos'] }}</p>
                    <p class="text-xs text-slate-500">{{ $usuario['correo'] }}</p>
                </div>
            </div>
        </aside>

        <main class="flex-1 min-w-0 p-4 sm:p-6 lg:p-8 overflow-y-auto">
            <section id="section-usuarios" class="section-content space-y-6">
                <div class="panel-card rounded-3xl p-6">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-[0.22em] text-[#004037]">Usuarios</p>
                            <h1 class="text-2xl sm:text-3xl font-black text-slate-900">Usuarios de la plataforma</h1>
                            <p class="text-sm text-slate-500 mt-1">Datos de acceso y relación con el empleado.</p>
                        </div>
                        <div class="text-sm text-slate-500">Total: <span class="font-bold text-slate-900">{{ $usuarios->count() }}</span></div>
                    </div>
                    <div class="mt-6 grid gap-4 lg:grid-cols-2 xl:grid-cols-3">
                        @foreach ($usuarios as $u)
                            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <h3 class="font-bold text-slate-900">{{ $u->nombres }} {{ $u->apellidos }}</h3>
                                        <p class="text-sm text-slate-500">{{ $u->correo_institucional }}</p>
                                    </div>
                                    <span class="text-[10px] font-bold uppercase rounded-full px-2.5 py-1 bg-[#EAF2EF] text-[#004037]">{{ $u->rol }}</span>
                                </div>
                                <div class="mt-4 grid grid-cols-2 gap-2 text-xs">
                                    <div class="rounded-xl bg-slate-50 p-3"><p class="text-slate-400 uppercase font-bold text-[10px]">Usuario</p><p class="font-semibold text-slate-800">#{{ $u->id_usuario }}</p></div>
                                    <div class="rounded-xl bg-slate-50 p-3"><p class="text-slate-400 uppercase font-bold text-[10px]">Empleado</p><p class="font-semibold text-slate-800">#{{ $u->id_empleado }}</p></div>
                                    <div class="rounded-xl bg-slate-50 p-3 col-span-2"><p class="text-slate-400 uppercase font-bold text-[10px]">Documento</p><p class="font-semibold text-slate-800">{{ $u->tipo_documento }} {{ $u->documento_identidad }}</p></div>
                                    <div class="rounded-xl bg-slate-50 p-3 col-span-2"><p class="text-slate-400 uppercase font-bold text-[10px]">Cargo</p><p class="font-semibold text-slate-800">{{ $u->nombre_cargo ?? 'Sin cargo' }}</p></div>
                                </div>
                                <form method="POST" action="{{ route('usuarios.reset-password', $u->id_usuario) }}" class="mt-4">
                                    @csrf
                                    <button class="w-full rounded-xl bg-slate-900 text-white py-2.5 text-sm font-bold" type="submit">Generar contraseña temporal</button>
                                </form>
                            </article>
                        @endforeach
                    </div>
                </div>
            </section>

            <section id="section-empleados" class="section-content hidden space-y-6">
                <div class="rounded-3xl p-1">
                    <p class="text-xs font-bold uppercase tracking-[0.22em] text-[#004037]">Empleados</p>
                    <h2 class="text-2xl sm:text-3xl font-black text-slate-900">Vista ampliada de empleados</h2>
                    <p class="text-sm text-slate-500 mt-1">Busca por cédula, nombre completo o correo.</p>

                    <div class="mt-6 grid gap-5 xl:grid-cols-[1.35fr_0.85fr]">
                        <div>
                            <input id="buscador-empleados" oninput="filtrarEmpleados()" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm shadow-sm outline-none focus:border-[#004037]" type="text" placeholder="Buscar por cédula, nombre o correo">

                            <div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-2">
                                @foreach ($empleados as $e)
                                    <article
                                        class="empleado-card rounded-2xl border border-slate-200 bg-white p-5 shadow-sm cursor-pointer transition hover:border-[#004037]"
                                        data-nombre="{{ strtolower($e->nombres . ' ' . $e->apellidos) }}"
                                        data-cedula="{{ strtolower($e->documento_identidad) }}"
                                        data-correo="{{ strtolower($e->correo_institucional ?? '') }}"
                                        data-cargo="{{ e($e->nombre_cargo ?? 'Sin cargo') }}"
                                        data-area="{{ e($e->nombre_area ?? 'Sin área') }}"
                                        data-estado="{{ $e->activo ? 'Activo' : 'Inactivo' }}"
                                        onclick="seleccionarEmpleado(this, @js($e))">
                                        <div class="flex items-center justify-between gap-4">
                                            <div>
                                                <h3 class="text-lg font-black text-slate-900">{{ $e->nombres }} {{ $e->apellidos }}</h3>
                                                <p class="text-sm text-slate-500">{{ $e->nombre_cargo ?? 'Sin cargo' }}</p>
                                            </div>
                                            <span class="w-10 h-10 rounded-full flex items-center justify-center bg-[#004037] text-white font-bold">{{ strtoupper(substr($e->nombres, 0, 1) . substr($e->apellidos, 0, 1)) }}</span>
                                        </div>
                                        <div class="mt-4 space-y-2 text-sm">
                                            <p><span class="font-bold text-slate-500">Correo:</span> {{ $e->correo_institucional ?? 'Sin correo' }}</p>
                                            <p><span class="font-bold text-slate-500">Documento:</span> {{ $e->tipo_documento }} {{ $e->documento_identidad }}</p>
                                            <p><span class="font-bold text-slate-500">Área:</span> {{ $e->nombre_area ?? 'Sin área' }}</p>
                                            <p><span class="font-bold text-slate-500">Estado:</span> {{ $e->activo ? 'Activo' : 'Inactivo' }}</p>
                                        </div>
                                    </article>
                                @endforeach
                            </div>
                        </div>

                        <aside class="rounded-3xl border border-slate-200 bg-gradient-to-br from-white to-slate-50 p-6 shadow-sm sticky top-4">
                            <p class="text-xs font-bold uppercase tracking-[0.22em] text-[#004037]">Detalle del empleado</p>
                            <div class="mt-4 flex items-start gap-4 pb-4 border-b border-slate-100">
                                <div id="empleado-avatar" class="w-20 h-20 rounded-3xl bg-[#004037] flex items-center justify-center text-white text-2xl font-black shadow-lg">--</div>
                                <div class="min-w-0">
                                    <h3 id="empleado-nombre" class="text-2xl font-black text-slate-900 leading-tight">Selecciona un empleado</h3>
                                    <p id="empleado-cargo" class="text-sm text-slate-500 mt-1">Verás su información ampliada aquí</p>
                                    <div class="mt-3 inline-flex items-center rounded-full bg-[#EAF2EF] px-3 py-1 text-[10px] font-bold uppercase tracking-[0.18em] text-[#004037]">Ficha activa</div>
                                </div>
                            </div>

                            <div class="mt-5 grid gap-3 text-sm">
                                <div class="rounded-2xl bg-white border border-slate-200 p-4 shadow-sm flex justify-between gap-4"><span class="text-slate-500 font-bold">Correo</span><span id="empleado-correo" class="text-slate-800 text-right font-medium">-</span></div>
                                <div class="rounded-2xl bg-white border border-slate-200 p-4 shadow-sm flex justify-between gap-4"><span class="text-slate-500 font-bold">Documento</span><span id="empleado-documento" class="text-slate-800 text-right font-medium">-</span></div>
                                <div class="rounded-2xl bg-white border border-slate-200 p-4 shadow-sm flex justify-between gap-4"><span class="text-slate-500 font-bold">Área</span><span id="empleado-area" class="text-slate-800 text-right font-medium">-</span></div>
                                <div class="rounded-2xl bg-white border border-slate-200 p-4 shadow-sm flex justify-between gap-4"><span class="text-slate-500 font-bold">Estado</span><span id="empleado-estado" class="text-slate-800 text-right font-medium">-</span></div>
                            </div>
                        </aside>
                    </div>
                </div>
            </section>

            <section id="section-rl" class="section-content hidden space-y-6">
                <div class="panel-card rounded-3xl p-6">
                    <p class="text-xs font-bold uppercase tracking-[0.22em] text-[#004037]">Acuerdos / RL</p>
                    <h2 class="text-2xl sm:text-3xl font-black text-slate-900">Rendimiento Laboral</h2>
                    <div class="mt-4 grid gap-4 lg:grid-cols-2">
                        <article class="rounded-2xl bg-white border border-slate-200 p-5">
                            <h3 class="font-bold text-slate-900">Parámetros</h3>
                            <p class="text-sm text-slate-600 mt-2 leading-relaxed">Población objetivo: provisionalidad. Ponderación 70/15/15. Fases: preparación, concertación, seguimiento y evidencias, calificación semestral y definitiva.</p>
                        </article>
                        <article class="rounded-2xl bg-white border border-slate-200 p-5">
                            <h3 class="font-bold text-slate-900">Reglas</h3>
                            <p class="text-sm text-slate-600 mt-2 leading-relaxed">7 a 10 compromisos, pesos individuales de 10% a 40%, total 100% para compromisos. Prorrateo automático para periodos parciales.</p>
                        </article>
                    </div>
                </div>
            </section>

            <section id="section-ag" class="section-content hidden space-y-6">
                <div class="panel-card rounded-3xl p-6">
                    <p class="text-xs font-bold uppercase tracking-[0.22em] text-[#004037]">Acuerdos / AG</p>
                    <h2 class="text-2xl sm:text-3xl font-black text-slate-900">Acuerdos de Gestión</h2>
                    <div class="mt-4 grid gap-4 lg:grid-cols-2">
                        <article class="rounded-2xl bg-white border border-slate-200 p-5">
                            <h3 class="font-bold text-slate-900">Parámetros</h3>
                            <p class="text-sm text-slate-600 mt-2 leading-relaxed">Población objetivo: Libre Nombramiento y Remoción y Período Fijo. Ponderación 85/7.5/7.5. Las cinco fases de evaluación aplican igual que RL.</p>
                        </article>
                        <article class="rounded-2xl bg-white border border-slate-200 p-5">
                            <h3 class="font-bold text-slate-900">Reglas</h3>
                            <p class="text-sm text-slate-600 mt-2 leading-relaxed">Plan de mejoramiento obligatorio si la primera calificación es no satisfactoria. Prorrateo soportado para evaluaciones parciales.</p>
                        </article>
                    </div>
                </div>
            </section>

            <section id="section-evaluaciones" class="section-content hidden space-y-6">
                <div class="panel-card rounded-3xl p-6">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-[0.22em] text-[#004037]">Evaluación</p>
                            <h2 class="text-2xl sm:text-3xl font-black text-slate-900">Resultados por empleado</h2>
                            <p class="text-sm text-slate-500 mt-1">Card resumen con fases, escala, categoría y compromisos.</p>
                        </div>
                        <button class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-bold text-slate-700" onclick="exportarPDF()">Exportar PDF</button>
                    </div>

                    <div class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        @foreach ($evaluaciones as $ev)
                            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <h3 class="font-black text-slate-900">{{ $ev->evaluado_nombres }} {{ $ev->evaluado_apellidos }}</h3>
                                        <p class="text-sm text-slate-500">{{ $ev->tipo_nombre ?? 'Evaluación' }}</p>
                                    </div>
                                    <span class="text-[10px] font-bold uppercase rounded-full px-2.5 py-1 bg-[#EAF2EF] text-[#004037]">{{ $ev->estado }}</span>
                                </div>
                                <div class="mt-4 space-y-2 text-sm">
                                    <p><span class="font-bold text-slate-500">Evaluador:</span> {{ $ev->evaluador_nombres }} {{ $ev->evaluador_apellidos }}</p>
                                    <p><span class="font-bold text-slate-500">Inicio:</span> {{ $ev->fecha_inicio }}</p>
                                    <p><span class="font-bold text-slate-500">Fin:</span> {{ $ev->fecha_fin ?? 'En curso' }}</p>
                                    <p><span class="font-bold text-slate-500">Fases:</span> 5 fases</p>
                                    <p><span class="font-bold text-slate-500">Escala:</span> 0 a 100</p>
                                    <p><span class="font-bold text-slate-500">Categorías:</span> Sobresaliente, Bueno, Aprobado-Susceptible de mejora, No satisfactorio</p>
                                </div>
                            </article>
                        @endforeach
                    </div>
                </div>
            </section>

            <section id="section-reportes" class="section-content hidden space-y-6">
                <div class="panel-card rounded-3xl p-6">
                    <p class="text-xs font-bold uppercase tracking-[0.22em] text-[#004037]">Exportación</p>
                    <h2 class="text-2xl sm:text-3xl font-black text-slate-900">Resultados organizados en PDF</h2>
                    <p class="text-sm text-slate-500 mt-1">Aquí puedes agregar la generación de reportes por usuario, empleado o evaluación.</p>
                    <div class="mt-6 grid gap-4 md:grid-cols-2">
                        <button class="rounded-2xl bg-[#004037] text-white font-bold py-4">Exportar usuarios</button>
                        <button class="rounded-2xl bg-[#B5A160] text-white font-bold py-4">Exportar evaluaciones</button>
                    </div>
                </div>
            </section>
        </main>
    </div>
</div>

<div id="password-modal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50 px-4">
    <div class="w-full max-w-md rounded-3xl bg-white p-6 shadow-2xl">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-xl font-black text-slate-900">Cambiar contraseña</h3>
            <button onclick="closePasswordModal()" class="text-slate-500 hover:text-slate-900"><span class="material-symbols-outlined">close</span></button>
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
            <button type="submit" class="w-full rounded-2xl bg-[#004037] text-white font-bold py-3">Guardar cambio</button>
        </form>
    </div>
</div>

@if (session('temp_password'))
<div id="temp-password-toast" class="fixed bottom-4 right-4 z-50 max-w-sm rounded-2xl bg-slate-900 text-white p-4 shadow-2xl">
    <p class="text-xs uppercase tracking-[0.18em] text-[#B5A160] font-bold">Contraseña temporal generada</p>
    <p class="mt-2 text-sm">Entrega esta contraseña al usuario para su primer acceso.</p>
    <div class="mt-3 rounded-xl bg-white/10 p-3 text-lg font-black tracking-wider">{{ session('temp_password') }}</div>
    <button onclick="document.getElementById('temp-password-toast').remove()" class="mt-3 text-xs font-bold text-white/80">Cerrar</button>
</div>
@endif

<script>
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar-menu');
        const overlay = document.getElementById('sidebar-overlay');
        sidebar.classList.toggle('-translate-x-full');
        overlay.classList.toggle('hidden');
    }

    function toggleProfileMenu() {
        document.getElementById('profile-menu').classList.toggle('open');
    }

    function toggleAcuerdos() {
        const submenu = document.getElementById('acuerdos-submenu');
        const chevron = document.getElementById('acuerdos-chevron');
        submenu.classList.toggle('hidden');
        chevron.classList.toggle('rotate-180');
    }

    function openPasswordModal() {
        document.getElementById('password-modal').classList.remove('hidden');
        document.getElementById('password-modal').classList.add('flex');
        document.getElementById('profile-menu').classList.remove('open');
    }

    function closePasswordModal() {
        document.getElementById('password-modal').classList.add('hidden');
        document.getElementById('password-modal').classList.remove('flex');
    }

    function navegarMenu(button, seccion) {
        const map = {
            usuarios: 'usuarios',
            empleados: 'empleados',
            rl: 'rl',
            ag: 'ag',
            evaluaciones: 'evaluaciones',
            reportes: 'reportes'
        };

        document.querySelectorAll('.section-content').forEach(el => el.classList.add('hidden'));
        const target = document.getElementById(`section-${map[seccion]}`);
        if (target) {
            target.classList.remove('hidden');
        }

        document.querySelectorAll('.sidebar-link').forEach(btn => btn.classList.remove('active'));
        if (button) {
            button.classList.add('active');
        }

        if (window.innerWidth < 1024) toggleSidebar();
    }

    function exportarPDF() {
        alert('Aquí se conectará la exportación a PDF de evaluaciones.');
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
        document.getElementById('empleado-avatar').innerText = (empleado.nombres?.[0] || '') + (empleado.apellidos?.[0] || '');
        document.getElementById('empleado-nombre').innerText = `${empleado.nombres} ${empleado.apellidos}`;
        document.getElementById('empleado-cargo').innerText = empleado.nombre_cargo || 'Sin cargo';
        document.getElementById('empleado-correo').innerText = empleado.correo_institucional || 'Sin correo';
        document.getElementById('empleado-documento').innerText = `${empleado.tipo_documento || ''} ${empleado.documento_identidad || ''}`.trim();
        document.getElementById('empleado-area').innerText = empleado.nombre_area || 'Sin área';
        document.getElementById('empleado-estado').innerText = empleado.activo ? 'Activo' : 'Inactivo';

        document.querySelectorAll('.empleado-card').forEach(el => el.classList.remove('ring-2', 'ring-[#004037]'));
        card.classList.add('ring-2', 'ring-[#004037]');
    }

    document.addEventListener('click', (e) => {
        const menu = document.getElementById('profile-menu');
        if (!menu.contains(e.target) && !e.target.closest('button[onclick="toggleProfileMenu()"]')) {
            menu.classList.remove('open');
        }
    });

    window.addEventListener('DOMContentLoaded', () => {
        const firstCard = document.querySelector('.empleado-card');
        if (firstCard) {
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
