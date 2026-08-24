@extends('layouts.app')

@section('content')
@include('partials.dashboard-styles')

<div class="panel-shell">
    @include('partials.header')

    <div class="flex min-h-0 overflow-hidden">
        @include('partials.sidebar')

        <main class="flex-1 min-w-0 p-4 sm:p-6 lg:p-8 overflow-y-auto">
            @include('partials.alerts')

            <section id="section-evaluaciones" class="section-content space-y-6">
                <div class="grid gap-6 lg:grid-cols-[0.8fr_1.2fr]">
                    <div class="panel-card rounded-3xl p-6 h-fit">
                        <h2 class="text-xl font-black text-slate-900 mb-4">Mis evaluaciones</h2>
                        <div class="space-y-3">
                            @forelse($evaluacionesEvaluado as $ev)
                                <div class="evaluacion-card p-4 rounded-2xl border border-slate-200 bg-white cursor-pointer hover:border-[#00594E] transition" onclick="abrirConcertacionEvaluado(this, @js($ev))">
                                    <div class="flex items-center gap-2">
                                        <h4 class="font-bold text-slate-900 text-sm leading-snug">{{ $ev->tipo_nombre }}</h4>
                                        @if($ev->es_traslado)
                                            <span class="inline-flex items-center gap-1 text-[10px] font-bold uppercase px-2 py-0.5 rounded-full bg-slate-200 text-slate-600" title="Evaluación bloqueada por traslado">
                                                <span class="material-symbols-outlined text-[13px]">swap_horiz</span>
                                                Traslado
                                            </span>
                                        @endif
                                        @if($ev->tipo_nombre === 'PARCIAL')
                                            <span class="inline-flex items-center gap-1 text-[10px] font-bold uppercase px-2 py-0.5 rounded-full bg-sky-100 text-sky-700" title="Evaluación correspondiente a un tramo parcial">
                                                <span class="material-symbols-outlined text-[13px]">timelapse</span>
                                                Parcial
                                            </span>
                                        @endif
                                        @if($ev->id_vinc_suplente)
                                            <span class="inline-flex items-center gap-1 text-[10px] font-bold uppercase px-2 py-0.5 rounded-full bg-[#B5A160]/20 text-[#8a7b3c]" title="Evaluación atendida temporalmente por delegación">
                                                <span class="material-symbols-outlined text-[13px]">assignment_ind</span>
                                                Delegación
                                            </span>
                                        @endif
                                        @if(isset($ev->tiene_extratiempo) && $ev->tiene_extratiempo)
                                            <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded-full bg-red-100 text-red-700 border border-red-200">Extratiempo</span>
                                        @endif
                                    </div>
                                    @if($ev->es_traslado)
                                        <p class="mt-1 text-[10px] font-semibold text-slate-600">Traslado registrado: esta evaluación es solo de consulta.</p>
                                    @endif
                                    @if($ev->tipo_nombre === 'PARCIAL')
                                        <p class="mt-1 text-[10px] font-semibold text-sky-700">Evaluación parcial por tramo de servicio{{ $ev->referencia ? ': ' . $ev->referencia : '.' }}</p>
                                    @endif
                                    @if($ev->tipo_nombre === 'PARCIAL' && $ev->referencia)
                                        <p class="text-[10px] font-semibold text-[#00594E] mt-1">{{ $ev->referencia }}</p>
                                    @endif
                                    <p class="text-xs text-slate-500 mt-0.5">Quién lo evaluó: {{ $ev->evaluador_nombres ?? 'Mi Evaluador' }} {{ $ev->evaluador_apellidos ?? '' }}@if($ev->id_vinc_suplente) (en delegación de {{ $ev->suplente_nombres }} {{ $ev->suplente_apellidos }})@endif</p>
                                    <div class="mt-2 space-y-0.5 text-[10px] text-slate-500">
                                        <p><span class="font-semibold text-slate-600">Período de evaluación:</span> {{ $ev->anio }}-{{ (int) $ev->semestre === 1 ? 'A' : 'B' }}</p>
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
                                <p id="concertacion-evaluado-evaluador" class="text-xs text-slate-500">Quién lo evaluó: -</p>
                            </div>
                            <div class="text-right flex flex-col items-end">
                                <div class="text-xs text-slate-500">Progreso Ponderación</div>
                                <div id="compromisos-suma-peso-evaluado" class="text-xl font-black text-[#00594E]">0% / 80%</div>
                                <div id="compromisos-contador-evaluado" class="text-[10px] text-slate-400 font-bold mt-0.5">0 compromisos</div>
                            </div>
                        </div>

                        <div id="aviso-traslado-evaluado" class="hidden mt-4 flex items-start gap-2 rounded-2xl border border-slate-200 bg-slate-100 p-3 text-xs font-semibold text-slate-600">
                            <span class="material-symbols-outlined text-base shrink-0">lock</span>
                            <span>Esta evaluación quedó bloqueada por traslado. Solo puedes consultarla; no se puede modificar.</span>
                        </div>

                        <!-- Tabs del evaluado -->
                        <div class="mt-5 flex flex-wrap gap-2">
                            <button type="button" id="tabbtn-evaluado-compromisos" onclick="cambiarTabEvaluado('compromisos')" class="evaluado-tab-btn active">Compromisos</button>
                            <button type="button" id="tabbtn-evaluado-competencias" onclick="cambiarTabEvaluado('competencias')" class="evaluado-tab-btn">Competencias</button>
                            <button type="button" id="tabbtn-evaluado-ejes" onclick="cambiarTabEvaluado('ejes')" class="evaluado-tab-btn hidden">Ejes misionales</button>
                            <button type="button" id="tabbtn-evaluado-recursos" onclick="cambiarTabEvaluado('recursos')" class="evaluado-tab-btn hidden">Recursos</button>
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
                                    Compromisos acordados
                                </h4>
                                <div id="compromisos-lista-evaluado" class="space-y-3"></div>
                            </div>

                            <div id="resultado-calculo-evaluado" class="hidden mt-6 space-y-3"></div>

                            <div id="notificacion-evaluado-seccion" class="hidden mt-6 pt-4 border-t border-slate-100 space-y-3">
                                <div class="flex items-center justify-between gap-3">
                                    <h4 class="text-sm font-bold text-slate-800 flex items-center gap-2">
                                        <span class="material-symbols-outlined text-base">how_to_reg</span>
                                        Notificación de la calificación
                                    </h4>
                                    <span id="notificacion-estado-evaluado" class="text-[10px] font-bold uppercase rounded-full px-2.5 py-1 bg-amber-50 text-amber-700 hidden">Pendiente</span>
                                </div>
                                <p class="text-[10px] text-slate-400 font-semibold">Debes dejar constancia de que conoces la calificación. Si renuncias a firmar, se registra la renuencia con un testigo institucional y el acta digitalizada en PDF.</p>
                                <div id="notificacion-detalle-evaluado" class="hidden space-y-1"></div>
                                <div id="notificacion-acciones-evaluado" class="hidden flex flex-wrap items-center gap-3">
                                    <button type="button" id="btn-firmar-notificacion-evaluado" onclick="firmarNotificacionEvaluado(event)" class="bg-[#00594E] text-white px-5 py-2.5 rounded-xl text-xs font-bold hover:brightness-110 transition">Firmar notificación</button>
                                    <button type="button" id="btn-abrir-renuncia-evaluado" onclick="toggleFormRenuenciaEvaluado()" class="text-[11px] font-bold text-red-600 hover:underline flex items-center gap-1">
                                        <span class="material-symbols-outlined text-sm">cancel</span> Renunciar a firmar
                                    </button>
                                </div>
                                <form id="form-renuncia-evaluado" onsubmit="registrarRenuenciaEvaluado(event)" class="hidden grid gap-3 rounded-2xl border border-red-100 bg-red-50/40 p-4">
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-600 uppercase mb-1">Testigos <span class="text-red-600 font-bold">*</span></label>
                                        <p class="text-[10px] text-slate-400 mb-1.5">Registra al menos un testigo institucional que acompañe la renuencia.</p>
                                        <div id="renuncia-testigos-lista-evaluado" class="space-y-2"></div>
                                        <button type="button" onclick="agregarTestigoRenuencia()" class="mt-2 text-[11px] font-bold text-[#00594E] hover:underline flex items-center gap-1">
                                            <span class="material-symbols-outlined text-sm">person_add</span> Agregar testigo
                                        </button>
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-600 uppercase mb-1">Evidencia — acta digitalizada en PDF <span class="text-red-600 font-bold">*</span></label>
                                        <p class="text-[10px] text-slate-400 mb-1.5">Adjunta el enlace (link) del acta física de renuencia escaneada en PDF.</p>
                                        <div id="renuncia-evidencias-lista-evaluado" class="space-y-2"></div>
                                        <button type="button" onclick="agregarEvidenciaRenuencia()" class="mt-2 text-[11px] font-bold text-[#00594E] hover:underline flex items-center gap-1">
                                            <span class="material-symbols-outlined text-sm">add_link</span> Agregar enlace del acta
                                        </button>
                                    </div>
                                    <div class="flex items-center justify-between gap-3">
                                        <span id="renuncia-mensaje-evaluado" class="hidden text-xs font-semibold"></span>
                                        <button type="submit" class="bg-red-600 text-white px-4 py-2 rounded-xl text-xs font-bold hover:brightness-110 transition ml-auto">Registrar renuencia con testigos</button>
                                    </div>
                                </form>
                            </div>

                            <div id="firma-evaluado-seccion" class="mt-6 pt-4 border-t border-slate-100 space-y-3">
                                <div id="seccion-firmar-evaluado" class="flex items-center justify-between gap-4">
                                    <div class="text-xs text-slate-500 leading-tight">Podrás firmar cuando el evaluador haya firmado la concertación.</div>
                                    <form id="form-firmar-evaluado" method="POST" action="" onsubmit="firmarConcertacion(event, 'evaluado')" class="shrink-0">
                                        @csrf
                                        <button type="submit" id="btn-firmar-evaluado" class="bg-[#00594E] text-white px-6 py-2.5 rounded-xl text-sm font-bold hover:brightness-110 transition disabled:opacity-50" disabled>Firmar Concertación</button>
                                        <button type="button" id="btn-recusacion-evaluado" onclick="mostrarModalRecusacion()" class="bg-red-600 text-white px-4 py-2.5 rounded-xl text-sm font-bold hover:bg-red-700 transition ml-2 hidden">Declarar Recusación</button>
                                    </form>
                                </div>
                                <div id="firmas-concertacion-evaluado" class="mt-3 hidden"></div>
                            </div>
                            
                            <!-- Bloque: Desacuerdo durante la concertación -->
                            <div id="bloque-desacuerdo-evaluado" class="mt-6 pt-4 border-t border-slate-100 hidden">
                                <h4 class="text-sm font-bold text-slate-800 flex items-center gap-2 mb-2">Desacuerdo con la concertación</h4>
                                <form id="form-desacuerdo-evaluacion" method="POST" action="" class="space-y-3">
                                    @csrf
                                    <p class="text-[11px] text-slate-500">Disponible mientras la concertación esté pendiente de firma. Una vez firmada o calificada, podrás usar el trámite de recursos cuando corresponda.</p>
                                    <textarea name="desacuerdo" class="w-full text-xs rounded-xl border border-slate-200 p-2.5 bg-white" placeholder="Escribe los motivos de tu desacuerdo con los compromisos propuestos..." required></textarea>
                                    <button type="submit" class="bg-amber-600 text-white px-4 py-2 rounded-xl text-xs font-bold w-full md:w-auto">Enviar desacuerdo al evaluador</button>
                                </form>
                            </div>
                            
                            <!-- Modal: Recusación -->
                            <div id="modal-recusacion" class="hidden mt-4 bg-red-50 p-4 border border-red-200 rounded-xl">
                                <h4 class="font-bold text-red-800 text-sm mb-2">Declarar Recusación contra el Evaluador</h4>
                                <form method="POST" action="" id="form-recusacion-accion" class="space-y-3">
                                    @csrf
                                    <input type="hidden" name="tipo" value="RECUSACION">
                                    <div><label class="text-[10px] font-bold text-red-700 uppercase">Motivo y Evidencia (Justificación)</label><textarea name="motivo" class="w-full text-xs p-2 rounded border" required></textarea></div>
                                    <button type="submit" class="bg-red-700 text-white px-4 py-2 rounded-xl text-xs font-bold w-full">Radicar en Talento Humano</button>
                                </form>
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
                            <div id="bloque-recursos-evaluado" class="space-y-3">
                                <div class="flex items-center justify-between gap-3">
                                    <h4 class="text-sm font-bold text-slate-800 flex items-center gap-2">
                                        <span class="material-symbols-outlined text-base">gavel</span>
                                        Recursos: reposición y apelación
                                    </h4>
                                    <span id="recursos-contador-evaluado" class="text-[10px] font-bold rounded-full px-2.5 py-1 bg-slate-100 text-slate-500">0</span>
                                </div>
                                <p class="text-[10px] text-slate-400 font-semibold">Reposición: revisa el mismo evaluador. Apelación: conoce el superior jerárquico del evaluador.</p>
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
                                    <div id="recurso-evidencias-bloque-evaluado">
                                        <label class="block text-[10px] font-bold text-slate-600 uppercase mb-1">Evidencias (links) <span class="text-red-600 font-bold">* (Obligatorio)</span></label>
                                        <div id="recurso-evidencias-lista-evaluado" class="space-y-2"></div>
                                        <button type="button" onclick="agregarEvidenciaRecurso()" class="mt-2 text-[11px] font-bold text-[#00594E] hover:underline flex items-center gap-1">
                                            <span class="material-symbols-outlined text-sm">add_link</span> Agregar evidencia
                                        </button>
                                    </div>
                                    <div class="flex items-center justify-between gap-3">
                                        <span id="recurso-mensaje-evaluado" class="hidden text-xs font-semibold"></span>
                                        <button type="submit" class="bg-[#00594E] text-white px-4 py-2 rounded-xl text-xs font-bold hover:brightness-110 transition ml-auto">Radicar recurso</button>
                                    </div>
                                </form>
                                <div id="recursos-lista-evaluado" class="space-y-2"></div>
                            </div>

                            <!-- Plan de mejoramiento condicionado - evaluado -->
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
                    </div>

                    <div id="panel-concertacion-evaluado-empty" class="panel-card rounded-3xl p-8 flex flex-col items-center justify-center text-center text-slate-400">
                        <span class="material-symbols-outlined text-5xl text-slate-300 mb-3">assignment</span>
                        <p class="text-sm">Selecciona una Evaluación de la lista de la izquierda para ver el estado de la concertación.</p>
                    </div>
                </div>
            </section>

            <section id="section-reportes" class="section-content hidden space-y-6">
                <div class="panel-card rounded-3xl p-6">
                    <div class="mb-4">
                        <p class="text-xs font-bold uppercase tracking-[0.22em] text-[#00594E]">Exportar PDF</p>
                        <h2 class="text-xl font-black text-slate-900 mt-1">Informes institucionales de evaluación</h2>
                        <p class="text-xs text-slate-500 mt-1">Descarga los informes oficiales en PDF de tus evaluaciones ya calificadas.</p>
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
                                                    @if($ev->tiene_informe_anual)
                                                        <a href="/evaluaciones/{{ $ev->id_evaluacion }}/informe-anual" class="inline-flex items-center gap-1.5 rounded-lg bg-[#B5A160] text-white px-3 py-1.5 text-[11px] font-bold hover:brightness-110 transition">
                                                            <span class="material-symbols-outlined text-sm">picture_as_pdf</span> PDF anual
                                                        </a>
                                                    @endif
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
@vite('resources/js/dashboards/evaluado.js')
@endsection
