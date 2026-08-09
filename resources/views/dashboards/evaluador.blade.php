@extends('layouts.app')

@section('content')
@include('partials.dashboard-styles')

<div class="panel-shell">
    @include('partials.header')

    <div class="flex min-h-0 overflow-hidden">
        @include('partials.sidebar')

        <main class="flex-1 min-w-0 p-4 sm:p-6 lg:p-8 overflow-y-auto">
            @include('partials.alerts')

            <!-- SECTION: USUARIOS EVALUADOR (Personas a cargo) -->
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
                                    <select name="tipo_evaluacion" id="apertura-ciclo-select" onchange="toggleAperturaDiasLaborados()" class="w-full text-xs rounded-xl border border-slate-200 p-2.5 bg-white" required>
                                        <option value="SEMESTRE_1">Primer Semestre</option>
                                        <option value="SEMESTRE_2">Segundo Semestre</option>
                                        <option value="PARCIAL" id="apertura-opcion-parcial">Parcial</option>
                                    </select>
                                </div>
                                <div id="apertura-dias-laborados-wrap" class="hidden">
                                    <label class="block text-[10px] font-bold text-slate-600 uppercase mb-1">Días laborados</label>
                                    <input type="number" name="dias_laborados" class="w-full text-xs rounded-xl border border-slate-200 p-2.5 bg-white" placeholder="Opcional" />
                                </div>
                                <div id="apertura-referencia-wrap" class="hidden">
                                    <label class="block text-[10px] font-bold text-slate-600 uppercase mb-1">Nombre o referencia</label>
                                    <input type="text" name="referencia" id="apertura-referencia-input" class="w-full text-xs rounded-xl border border-slate-200 p-2.5 bg-white" placeholder="Identifica el funcionario asociado al periodo parcial" />
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

            <!-- SECTION: EVALUACIONES EVALUADOR -->
            <section id="section-evaluaciones-evaluador" class="section-content space-y-6">
                @if($planesPendientesEvaluador->isNotEmpty())
                <div class="panel-card rounded-3xl p-5 border-amber-200 border">
                    <div class="flex items-start gap-3">
                        <span class="material-symbols-outlined text-amber-600 mt-0.5">warning</span>
                        <div class="min-w-0">
                            <p class="text-sm font-black text-slate-800">Acción requerida: planes de mejoramiento pendientes</p>
                            <p class="text-xs text-slate-500 mt-1">Tienes <b>{{ $planesPendientesEvaluador->count() }}</b> evaluación(es) con plan de mejoramiento sin concertar ni firmar:</p>
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
                                            <div class="flex items-center gap-2">
                                                <h4 class="font-bold text-slate-900 text-sm leading-snug">{{ $ev->evaluado_nombres }} {{ $ev->evaluado_apellidos }}</h4>
                                                @if($ev->es_traslado)
                                                    <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded-full bg-slate-200 text-slate-600">Traslado</span>
                                                @endif
                                            </div>
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

                            <div id="aviso-traslado-evaluador" class="hidden mt-4 flex items-start gap-2 rounded-2xl border border-slate-200 bg-slate-100 p-3 text-xs font-semibold text-slate-600">
                                <span class="material-symbols-outlined text-base shrink-0">lock</span>
                                <span>Esta evaluación quedó bloqueada por traslado. Solo puedes consultarla; no se puede modificar ni calificar.</span>
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
                            </div>

                            <div id="tab-evaluador-recursos" class="evaluador-tab-panel hidden">
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

                            <!-- Plan de mejoramiento condicionado (evaluador) -->
                            <div id="bloque-plan-mejoramiento-evaluador" class="my-6 pt-4 border-t border-slate-100 space-y-3 hidden">
                                <div class="flex items-center justify-between gap-3">
                                    <h4 class="text-sm font-bold text-slate-800 flex items-center gap-2">
                                        <span class="material-symbols-outlined text-base">trending_up</span>
                                        Plan de mejoramiento
                                    </h4>
                                    <span id="plan-estado-evaluador" class="text-[10px] font-bold uppercase rounded-full px-2.5 py-1 bg-amber-50 text-amber-700 hidden">Pendiente</span>
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
                            <p class="text-sm">Selecciona una Evaluación de la lista para revisar la concertación.</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- SECTION: INSTANCIA EXTERNA (academic components) -->
            @include('partials.evaluador.instancia-externa')
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
@vite('resources/js/dashboards/evaluador.js')
@endsection
