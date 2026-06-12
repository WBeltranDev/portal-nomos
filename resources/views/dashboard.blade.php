@extends('layouts.app')

@section('content')
<header class="w-full h-16 bg-[#004037] text-white flex items-center justify-between px-4 flex-shrink-0 z-20 border-b border-[#00594e]">
    <div class="flex items-center gap-2 sm:gap-4">
        <button onclick="toggleSidebar()" class="lg:hidden p-1.5 rounded hover:bg-white/10 flex items-center justify-center text-white mr-1">
            <span class="material-symbols-outlined text-lg">menu</span>
        </button>
        <div class="border border-[#B5A160]/40 rounded px-2.5 py-1 bg-[#B5A160]/10 font-bold tracking-widest text-[10px] sm:text-xs text-[#B5A160]">
            UNITRÓPICO
        </div>
        <div class="flex flex-col border-l border-white/10 pl-2 sm:pl-4">
            <span class="text-[10px] sm:text-xs font-semibold text-white tracking-wide uppercase">Evaluación del Desempeño Institucional</span>
        </div>
    </div>

    <div class="flex items-center gap-3 sm:gap-6 text-xs">
        <div class="flex flex-col text-right hidden md:flex">
            <span class="text-[9px] text-[#B5A160] uppercase font-bold tracking-wider">Período Evaluativo</span>
            <span class="font-medium text-white/95">Vigencia 2026</span>
        </div>
        <div class="flex items-center gap-2 sm:gap-3 border-l border-white/10 pl-3 sm:pl-6">
            <div class="flex flex-col text-right hidden sm:flex">
                <span class="font-semibold text-white">Dirección de Talento Humano</span>
                <span class="text-[10px] text-[#B5A160] font-semibold">Rol: Administrador</span>
            </div>
            <div class="w-8 h-8 rounded-full bg-white/5 border border-[#B5A160]/30 flex items-center justify-center font-bold text-xs text-[#B5A160]">
                TH
            </div>
        </div>
    </div>
</header>

<div class="flex flex-1 w-full bg-slate-50 overflow-hidden relative">
    
    <!-- Sidebar Overlay for mobile -->
    <div id="sidebar-overlay" onclick="toggleSidebar()" class="fixed inset-0 bg-black/40 z-20 hidden lg:hidden"></div>

    <aside id="sidebar-menu" class="fixed inset-y-0 left-0 w-64 lg:relative lg:translate-x-0 -translate-x-full transition-transform duration-300 ease-in-out flex-shrink-0 flex flex-col justify-between py-4 bg-white border-r border-slate-200 h-full z-30">
        <div>
            <div class="px-6 py-2 mb-4 flex items-center justify-between">
                <div>
                    <h2 class="text-sm font-bold text-slate-700">Gestión Académica</h2>
                    <p class="text-[11px] text-slate-400">Administrador</p>
                </div>
                <button onclick="toggleSidebar()" class="lg:hidden p-1 rounded hover:bg-slate-100 text-slate-500 flex items-center justify-center">
                    <span class="material-symbols-outlined text-base">close</span>
                </button>
            </div>
            <nav class="px-3 space-y-0.5">
                <a id="link-dashboard" onclick="navegarMenu('dashboard')" class="flex items-center gap-3 px-4 py-2.5 text-slate-600 hover:bg-slate-50 rounded-lg text-xs font-medium cursor-pointer transition-all">
                    <span class="material-symbols-outlined text-base">dashboard</span>
                    <span>Dashboard</span>
                </a>
                <a id="link-employees" onclick="navegarMenu('employees')" class="flex items-center gap-3 px-4 py-2.5 text-[#004037] font-bold bg-[#E6F2F0] rounded-lg text-xs cursor-pointer transition-all">
                    <span class="material-symbols-outlined text-base">group</span>
                    <span>Empleados</span>
                </a>
                <a id="link-evaluaciones" onclick="navegarMenu('evaluaciones')" class="flex items-center gap-3 px-4 py-2.5 text-slate-600 hover:bg-slate-50 rounded-lg text-xs font-medium cursor-pointer transition-all">
                    <span class="material-symbols-outlined text-base">assignment</span>
                    <span>Evaluaciones</span>
                </a>
                <a class="flex items-center gap-3 px-4 py-2.5 text-slate-600 hover:bg-slate-50 rounded-lg text-xs font-medium" href="#">
                    <span class="material-symbols-outlined text-base">psychology</span>
                    <span>Competencias</span>
                </a>
                <a class="flex items-center gap-3 px-4 py-2.5 text-slate-600 hover:bg-slate-50 rounded-lg text-xs font-medium" href="#">
                    <span class="material-symbols-outlined text-base">trending_up</span>
                    <span>Planes de Mejoramiento</span>
                </a>
                <a class="flex items-center gap-3 px-4 py-2.5 text-slate-600 hover:bg-slate-50 rounded-lg text-xs font-medium" href="#">
                    <span class="material-symbols-outlined text-base">description</span>
                    <span>Reportes</span>
                </a>
                <a class="flex items-center gap-3 px-4 py-2.5 text-slate-600 hover:bg-slate-50 rounded-lg text-xs font-medium" href="#">
                    <span class="material-symbols-outlined text-base">settings</span>
                    <span>Configuración</span>
                </a>
            </nav>
        </div>
        <div class="px-3">
            <a class="flex items-center gap-3 px-4 py-2.5 text-slate-500 hover:bg-red-50 hover:text-red-600 rounded-lg text-xs font-medium transition-all border-t border-slate-100 pt-4" href="/">
                <span class="material-symbols-outlined text-base">logout</span>
                <span>Cerrar Sesión</span>
            </a>
        </div>
    </aside>

    <div class="flex-1 h-full relative overflow-hidden bg-slate-50">

        <div id="vista-dashboard" class="hidden p-6 h-full w-full absolute inset-0 overflow-y-auto">
            <header class="mb-6">
                <h1 class="text-base font-bold text-slate-800">Dashboard General</h1>
                <p class="text-xs text-slate-500">Métricas globales consolidando los avances de la vigencia actual.</p>
            </header>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <div class="bg-white p-4 border border-slate-200 rounded-xl shadow-xs">
                    <p class="text-[10px] text-slate-400 font-bold uppercase">Total Personal</p>
                    <h3 class="text-xl font-black text-slate-800 mt-1">10</h3>
                </div>
            </div>
        </div>

        <div id="vista-employees" class="p-4 lg:p-6 flex flex-col lg:flex-row h-full w-full absolute inset-0 overflow-y-auto lg:overflow-hidden">
            <div class="w-full lg:w-[66%] flex flex-col h-auto lg:h-full overflow-visible lg:overflow-hidden">
                <header class="mb-4 flex-shrink-0">
                    <h2 class="text-base font-bold text-[#004037]">Estado de evaluaciones — Período 2025-1</h2>
                    <div class="flex flex-col sm:flex-row sm:items-center gap-4 mt-2">
                        <div class="flex flex-wrap gap-2">
                            <span class="bg-[#9E9E22]/10 text-[#9E9E22] text-[10px] font-bold uppercase px-3 py-1 rounded border border-[#9E9E22]/20">● 3 Pendientes</span>
                            <span class="bg-blue-50 text-blue-600 text-[10px] font-bold uppercase px-3 py-1 rounded border border-blue-100">● 4 En progreso</span>
                            <span class="bg-emerald-50 text-emerald-700 text-[10px] font-bold uppercase px-3 py-1 rounded border border-emerald-100">● 3 Completadas</span>
                        </div>
                        <select class="bg-white border border-slate-200 rounded px-2 py-1 text-xs text-slate-600 outline-none">
                            <option>Área: Todas las facultades</option>
                        </select>
                    </div>
                </header>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 flex-1 overflow-visible lg:overflow-y-auto pb-2">
                    
                    <div class="flex flex-col bg-slate-100/60 border border-slate-200 rounded-xl p-3 overflow-visible h-auto lg:overflow-hidden lg:h-full">
                        <div class="flex items-center justify-between mb-3 flex-shrink-0"><span class="font-bold text-xs text-slate-700">Pendientes <span class="bg-amber-200 text-amber-800 text-[10px] px-1.5 py-0.2 rounded-full ml-1">3</span></span></div>
                        <div class="flex-1 overflow-visible lg:overflow-y-auto space-y-3 pr-1" style="scrollbar-width: none;">
                            <div onclick="verDetalleCard('Ricardo Mendoza', 'Docente Investigador', 'Dpto. Ciencias Biológicas', 'DOC-009', 'Dpto. Sistemas', 'A', 'RM', 0, '0 / 15', '0 / 6', 'PENDIENTE')" class="bg-white border border-slate-200 rounded-lg p-3.5 shadow-sm hover:border-[#004037] cursor-pointer transition-all">
                                <div class="flex items-center gap-3 mb-2">
                                    <div class="w-8 h-8 rounded bg-[#9E9E22] flex items-center justify-center text-white font-bold text-xs">RM</div>
                                    <div><h4 class="text-xs font-bold text-slate-800 leading-tight">Ricardo Mendoza</h4><p class="text-[10px] text-slate-400">Docente Investigador</p></div>
                                </div>
                                <p class="text-[10px] text-slate-500 mb-2">Dpto. Ciencias Biológicas</p>
                                <div class="flex justify-between text-[9px] text-slate-400 mb-1"><span>Progreso</span><span>0%</span></div>
                                <div class="w-full bg-slate-100 h-1.5 rounded-full overflow-hidden"><div class="bg-[#9E9E22] h-full w-0"></div></div>
                            </div>
                            <div onclick="verDetalleCard('Lucía Gutiérrez', 'Catedrático', 'Dpto. Ingeniería Civil', 'DOC-012', 'Dpto. Humanidades', 'B', 'LG', 0, '0 / 15', '0 / 6', 'PENDIENTE')" class="bg-white border border-slate-200 rounded-lg p-3.5 shadow-sm hover:border-[#004037] cursor-pointer transition-all">
                                <div class="flex items-center gap-3 mb-2">
                                    <div class="w-8 h-8 rounded bg-[#A3A3C2] flex items-center justify-center text-white font-bold text-xs">LG</div>
                                    <div><h4 class="text-xs font-bold text-slate-800 leading-tight">Lucía Gutiérrez</h4><p class="text-[10px] text-slate-400">Catedrático</p></div>
                                </div>
                                <p class="text-[10px] text-slate-500 mb-2">Dpto. Ingeniería Civil</p>
                                <div class="flex justify-between text-[9px] text-slate-400 mb-1"><span>Progreso</span><span>0%</span></div>
                                <div class="w-full bg-slate-100 h-1.5 rounded-full overflow-hidden"><div class="bg-slate-400 h-full w-0"></div></div>
                            </div>
                            <div onclick="verDetalleCard('Andrés Salazar', 'Docente de Planta', 'Dpto. Humanidades', 'DOC-018', 'Dpto. Ingeniería Civil', 'A', 'AS', 0, '0 / 15', '0 / 6', 'PENDIENTE')" class="bg-white border border-slate-200 rounded-lg p-3.5 shadow-sm hover:border-[#004037] cursor-pointer transition-all">
                                <div class="flex items-center gap-3 mb-2">
                                    <div class="w-8 h-8 rounded bg-[#00594e] flex items-center justify-center text-white font-bold text-xs">AS</div>
                                    <div><h4 class="text-xs font-bold text-slate-800 leading-tight">Andrés Salazar</h4><p class="text-[10px] text-slate-400">Docente de Planta</p></div>
                                </div>
                                <p class="text-[10px] text-slate-500 mb-2">Dpto. Humanidades</p>
                                <div class="flex justify-between text-[9px] text-slate-400 mb-1"><span>Progreso</span><span>0%</span></div>
                                <div class="w-full bg-slate-100 h-1.5 rounded-full overflow-hidden"><div class="bg-[#00594e] h-full w-0"></div></div>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-col bg-slate-100/60 border border-slate-200 rounded-xl p-3 overflow-visible h-auto lg:overflow-hidden lg:h-full">
                        <div class="flex items-center justify-between mb-3 flex-shrink-0"><span class="font-bold text-xs text-slate-700">En progreso <span class="bg-blue-200 text-blue-800 text-[10px] px-1.5 py-0.2 rounded-full ml-1">4</span></span></div>
                        <div class="flex-1 overflow-visible lg:overflow-y-auto space-y-3 pr-1" style="scrollbar-width: none;">
                            <div onclick="verDetalleCard('Marta Pedraza', 'Docente Investigador', 'Dpto. Agronomía', 'DOC-014', 'Dpto. Contaduría', 'A', 'MP', 65, '10 / 15', '4 / 6', 'EN PROCESO')" class="bg-white border border-slate-200 rounded-lg p-3.5 shadow-sm hover:border-[#004037] cursor-pointer transition-all">
                                <div class="flex items-center gap-3 mb-2">
                                    <div class="w-8 h-8 rounded bg-blue-600 flex items-center justify-center text-white font-bold text-xs">MP</div>
                                    <div><h4 class="text-xs font-bold text-slate-800 leading-tight">Marta Pedraza</h4><p class="text-[10px] text-slate-400">Docente Investigador</p></div>
                                </div>
                                <p class="text-[10px] text-slate-500 mb-2">Dpto. Agronomía</p>
                                <div class="flex justify-between text-[9px] text-slate-400 mb-1"><span>Progreso</span><span>65%</span></div>
                                <div class="w-full bg-slate-100 h-1.5 rounded-full overflow-hidden"><div class="bg-blue-600 h-full w-[65%]"></div></div>
                            </div>
                            <div onclick="verDetalleCard('Jorge Villamil', 'Catedrático', 'Dpto. Sistemas', 'DOC-022', 'Dpto. Agronomía', 'B', 'JV', 30, '5 / 15', '2 / 6', 'EN PROCESO')" class="bg-white border border-slate-200 rounded-lg p-3.5 shadow-sm hover:border-[#004037] cursor-pointer transition-all">
                                <div class="flex items-center gap-3 mb-2">
                                    <div class="w-8 h-8 rounded bg-[#66C2A5] flex items-center justify-center text-white font-bold text-xs">JV</div>
                                    <div><h4 class="text-xs font-bold text-slate-800 leading-tight">Jorge Villamil</h4><p class="text-[10px] text-slate-400">Catedrático</p></div>
                                </div>
                                <p class="text-[10px] text-slate-500 mb-2">Dpto. Sistemas</p>
                                <div class="flex justify-between text-[9px] text-slate-400 mb-1"><span>Progreso</span><span>30%</span></div>
                                <div class="w-full bg-slate-100 h-1.5 rounded-full overflow-hidden"><div class="bg-[#66C2A5] h-full w-[30%]"></div></div>
                            </div>
                            <div onclick="verDetalleCard('Clara Rojas', 'Docente de Planta', 'Dpto. Medicina Veterinaria', 'DOC-004', 'Dpto. Arquitectura', 'A', 'CR', 85, '13 / 15', '5 / 6', 'EN PROCESO')" class="bg-white border border-slate-200 rounded-lg p-3.5 shadow-sm hover:border-[#004037] cursor-pointer transition-all">
                                <div class="flex items-center gap-3 mb-2">
                                    <div class="w-8 h-8 rounded bg-[#B38F4D] flex items-center justify-center text-white font-bold text-xs">CR</div>
                                    <div><h4 class="text-xs font-bold text-slate-800 leading-tight">Clara Rojas</h4><p class="text-[10px] text-slate-400">Docente de Planta</p></div>
                                </div>
                                <p class="text-[10px] text-slate-500 mb-2">Dpto. Medicina Veterinaria</p>
                                <div class="flex justify-between text-[9px] text-slate-400 mb-1"><span>Progreso</span><span>85%</span></div>
                                <div class="w-full bg-slate-100 h-1.5 rounded-full overflow-hidden"><div class="bg-[#B38F4D] h-full w-[85%]"></div></div>
                            </div>
                            <div onclick="verDetalleCard('Felipe Parra', 'Asistente Administrativo', 'Dpto. Contaduría', 'DOC-099', 'Dpto. Idiomas', 'C', 'FP', 15, '2 / 15', '1 / 6', 'EN PROCESO')" class="bg-white border border-slate-200 rounded-lg p-3.5 shadow-sm hover:border-[#004037] cursor-pointer transition-all">
                                <div class="flex items-center gap-3 mb-2">
                                    <div class="w-8 h-8 rounded bg-[#859900] flex items-center justify-center text-white font-bold text-xs">FP</div>
                                    <div><h4 class="text-xs font-bold text-slate-800 leading-tight">Felipe Parra</h4><p class="text-[10px] text-slate-400">Asistente Administrativo</p></div>
                                </div>
                                <p class="text-[10px] text-slate-500 mb-2">Dpto. Contaduría</p>
                                <div class="flex justify-between text-[9px] text-slate-400 mb-1"><span>Progreso</span><span>15%</span></div>
                                <div class="w-full bg-slate-100 h-1.5 rounded-full overflow-hidden"><div class="bg-[#859900] h-full w-[15%]"></div></div>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-col bg-slate-100/60 border border-slate-200 rounded-xl p-3 overflow-visible h-auto lg:overflow-hidden lg:h-full">
                        <div class="flex items-center justify-between mb-3 flex-shrink-0"><span class="font-bold text-xs text-slate-700">Completadas <span class="bg-emerald-200 text-emerald-800 text-[10px] px-1.5 py-0.2 rounded-full ml-1">3</span></span></div>
                        <div class="flex-1 overflow-visible lg:overflow-y-auto space-y-3 pr-1" style="scrollbar-width: none;">
                            <div onclick="verDetalleCard('Sofía Restrepo', 'Docente de Planta', 'Dpto. Arquitectura', 'DOC-033', 'Dpto. Matemáticas', 'A', 'SR', 100, '15 / 15', '6 / 6', 'CALIFICADO: 4.8 / 5.0')" class="bg-white border border-slate-200 rounded-lg p-3.5 shadow-sm hover:border-[#004037] cursor-pointer transition-all">
                                <div class="flex items-center gap-3 mb-2">
                                    <div class="w-8 h-8 rounded bg-[#004F3F] flex items-center justify-center text-white font-bold text-xs">SR</div>
                                    <div><h4 class="text-xs font-bold text-slate-800 leading-tight">Sofía Restrepo</h4><p class="text-[10px] text-slate-400">Docente de Planta</p></div>
                                </div>
                                <p class="text-[10px] text-slate-500 mb-2">Dpto. Arquitectura</p>
                                <div class="w-full bg-slate-100 h-1.5 rounded-full overflow-hidden mb-2"><div class="bg-emerald-600 h-full w-full"></div></div>
                                <div class="flex justify-end"><span class="text-[9px] bg-emerald-50 text-emerald-700 px-1.5 py-0.5 rounded border border-emerald-100 font-bold uppercase">CALIFICADO: 4.8/5.0</span></div>
                            </div>
                            <div onclick="verDetalleCard('Alberto Moreno', 'Docente Investigador', 'Dpto. Matemáticas', 'DOC-051', 'Dpto. Ciencias Biológicas', 'B', 'AM', 100, '15 / 15', '6 / 6', 'CALIFICADO: 4.5 / 5.0')" class="bg-white border border-slate-200 rounded-lg p-3.5 shadow-sm hover:border-[#004037] cursor-pointer transition-all">
                                <div class="flex items-center gap-3 mb-2">
                                    <div class="w-8 h-8 rounded bg-blue-600 flex items-center justify-center text-white font-bold text-xs">AM</div>
                                    <div><h4 class="text-xs font-bold text-slate-800 leading-tight">Alberto Moreno</h4><p class="text-[10px] text-slate-400">Docente Investigador</p></div>
                                </div>
                                <p class="text-[10px] text-slate-500 mb-2">Dpto. Matemáticas</p>
                                <div class="w-full bg-slate-100 h-1.5 rounded-full overflow-hidden mb-2"><div class="bg-emerald-600 h-full w-full"></div></div>
                                <div class="flex justify-end"><span class="text-[9px] bg-emerald-50 text-emerald-700 px-1.5 py-0.5 rounded border border-emerald-100 font-bold uppercase">CALIFICADO: 4.5/5.0</span></div>
                            </div>
                            <div onclick="verDetalleCard('Elena Cuervo', 'Catedrático', 'Dpto. Idiomas', 'DOC-066', 'Dpto. Medicina Veterinaria', 'A', 'EC', 100, '15 / 15', '6 / 6', 'CALIFICADO: 4.9 / 5.0')" class="bg-white border border-slate-200 rounded-lg p-3.5 shadow-sm hover:border-[#004037] cursor-pointer transition-all">
                                <div class="flex items-center gap-3 mb-2">
                                    <div class="w-8 h-8 rounded bg-[#4DB3A2] flex items-center justify-center text-white font-bold text-xs">EC</div>
                                    <div><h4 class="text-xs font-bold text-slate-800 leading-tight">Elena Cuervo</h4><p class="text-[10px] text-slate-400">Catedrático</p></div>
                                </div>
                                <p class="text-[10px] text-slate-500 mb-2">Dpto. Idiomas</p>
                                <div class="w-full bg-slate-100 h-1.5 rounded-full overflow-hidden mb-2"><div class="bg-emerald-600 h-full w-full"></div></div>
                                <div class="flex justify-end"><span class="text-[9px] bg-emerald-50 text-emerald-700 px-1.5 py-0.5 rounded border border-emerald-100 font-bold uppercase">CALIFICADO: 4.9/5.0</span></div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <div class="w-full lg:w-[34%] bg-white border-t lg:border-t-0 lg:border-l border-slate-200 p-5 flex flex-col justify-between h-auto lg:h-full mt-6 lg:mt-0">
                <div>
                    <div class="text-center pb-3 border-b border-slate-100">
                        <div id="card-avatar" class="w-12 h-12 rounded-xl bg-[#004037] flex items-center justify-center text-white text-base font-bold mx-auto mb-2 shadow-xs">RM</div>
                        <h2 id="card-nombre" class="text-sm font-bold text-slate-800">Ricardo Mendoza</h2>
                        <p id="card-cargo" class="text-xs text-slate-400 font-medium">Docente Investigador</p>
                    </div>

                    <div class="mt-4 space-y-2.5 text-xs bg-slate-50 border border-slate-100 p-4 rounded-xl">
                        <div class="flex justify-between py-0.5 border-b border-slate-200/60"><span class="text-slate-400 font-bold text-[9px] uppercase">Departamento</span><span id="card-dep" class="font-bold text-slate-700">Dpto. Ciencias Biológicas</span></div>
                        <div class="flex justify-between py-0.5 border-b border-slate-200/60"><span class="text-slate-400 font-bold text-[9px] uppercase">Código Cargo</span><span id="card-codigo" class="font-bold text-slate-700">DOC-009</span></div>
                        <div class="flex justify-between py-0.5 border-b border-slate-200/60"><span class="text-slate-400 font-bold text-[9px] uppercase">Otro Departamento</span><span id="card-nat" class="font-bold text-slate-700">Dpto. Sistemas</span></div>
                        <div class="flex justify-between py-0.5"><span class="text-slate-400 font-bold text-[9px] uppercase">Estado</span><span id="card-estado-tag" class="font-bold text-amber-600 uppercase text-[10px]">PENDIENTE</span></div>
                    </div>

                    <div class="mt-4 bg-white border border-slate-200 p-4 rounded-xl shadow-xs">
                        <h3 class="text-xs font-bold text-slate-800 mb-2">Progreso de Concertación</h3>
                        <div class="flex flex-col items-center py-4 bg-slate-50/50 rounded-lg">
                            <div id="card-circulo" class="relative w-20 h-20 rounded-full flex items-center justify-center transition-all duration-300" style="background: radial-gradient(closest-side, white 79%, transparent 80% 100%), conic-gradient(#9E9E22 0%, #eceeec 0);">
                                <span id="card-porcentaje" class="text-base text-[#004037] font-black">0%</span>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-2 text-center mt-3">
                            <div class="bg-white border border-slate-100 p-2 rounded"><p class="text-[9px] text-slate-400 font-bold uppercase">Competencias</p><p id="card-comp" class="text-xs font-bold text-slate-700 mt-0.5">0 / 15</p></div>
                            <div class="bg-white border border-slate-100 p-2 rounded"><p class="text-[9px] text-slate-400 font-bold uppercase">Objetivos</p><p id="card-obj" class="text-xs font-bold text-slate-700 mt-0.5">0 / 6</p></div>
                        </div>
                    </div>
                </div>
                <button class="w-full py-2 bg-[#004037] text-white text-xs font-bold rounded-lg hover:bg-[#00594e] mt-4 shadow-sm">Auditar Expediente</button>
            </div>
        </div>

        <div id="vista-evaluaciones" class="hidden h-full w-full absolute inset-0 overflow-y-auto lg:overflow-hidden p-4 lg:p-6">
            <div class="w-full lg:w-[65%] flex flex-col h-auto lg:h-full overflow-visible lg:overflow-hidden lg:pr-4">
                <h1 class="text-base font-bold text-slate-800 mb-4 flex-shrink-0">Evaluaciones de Desempeño</h1>
                
                <div class="flex gap-2 mb-4 flex-shrink-0">
                    <div class="relative flex-1">
                        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">search</span>
                        <input class="w-full bg-white border border-slate-200 rounded-lg py-1.5 pl-9 pr-4 text-xs outline-none focus:border-[#004037]" placeholder="Buscar empleado..." type="text"/>
                    </div>
                    <button class="bg-white border border-slate-200 rounded-lg px-4 py-1.5 text-xs font-medium text-slate-600 flex items-center gap-1"><span class="material-symbols-outlined text-xs">filter_list</span> Filtrar</button>
                </div>

                <div class="bg-white border border-slate-200 rounded-xl overflow-x-auto lg:overflow-x-visible overflow-y-visible lg:overflow-y-auto flex-1 shadow-sm">
                    <table class="w-full text-left border-collapse min-w-[500px] lg:min-w-0">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 text-[10px] font-bold text-slate-400 uppercase tracking-wider sticky top-0 z-10">
                                <th class="p-3 pl-4">Empleado</th>
                                <th class="p-3">Nivel</th>
                                <th class="p-3 pr-4 text-right">Estado</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-xs">
                            <tr onclick="cargarDetalleEvaluado('Alejandro Morales', 'Rector Institucional', 'Consejo Superior', 'DIR-001', 'Libre Nombramiento', 'A', 'AM', 75, '12 / 15', '4 / 6')" class="hover:bg-slate-50 cursor-pointer transition-colors">
                                <td class="p-3 pl-4 flex items-center gap-3">
                                    <div class="w-7 h-7 rounded-full bg-[#004037] flex items-center justify-center text-white font-bold text-[10px]">AM</div>
                                    <span class="font-bold text-slate-800">Alejandro Morales</span>
                                </td>
                                <td class="p-3 text-slate-500">Directivo</td>
                                <td class="p-3 pr-4 text-right"><span class="bg-[#00594e] text-white px-2.5 py-0.5 rounded-full text-[9px] font-bold uppercase">En proceso</span></td>
                            </tr>
                            <tr onclick="cargarDetalleEvaluado('Elena Castañeda', 'Docente de Planta', 'Decanatura', 'DOC-023', 'Carrera', 'B', 'EC', 0, '0 / 15', '0 / 6')" class="hover:bg-slate-50 cursor-pointer transition-colors">
                                <td class="p-3 pl-4 flex items-center gap-3">
                                    <div class="w-7 h-7 rounded-full bg-slate-400 flex items-center justify-center text-white font-bold text-[10px]">EC</div>
                                    <span class="font-bold text-slate-800">Elena Castañeda</span>
                                </td>
                                <td class="p-3 text-slate-500">Docente</td>
                                <td class="p-3 pr-4 text-right"><span class="bg-slate-200 text-slate-600 px-2.5 py-0.5 rounded-full text-[9px] font-bold uppercase">Pendiente</span></td>
                            </tr>
                            <tr onclick="cargarDetalleEvaluado('Ricardo Gómez', 'Asistencial', 'Servicios Generales', 'ASIS-012', 'Planta', 'C', 'RG', 100, '15 / 15', '6 / 6')" class="hover:bg-slate-50 cursor-pointer transition-colors">
                                <td class="p-3 pl-4 flex items-center gap-3">
                                    <div class="w-7 h-7 rounded-full bg-blue-600 flex items-center justify-center text-white font-bold text-[10px]">RG</div>
                                    <span class="font-bold text-slate-800">Ricardo Gómez</span>
                                </td>
                                <td class="p-3 text-slate-500">Asistencial</td>
                                <td class="p-3 pr-4 text-right"><span class="bg-blue-100 text-blue-700 px-2.5 py-0.5 rounded-full text-[9px] font-bold uppercase">Completado</span></td>
                            </tr>
                            <tr onclick="cargarDetalleEvaluado('Sandra Milena Ortiz', 'Profesional', 'Oficina Jurídica', 'PROF-004', 'Libre Nombramiento', 'A', 'SO', 40, '6 / 15', '2 / 6')" class="hover:bg-slate-50 cursor-pointer transition-colors">
                                <td class="p-3 pl-4 flex items-center gap-3">
                                    <div class="w-7 h-7 rounded-full bg-[#004037] flex items-center justify-center text-white font-bold text-[10px]">SO</div>
                                    <span class="font-bold text-slate-800">Sandra Milena Ortiz</span>
                                </td>
                                <td class="p-3 text-slate-500">Profesional</td>
                                <td class="p-3 pr-4 text-right"><span class="bg-[#00594e] text-white px-2.5 py-0.5 rounded-full text-[9px] font-bold uppercase">En proceso</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="w-full lg:w-[35%] bg-white border-t lg:border-t-0 lg:border-l border-slate-200 p-5 flex flex-col justify-between h-auto lg:h-full mt-6 lg:mt-0">
                <div>
                    <div class="text-center pb-4 border-b border-slate-100">
                        <div id="eval-avatar" class="w-14 h-14 rounded-xl bg-[#004037] flex items-center justify-center text-white text-lg font-bold mx-auto mb-2 shadow-xs">AM</div>
                        <h2 id="eval-nombre" class="text-sm font-bold text-slate-800">Alejandro Morales</h2>
                        <p id="eval-cargo" class="text-xs text-slate-400 font-medium">Rector Institucional</p>
                    </div>

                    <div class="mt-4 space-y-3 text-xs bg-white border border-slate-200 p-4 rounded-xl">
                        <div class="flex justify-between py-1 border-b border-slate-100/50"><span class="text-slate-400 font-bold text-[9px] uppercase">Evaluador</span><span id="eval-evaluador" class="font-bold text-slate-700">Consejo Superior</span></div>
                        <div class="flex justify-between py-1 border-b border-slate-100/50"><span class="text-slate-400 font-bold text-[9px] uppercase">Cargo Código</span><span id="eval-codigo" class="font-bold text-slate-700">DIR-001</span></div>
                        <div class="flex justify-between py-1 border-b border-slate-100/50"><span class="text-slate-400 font-bold text-[9px] uppercase">Naturaleza</span><span id="eval-naturaleza" class="font-bold text-slate-700">Libre Nombramiento</span></div>
                        <div class="flex justify-between py-1"><span class="text-slate-400 font-bold text-[9px] uppercase">Grado</span><span id="eval-grado" class="font-bold text-slate-700">A</span></div>
                    </div>

                    <div class="mt-4 bg-white border border-slate-200 p-4 rounded-xl shadow-xs">
                        <h3 class="text-xs font-bold text-slate-800 mb-2">Estado de evaluación</h3>
                        <div class="flex flex-col items-center py-4 bg-slate-50/50 rounded-xl border border-slate-100">
                            <div id="eval-circulo" class="relative w-24 h-24 rounded-full flex items-center justify-center transition-all duration-300" style="background: radial-gradient(closest-side, white 79%, transparent 80% 100%), conic-gradient(#00594e 75%, #eceeec 0);">
                                <div class="flex flex-col items-center">
                                    <span id="eval-porcentaje" class="text-xl text-[#004037] font-black">75%</span>
                                    <span class="text-[8px] text-slate-400 font-bold uppercase tracking-widest">Avance</span>
                                </div>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-2 text-center mt-3">
                            <div class="bg-white border border-slate-100 p-2 rounded-lg"><p class="text-[9px] text-slate-400 font-bold uppercase">Competencias</p><p id="eval-competencias" class="text-xs font-bold text-slate-700 mt-0.5">12 / 15</p></div>
                            <div class="bg-white border border-slate-100 p-2 rounded-lg"><p class="text-[9px] text-slate-400 font-bold uppercase">Objetivos</p><p id="eval-objetivos" class="text-xs font-bold text-slate-700 mt-0.5">4 / 6</p></div>
                        </div>
                    </div>
                </div>
                <div class="space-y-2 mt-4">
                    <button class="w-full py-2 bg-[#004037] text-white text-xs font-bold rounded-lg hover:bg-[#00594e] shadow-sm">Ver evaluación completa</button>
                    <button class="w-full py-2 bg-white text-slate-700 border border-slate-200 text-xs font-bold rounded-lg hover:bg-slate-50">Plan de mejoramiento</button>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar-menu');
        const overlay = document.getElementById('sidebar-overlay');
        const isOpen = !sidebar.classList.contains('-translate-x-full');
        
        if (isOpen) {
            sidebar.classList.add('-translate-x-full');
            overlay.classList.add('hidden');
        } else {
            sidebar.classList.remove('-translate-x-full');
            overlay.classList.remove('hidden');
        }
    }

    function navegarMenu(seccion) {
        const vDashboard = document.getElementById('vista-dashboard');
        const vEmployees = document.getElementById('vista-employees');
        const vEvaluaciones = document.getElementById('vista-evaluaciones');
        
        const lDashboard = document.getElementById('link-dashboard');
        const lEmployees = document.getElementById('link-employees');
        const lEvaluaciones = document.getElementById('link-evaluaciones');

        [lDashboard, lEmployees, lEvaluaciones].forEach(link => {
            link.className = "flex items-center gap-3 px-4 py-2.5 text-slate-600 hover:bg-slate-50 rounded-lg text-xs font-medium cursor-pointer transition-all";
        });

        vDashboard.className = "hidden p-4 lg:p-6 h-full w-full absolute inset-0 overflow-y-auto";
        vEmployees.className = "hidden p-4 lg:p-6 h-full w-full overflow-y-auto lg:overflow-hidden absolute inset-0";
        vEvaluaciones.className = "hidden h-full w-full absolute inset-0 overflow-y-auto lg:overflow-hidden";

        if (seccion === 'dashboard') {
            vDashboard.classList.remove('hidden');
            vDashboard.classList.add('block');
            lDashboard.className = "flex items-center gap-3 px-4 py-2.5 text-[#004037] font-bold bg-[#E6F2F0] rounded-lg text-xs cursor-pointer transition-all";
        } else if (seccion === 'employees') {
            vEmployees.classList.remove('hidden');
            vEmployees.classList.add('flex', 'flex-col', 'lg:flex-row');
            lEmployees.className = "flex items-center gap-3 px-4 py-2.5 text-[#004037] font-bold bg-[#E6F2F0] rounded-lg text-xs cursor-pointer transition-all";
        } else if (seccion === 'evaluaciones') {
            vEvaluaciones.classList.remove('hidden');
            vEvaluaciones.classList.add('flex', 'flex-col', 'lg:flex-row');
            lEvaluaciones.className = "flex items-center gap-3 px-4 py-2.5 text-[#004037] font-bold bg-[#E6F2F0] rounded-lg text-xs cursor-pointer transition-all";
        }

        // Auto close sidebar on mobile menu selection
        const sidebar = document.getElementById('sidebar-menu');
        if (sidebar && !sidebar.classList.contains('-translate-x-full') && window.innerWidth < 1024) {
            toggleSidebar();
        }
    }

    function verDetalleCard(nombre, cargo, dep, codigo, nat, grado, iniciales, avance, comp, obj, estadoTag) {
        document.getElementById('card-nombre').innerText = nombre;
        document.getElementById('card-cargo').innerText = cargo;
        document.getElementById('card-dep').innerText = dep;
        document.getElementById('card-codigo').innerText = codigo;
        document.getElementById('card-nat').innerText = nat;
        document.getElementById('card-avatar').innerText = iniciales;
        document.getElementById('card-porcentaje').innerText = avance + '%';
        document.getElementById('card-comp').innerText = comp;
        document.getElementById('card-obj').innerText = obj;
        
        const tag = document.getElementById('card-estado-tag');
        tag.innerText = estadoTag;

        let colorCirculo = '#00594e'; 
        tag.className = "font-bold uppercase text-[10px] text-blue-600";
        if (avance === 0) { colorCirculo = '#9E9E22'; tag.className = "font-bold uppercase text-[10px] text-[#9E9E22]"; }
        if (avance === 100) { colorCirculo = '#10b981'; tag.className = "font-bold uppercase text-[10px] text-emerald-600"; }

        const circulo = document.getElementById('card-circulo');
        circulo.style.background = `radial-gradient(closest-side, white 79%, transparent 80% 100%), conic-gradient(${colorCirculo} ${avance}%, #eceeec 0)`;
    }

    function cargarDetalleEvaluado(nombre, cargo, evaluador, codigo, naturaleza, grado, iniciales, avance, competencias, objetivos) {
        document.getElementById('eval-nombre').innerText = nombre;
        document.getElementById('eval-cargo').innerText = cargo;
        document.getElementById('eval-evaluador').innerText = evaluador;
        document.getElementById('eval-codigo').innerText = codigo;
        document.getElementById('eval-naturaleza').innerText = naturaleza;
        document.getElementById('eval-grado').innerText = grado;
        document.getElementById('eval-avatar').innerText = iniciales;
        document.getElementById('eval-porcentaje').innerText = avance + '%';
        document.getElementById('eval-competencias').innerText = competencias;
        document.getElementById('eval-objetivos').innerText = objetivos;

        let colorCirculo = '#00594e';
        if (avance === 0) colorCirculo = '#9E9E22';
        if (avance === 100) colorCirculo = '#10b981';

        const circulo = document.getElementById('eval-circulo');
        circulo.style.background = `radial-gradient(closest-side, white 79%, transparent 80% 100%), conic-gradient(${colorCirculo} ${avance}%, #eceeec 0)`;
    }
</script>
@endsection