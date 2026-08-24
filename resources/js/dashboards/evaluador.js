/**
 * Evaluador & Instancia Externa Dashboard JS Module
 */
import { escapeHtml, fetchJson, parseErrorMessage, showInlineMessage, navegarMenu, renderResultado } from './common.js';

let selectedEvaluacionId = null;
let selectedEstadoEvaluacion = null;
let selectedEvaluacionData = null;
let selectedEvaluacionEjes = {};
let selectedPlanData = null;
let selectedEvalExternaId = null;
let compromisosActuales = [];

const EJE_LABELS = {
    DOCENCIA: 'Docencia',
    INVESTIGACION: 'Investigación',
    PROYECCION_SOCIAL: 'Proyección Social',
};

export function badgeDecisionRecurso(decision) {
    if (decision === 'PENDIENTE') return '<span class="text-[10px] font-bold uppercase rounded-full px-2.5 py-1 bg-amber-50 text-amber-700">Pendiente</span>';
    if (decision === 'APROBADO') return '<span class="text-[10px] font-bold uppercase rounded-full px-2.5 py-1 bg-emerald-50 text-emerald-700">Aprobado</span>';
    return '<span class="text-[10px] font-bold uppercase rounded-full px-2.5 py-1 bg-red-50 text-red-600">Negado</span>';
}

export function badgeEstadoAprobacion(estado) {
    const label = estado || 'PENDIENTE';
    const cls = label === 'APROBADA' ? 'bg-[#EAF2EF] text-[#00594E]' : (label === 'RECHAZADA' ? 'bg-red-50 text-red-600' : 'bg-amber-50 text-amber-700');
    return `<span class="inline-block mt-1 text-[9px] font-bold uppercase px-2 py-0.5 rounded-full ${cls}">${label}</span>`;
}

export function calcularObjetivoCompromisos(ev, ejes = {}) {
    const ponderacionesConfig = window.APP_CONFIG?.ponderacionesConfig || {};
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

export function actualizarPeriodoSeleccionado(persona) {
    if (!persona) return;
    const periodosDisponibles = window.APP_CONFIG?.periodosDisponibles || [];
    const sistema = String(persona.sistema_evaluacion || '').trim().toUpperCase();
    const cicloSelect = document.getElementById('apertura-ciclo-select');
    const tipo = cicloSelect ? cicloSelect.value : 'SEMESTRE_1';

    let semestreTarget = 1;
    if (tipo === 'SEMESTRE_2') semestreTarget = 2;

    let periodo = null;
    if (tipo === 'SEMESTRE_1' || tipo === 'SEMESTRE_2') {
        periodo = periodosDisponibles.find(p => p.estado === 'ABIERTO' && String(p.sistema || '').trim().toUpperCase() === sistema && Number(p.semestre) === semestreTarget);
    } else {
        periodo = periodosDisponibles.find(p => p.estado === 'ABIERTO' && String(p.sistema || '').trim().toUpperCase() === sistema);
    }

    const aperturaIdPeriodo = document.getElementById('apertura-id-periodo');
    const aperturaPeriodo = document.getElementById('apertura-periodo');
    const aperturaVigencia = document.getElementById('apertura-vigencia');
    const aperturaCiclo = document.getElementById('apertura-ciclo');
    const aperturaAviso = document.getElementById('apertura-aviso-periodo');

    if (periodo) {
        if (aperturaIdPeriodo) aperturaIdPeriodo.value = periodo.id_periodo;
        if (aperturaPeriodo) aperturaPeriodo.innerText = `${periodo.sistema} (${periodo.anio}-${Number(periodo.semestre) === 1 ? 'A' : 'B'})`;
        if (aperturaVigencia) aperturaVigencia.innerText = `${periodo.fecha_inicio || '-'} a ${periodo.fecha_fin || '-'}`;
        if (aperturaCiclo && cicloSelect) aperturaCiclo.innerText = cicloSelect.options[cicloSelect.selectedIndex].text;
        if (aperturaAviso) aperturaAviso.innerText = 'El periodo se asigna automáticamente según el tipo de acuerdo y ciclo.';
    } else {
        if (aperturaIdPeriodo) aperturaIdPeriodo.value = '';
        if (aperturaPeriodo) aperturaPeriodo.innerText = 'No hay periodo abierto para este sistema y ciclo';
        if (aperturaVigencia) aperturaVigencia.innerText = '-';
        if (aperturaCiclo && cicloSelect) aperturaCiclo.innerText = cicloSelect.options[cicloSelect.selectedIndex].text;
        if (aperturaAviso) aperturaAviso.innerText = 'No hay un periodo abierto activo para este ciclo. Pide al administrador que abra el periodo.';
    }
}

export function seleccionarPersonaEvaluador(card, persona) {
    selectedEvaluacionData = persona;
    const nombreCompleto = `${persona.nombres || ''} ${persona.apellidos || ''}`.trim();
    const sistema = String(persona.sistema_evaluacion || '').trim().toUpperCase();

    const setText = (id, value) => {
        const node = document.getElementById(id);
        if (node) node.innerText = value;
    };
    const panel = document.getElementById('panel-apertura-evaluacion');
    if (panel) panel.classList.remove('hidden');
    setText('empleado-avatar', ((persona.nombres?.[0] || '') + (persona.apellidos?.[0] || '')).toUpperCase() || '--');
    setText('empleado-nombre', nombreCompleto || 'Selecciona una persona');
    setText('empleado-cargo', `${persona.cargo || 'Sin cargo'} - ${persona.area || 'Sin área'}`);
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
    const cicloSelect = document.getElementById('apertura-ciclo-select');
    const aperturaEjes = document.getElementById('apertura-ejes-misionales');
    const aperturaEjeInv = document.getElementById('apertura-eje-investigacion');
    const aperturaEjeProy = document.getElementById('apertura-eje-proyeccion');

    if (aperturaIdVinc) aperturaIdVinc.value = persona.id_vinculacion || '';
    if (cicloSelect) cicloSelect.value = 'SEMESTRE_1';
    if (cicloSelect) {
        const opcionParcial = cicloSelect.querySelector('option[value="PARCIAL"]');
        if (persona.tiene_periodo_parcial && !opcionParcial) {
            const nuevaOpcionParcial = document.createElement('option');
            nuevaOpcionParcial.value = 'PARCIAL';
            nuevaOpcionParcial.textContent = 'Parcial';
            cicloSelect.appendChild(nuevaOpcionParcial);
        } else if (!persona.tiene_periodo_parcial && opcionParcial) {
            opcionParcial.remove();
        }
    }
    if (aperturaEjeInv) aperturaEjeInv.checked = false;
    if (aperturaEjeProy) aperturaEjeProy.checked = false;
    if (aperturaEjes) {
        aperturaEjes.classList.toggle('hidden', !(sistema === 'ACUERDO_GESTION' && !!persona.aplica_eje_misional));
    }

    actualizarPeriodoSeleccionado(selectedEvaluacionData);
    document.querySelectorAll('.evaluado-card').forEach(el => el.classList.remove('ring-[#00594E]', 'ring-2'));
    if (card) card.classList.add('ring-2', 'ring-[#00594E]');
}

export function cambiarTabEvaluador(tab) {
    const tabs = ['compromisos', 'competencias', 'ejes', 'recursos'];
    tabs.forEach(t => {
        const panel = document.getElementById(`tab-evaluador-${t}`);
        if (panel) panel.classList.toggle('hidden', t !== tab);
        const btn = document.getElementById(`tabbtn-evaluador-${t}`);
        if (btn) btn.classList.toggle('active', t === tab);
    });
    if (tab === 'competencias' && selectedEvaluacionData) cargarCompetenciasEvaluador(selectedEvaluacionData);
    if (tab === 'ejes' && selectedEvaluacionData) cargarEjesEvaluador(selectedEvaluacionData);
    if (tab === 'recursos' && selectedEvaluacionData) cargarPlanMejoramientoEvaluador(selectedEvaluacionData);
}

export function abrirConcertacionEvaluador(card, ev) {
    selectedEvaluacionId = ev.id_evaluacion;
    selectedEstadoEvaluacion = ev.estado;
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
    setText('concertacion-nombre', `${ev.evaluado_nombres || ''} ${ev.evaluado_apellidos || ''}`.trim());
    setText('concertacion-detalle', `${ev.evaluado_cargo || '-'} - ${ev.evaluado_area || '-'}`);
    setText('concertacion-sistema', ev.sistema === 'RENDIMIENTO_LABORAL' ? 'RL' : (ev.sistema === 'ACUERDO_GESTION' ? 'AG' : ev.sistema));

    const resultado = document.getElementById('resultado-calculo-evaluador');
    if (resultado) previsualizarCalculoEvaluador();

    const formFirmar = document.getElementById('form-firmar-evaluacion');
    if (formFirmar) formFirmar.action = `/evaluaciones/${ev.id_evaluacion}/firmar`;

    // El impedimento solo procede antes de cerrar la concertación. Nunca debe
    // estar disponible durante seguimiento, evidencias o calificación.
    const btnImpedimento = document.getElementById('btn-impedimento-evaluador');
    const modalImpedimento = document.getElementById('modal-impedimento');
    const puedeDeclararImpedimento = ev.estado === 'EN_PROCESO'
        && !ev.concertacion_firmada
        && Number(ev.fase_actual || 1) <= 2
        && !ev.es_traslado;
    if (btnImpedimento) btnImpedimento.classList.toggle('hidden', !puedeDeclararImpedimento);
    if (!puedeDeclararImpedimento && modalImpedimento) modalImpedimento.classList.add('hidden');

    const avisoTraslado = document.getElementById('aviso-traslado-evaluador');
    if (avisoTraslado) avisoTraslado.classList.toggle('hidden', !ev.es_traslado);

    const avisoDelegacion = document.getElementById('aviso-delegacion-evaluador');
    if (avisoDelegacion) {
        const titular = ev.id_vinc_suplente ? `${ev.suplente_nombres || ''} ${ev.suplente_apellidos || ''}`.trim() : '';
        const label = document.getElementById('delegacion-titular-nombre');
        if (label) label.textContent = titular;
        avisoDelegacion.classList.toggle('hidden', !ev.id_vinc_suplente);
    }
    
    // Remueve mensaje de desacuerdo anterior si existe
    const oldDesacuerdo = document.getElementById('aviso-desacuerdo-evaluador');
    if (oldDesacuerdo) oldDesacuerdo.remove();
    
    if (ev.desacuerdo_evaluado && avisoTraslado) {
        avisoTraslado.insertAdjacentHTML('beforebegin', `
            <div id="aviso-desacuerdo-evaluador" class="mt-4 flex items-start gap-2 rounded-2xl border border-red-200 bg-red-50 p-3 text-xs font-semibold text-red-700">
                <span class="material-symbols-outlined text-base shrink-0">gavel</span>
                <div>
                    <p class="font-bold uppercase tracking-wider text-[10px] text-red-500 mb-0.5">El evaluado reportó un desacuerdo</p>
                    <p class="whitespace-pre-wrap">${escapeHtml(ev.desacuerdo_evaluado)}</p>
                </div>
            </div>
        `);
    }

    const formNuevoComp = document.getElementById('compromiso-formulario-evaluador-contenedor');
    if (formNuevoComp) formNuevoComp.classList.toggle('hidden', !!ev.es_traslado || !!ev.concertacion_firmada);

    const tabBtnEjes = document.getElementById('tabbtn-evaluador-ejes');
    const vistaEjes = document.getElementById('ejes-misionales-vista-evaluador');
    if (tabBtnEjes) tabBtnEjes.classList.add('hidden');
    if (vistaEjes) vistaEjes.classList.add('hidden');

    cambiarTabEvaluador('compromisos');

    fetchJson(`/evaluaciones/${ev.id_evaluacion}/ejes`)
        .then(res => res.json())
        .then(ejes => {
            selectedEvaluacionEjes = ejes || {};
            const aplica = ev.sistema === 'ACUERDO_GESTION' && !!ev.aplica_eje_misional;
            if (aplica && vistaEjes) {
                vistaEjes.classList.remove('hidden');
                document.getElementById('eje-vista-investigacion')?.classList.toggle('hidden', !ejes.investigacion);
                document.getElementById('eje-vista-proyeccion')?.classList.toggle('hidden', !ejes.proyeccion_social);
                document.getElementById('eje-vista-ninguno')?.classList.toggle('hidden', !!(ejes.investigacion || ejes.proyeccion_social));
            }
            if (aplica && tabBtnEjes) tabBtnEjes.classList.remove('hidden');
            cargarCompromisosEvaluador(ev, ejes);
        })
        .catch(() => cargarCompromisosEvaluador(ev, {}));

    cargarRecursosEvaluador(ev);
    cargarPlanMejoramientoEvaluador(ev);

    document.querySelectorAll('.evaluacion-evaluador-card').forEach(el => el.classList.remove('ring-2', 'ring-[#00594E]'));
    if (card) card.classList.add('ring-2', 'ring-[#00594E]');
}

export function renderEvidenciasEvaluadorAccion(evidencias = [], bloqueada = false) {
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
                    ${estado === 'RECHAZADA' && evidencia.observacion_aprobacion ? `<p class="text-[10px] text-red-600 mt-1">${escapeHtml(evidencia.observacion_aprobacion)}</p>` : ''}
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

export function aprobarEvidencia(idEvidencia, decision) {
    if (!selectedEvaluacionId) return;

    let observacion = '';
    if (decision === 'RECHAZADA') {
        const motivo = prompt('Motivo del rechazo:');
        if (motivo === null) return;
        if (!motivo.trim()) { alert('Debes indicar un motivo para rechazar la evidencia.'); return; }
        observacion = motivo.trim();
        if (!confirm('¿Confirmas rechazar esta evidencia? Esta decisión quedará registrada.')) return;
    } else {
        if (!confirm('¿Confirmas aprobar esta evidencia? Esta decisión quedará registrada.')) return;
    }

    fetchJson(`/evaluaciones/${selectedEvaluacionId}/evidencias/${idEvidencia}/aprobar`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ decision, observacion }),
    })
        .then(async res => {
            const payload = await res.json().catch(() => ({}));
            if (!res.ok) { alert(payload.message || 'No se pudo registrar la decisión.'); return; }
            if (selectedEvaluacionData) cargarCompromisosEvaluador(selectedEvaluacionData, selectedEvaluacionEjes);
        })
        .catch(() => {});
}

export function renderObservacionEvaluador(compromiso, observacion = null, bloqueadaPorCierre = false) {
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

export function guardarObservacionCompromiso(e, idCompromiso, confirmar = false) {
    if (e) e.preventDefault();
    if (!selectedEvaluacionId) return;
    const msg = document.getElementById(`observacion-mensaje-${idCompromiso}`);
    const texto = (document.getElementById(`observacion-compromiso-${idCompromiso}`)?.value || '').trim();

    fetchJson(`/evaluaciones/${selectedEvaluacionId}/observaciones`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id_compromiso: idCompromiso, texto, confirmar }),
    })
        .then(async res => {
            const payload = await res.json().catch(() => ({}));
            if (!res.ok) throw new Error(parseErrorMessage(payload, 'No se pudo guardar la observación.'));
            if (msg) {
                msg.classList.remove('hidden');
                msg.className = 'text-xs font-semibold text-[#00594E]';
                msg.innerText = payload.message || 'Observación guardada.';
            }
            if (selectedEvaluacionData) cargarCompromisosEvaluador(selectedEvaluacionData, selectedEvaluacionEjes);
        })
        .catch(error => {
            if (msg) {
                msg.classList.remove('hidden');
                msg.className = 'text-xs font-semibold text-red-600';
                msg.innerText = error.message;
            }
        });
}

export function confirmarObservacionCompromiso(idCompromiso) {
    if (!confirm('¿Confirmas esta observación? Una vez confirmada no podrás modificarla.')) return;
    guardarObservacionCompromiso(null, idCompromiso, true);
}

export function cargarCompromisosEvaluador(ev, ejes = {}) {
    const contenedor = document.getElementById('compromisos-lista-contenedor');
    const sumaPesoNode = document.getElementById('compromisos-suma-peso-evaluador');
    const contadorNode = document.getElementById('compromisos-contador-evaluador');
    if (!contenedor) return;

    fetchJson(`/evaluaciones/${ev.id_evaluacion}/compromisos`)
        .then(res => res.json())
        .then(payload => {
            if (payload.estado_evaluacion) {
                ev.estado = payload.estado_evaluacion;
                if (selectedEvaluacionData && selectedEvaluacionData.id_evaluacion === ev.id_evaluacion) {
                    selectedEvaluacionData.estado = payload.estado_evaluacion;
                }
            }
            const compromisos = payload.compromisos || [];
            const evidencias = payload.evidencias || [];
            const observaciones = payload.observaciones || [];
            const objetivo = calcularObjetivoCompromisos(ev, ejes);
            compromisosActuales = compromisos;

            const gruposEvidencias = evidencias.reduce((g, e) => {
                const k = String(e.id_compromiso || '');
                if (!g[k]) g[k] = [];
                g[k].push(e);
                return g;
            }, {});
            const gruposObservaciones = observaciones.reduce((g, o) => {
                const k = String(o.id_compromiso || '');
                if (k) g[k] = o;
                return g;
            }, {});

            const total = compromisos.length;
            const sumaPeso = compromisos.reduce((acc, item) => acc + parseFloat(item.porcentaje_peso || 0), 0);
            if (sumaPesoNode) sumaPesoNode.innerText = `${sumaPeso.toFixed(1)}% / ${objetivo.toFixed(1)}%`;
            if (contadorNode) contadorNode.innerText = `${total} compromisos (mín 7, máx 10)`;

            const cumpleConcertacion = total >= 7 && total <= 10 && Math.abs(sumaPeso - objetivo) < 0.01;
            const btnFirmar = document.getElementById('btn-firmar-evaluador');
            if (btnFirmar) {
                const yaFirmado = !!ev.evaluador_firmado;
                btnFirmar.disabled = !cumpleConcertacion || yaFirmado || !!ev.es_traslado;
                btnFirmar.classList.toggle('bg-[#00594E]', !yaFirmado);
                btnFirmar.classList.toggle('bg-emerald-600', yaFirmado);
                btnFirmar.classList.toggle('cursor-not-allowed', yaFirmado);
                btnFirmar.innerText = yaFirmado ? 'Firmado' : 'Firmar concertación';
            }

            const puedeCalificar = ev.concertacion_firmada && ev.estado !== 'CALIFICADA' && !ev.es_traslado;
            const bloqueCalificacion = document.getElementById('compromisos-calificacion-bloque');
            if (bloqueCalificacion) bloqueCalificacion.classList.toggle('hidden', !puedeCalificar);

            if (!compromisos.length) {
                contenedor.innerHTML = '<div class="py-8 text-center text-slate-500 text-xs">No hay compromisos registrados aún.</div>';
                return;
            }

            contenedor.innerHTML = compromisos.map((c, idx) => {
                const calificacionControl = ev.concertacion_firmada
                    ? (ev.estado !== 'CALIFICADA'
                        ? `<div class="flex flex-col items-end gap-1">
                            <label class="text-[10px] font-bold uppercase text-slate-500">Calificación (0-100)</label>
                            <input type="number" min="0" max="100" step="0.01" class="compromiso-calificacion-input w-20 text-xs rounded-lg border border-slate-200 p-1.5 bg-white outline-none focus:border-[#00594E]" data-id="${c.id_compromiso}" value="${c.calificacion_definitiva ?? ''}" onblur="clampCalificacion(this)" />
                        </div>`
                        : `<div class="flex flex-col items-end gap-1">
                            <label class="text-[10px] font-bold uppercase text-slate-500">Calificación</label>
                            <span class="text-xs font-black rounded-xl px-2.5 py-1 bg-[#EAF2EF] text-[#00594E]">${c.calificacion_definitiva ?? '-'}</span>
                        </div>`)
                    : '';
                return `
                <div class="rounded-2xl border border-slate-200 bg-white p-4 space-y-3 shadow-sm transition hover:border-[#00594E]/40">
                    <div class="flex items-center justify-between gap-3 cursor-pointer select-none" onclick="document.getElementById('detalle-comp-evaluador-${c.id_compromiso}')?.classList.toggle('hidden'); document.getElementById('icon-chevron-evaluador-${c.id_compromiso}')?.classList.toggle('rotate-180');">
                        <div class="min-w-0 flex items-center gap-2">
                            <span id="icon-chevron-evaluador-${c.id_compromiso}" class="material-symbols-outlined text-[#00594E] text-base transition-transform duration-200">expand_more</span>
                            <div>
                                <span class="text-[10px] font-black uppercase text-[#00594E] tracking-wide">Compromiso #${idx + 1}</span>
                                <p class="text-xs font-semibold text-slate-800 mt-0.5">${escapeHtml(c.descripcion)}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 shrink-0" onclick="event.stopPropagation()">
                            ${calificacionControl}
                            <span class="text-xs font-black rounded-xl px-2.5 py-1 bg-[#EAF2EF] text-[#00594E]">${c.porcentaje_peso}%</span>
                            ${!ev.concertacion_firmada && !ev.es_traslado ? `
                            <button type="button" onclick="editarCompromisoEvaluador(${c.id_compromiso})" class="text-[#00594E] hover:text-[#00443b] p-1" title="Editar compromiso">
                                <span class="material-symbols-outlined text-base">edit</span>
                            </button>
                            <button type="button" onclick="eliminarCompromisoEvaluador(${c.id_compromiso})" class="text-red-400 hover:text-red-600 p-1" title="Eliminar compromiso">
                                <span class="material-symbols-outlined text-base">delete</span>
                            </button>` : ''}
                        </div>
                    </div>
                    <div id="detalle-comp-evaluador-${c.id_compromiso}" class="space-y-3 pt-2 border-t border-slate-100">
                        <p class="text-[11px] text-slate-500"><span class="font-bold">Metas:</span> ${(c.metas || []).join(', ') || '-'}</p>
                        <div id="editar-compromiso-contenedor-${c.id_compromiso}" class="hidden"></div>
                        <div class="pt-2 border-t border-slate-100">
                            <p class="text-[10px] font-bold uppercase text-slate-400 mb-1">Evidencias Registradas</p>
                            ${renderEvidenciasEvaluadorAccion(gruposEvidencias[String(c.id_compromiso)] || [], !ev.concertacion_firmada || ev.estado === 'CALIFICADA' || !!ev.es_traslado)}
                        </div>
                        ${renderObservacionEvaluador(c, gruposObservaciones[String(c.id_compromiso)], !ev.concertacion_firmada)}
                    </div>
                </div>
            `;
            }).join('');
        })
        .catch(() => {
            contenedor.innerHTML = '<div class="py-8 text-center text-red-500 text-xs">Error al cargar compromisos.</div>';
        });
}

export function agregarCompromisoEvaluador(e) {
    e.preventDefault();
    if (!selectedEvaluacionId) return;
    const desc = document.getElementById('comp-descripcion-evaluador')?.value;
    const peso = parseFloat(document.getElementById('comp-peso-evaluador')?.value || '0');
    const metas = document.getElementById('comp-metas-evaluador')?.value;

    fetchJson(`/evaluaciones/${selectedEvaluacionId}/compromisos`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ descripcion: desc, porcentaje_peso: peso, metas: (metas || '').split(',').map(m => m.trim()).filter(Boolean) }),
    })
        .then(async res => {
            const data = await res.json().catch(() => ({}));
            if (data.error) {
                alert(data.error);
            } else {
                document.getElementById('form-nuevo-compromiso-evaluador')?.reset();
                if (selectedEvaluacionData) cargarCompromisosEvaluador(selectedEvaluacionData, selectedEvaluacionEjes);
            }
        });
}

export function eliminarCompromisoEvaluador(id) {
    if (!confirm('¿Deseas eliminar este compromiso?')) return;
    fetchJson(`/compromisos/${id}`, { method: 'DELETE' })
        .then(res => res.json())
        .then(data => {
            if (data.error) {
                alert(data.error);
            } else if (selectedEvaluacionData) {
                cargarCompromisosEvaluador(selectedEvaluacionData, selectedEvaluacionEjes);
            }
        });
}

export function editarCompromisoEvaluador(id) {
    const compromiso = compromisosActuales.find(c => c.id_compromiso == id);
    if (!compromiso) return;
    const contenedor = document.getElementById(`editar-compromiso-contenedor-${id}`);
    if (!contenedor) return;

    const metasTexto = (compromiso.metas || []).join(', ');
    contenedor.innerHTML = `
        <div class="rounded-2xl border border-[#B5A160]/40 bg-[#B5A160]/5 p-4 space-y-3">
            <h4 class="text-[10px] font-black uppercase text-[#8a7b3c] tracking-wide">Editar compromiso</h4>
            <div>
                <label class="block text-[10px] font-bold text-slate-500 uppercase mb-0.5">Descripción del Compromiso</label>
                <textarea id="edit-comp-descripcion-${id}" class="w-full text-xs rounded-xl border border-slate-200 p-2.5 bg-white outline-none focus:border-[#00594E]" rows="2" required>${escapeHtml(compromiso.descripcion)}</textarea>
            </div>
            <div class="grid grid-cols-3 gap-2 items-end">
                <div class="col-span-1">
                    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-0.5">Peso (1% - 15%)</label>
                    <input type="number" id="edit-comp-peso-${id}" min="1" max="15" step="0.1" value="${escapeHtml(String(compromiso.porcentaje_peso ?? ''))}" class="w-full text-xs rounded-xl border border-slate-200 p-2.5 bg-white outline-none focus:border-[#00594E]" required />
                </div>
                <div class="col-span-2">
                    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-0.5">Metas de Contribución</label>
                    <input type="text" id="edit-comp-metas-${id}" value="${escapeHtml(metasTexto)}" class="w-full text-xs rounded-xl border border-slate-200 p-2.5 bg-white outline-none focus:border-[#00594E]" placeholder="Separadas por comas (ej: PDI, Manual)" required />
                </div>
            </div>
            <div class="flex gap-2">
                <button type="button" onclick="guardarEdicionCompromisoEvaluador(${id})" class="flex-1 bg-[#00594E] text-white py-2 rounded-xl text-xs font-bold hover:brightness-110 transition">Guardar cambios</button>
                <button type="button" onclick="cancelarEdicionCompromisoEvaluador(${id})" class="bg-white border border-slate-200 text-slate-600 px-4 py-2 rounded-xl text-xs font-bold hover:border-[#00594E] transition">Cancelar</button>
            </div>
        </div>`;
    contenedor.classList.remove('hidden');
}

export function cancelarEdicionCompromisoEvaluador(id) {
    const contenedor = document.getElementById(`editar-compromiso-contenedor-${id}`);
    if (contenedor) contenedor.classList.add('hidden');
}

export function guardarEdicionCompromisoEvaluador(id) {
    const descripcion = document.getElementById(`edit-comp-descripcion-${id}`)?.value?.trim();
    const peso = parseFloat(document.getElementById(`edit-comp-peso-${id}`)?.value || '0');
    const metas = (document.getElementById(`edit-comp-metas-${id}`)?.value || '').split(',').map(m => m.trim()).filter(Boolean);

    if (!descripcion || !peso || !metas.length) {
        alert('Completa la descripción, el peso y al menos una meta.');
        return;
    }

    fetchJson(`/compromisos/${id}`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ descripcion, porcentaje_peso: peso, metas }),
    })
        .then(async res => {
            const data = await res.json().catch(() => ({}));
            if (!res.ok) throw new Error(parseErrorMessage(data, 'No se pudo guardar el compromiso.'));
            if (selectedEvaluacionData) cargarCompromisosEvaluador(selectedEvaluacionData, selectedEvaluacionEjes);
        })
        .catch(error => alert(error.message));
}

export function renderTarjetaRecurso(r, contexto) {
    const tipo = r.tipo_recurso === 'REPOSICION' ? 'Reposición' : 'Apelación';
    let acciones = '';
    if (contexto === 'evaluador' && r.decision === 'PENDIENTE') {
        acciones = `
            <div class="mt-3 grid grid-cols-2 gap-2">
                <select id="decision-${r.id_recurso}" class="w-full text-xs rounded-xl border border-slate-200 p-2 bg-white outline-none focus:border-[#00594E]">
                    <option value="APROBADO">Aprobado</option>
                    <option value="NEGADO">Negado</option>
                </select>
                <button onclick="decidirRecurso(${r.id_recurso})" class="bg-[#00594E] text-white px-3 py-2 rounded-xl text-xs font-bold hover:brightness-110 transition" type="button">Decidir</button>
            </div>`;
    }
    const evidencias = Array.isArray(r.evidencias) && r.evidencias.length
        ? `<div class="pt-2 border-t border-slate-100 space-y-1.5">
            <p class="text-[10px] font-bold uppercase text-slate-500 flex items-center gap-1"><span class="material-symbols-outlined text-xs">link</span> Evidencias</p>
            ${r.evidencias.map(ev => `
                <a href="${escapeHtml(ev.url)}" target="_blank" rel="noopener noreferrer" class="flex items-center gap-1.5 text-xs text-[#00594E] hover:underline min-w-0">
                    <span class="material-symbols-outlined text-sm shrink-0">open_in_new</span>
                    <span class="truncate">${escapeHtml(ev.descripcion || ev.url)}</span>
                </a>`).join('')}
        </div>`
        : '';
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
            ${r.evaluado_nombres ? `<p class="text-[10px] font-bold text-slate-500 uppercase">Evaluado: ${escapeHtml(r.evaluado_nombres)} ${escapeHtml(r.evaluado_apellidos || '')}</p>` : ''}
            <p class="text-xs text-slate-600 whitespace-pre-wrap">${escapeHtml(r.motivacion || '')}</p>
            ${evidencias}
            ${acciones}
        </div>`;
}

export function cargarRecursosEvaluador(ev) {
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

export function cargarRecursosMiosEvaluador() {
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

export function enviarDecisionRecurso(id, decision, motivacion) {
    fetchJson(`/recursos/${id}/decision`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ decision, motivacion }),
    })
        .then(async res => {
            const payload = await res.json().catch(() => ({}));
            if (!res.ok) throw new Error(parseErrorMessage(payload, 'No se pudo registrar la decisión.'));
            alert(payload.message || 'Decisión registrada.');
            if (selectedEvaluacionData) {
                if (decision === 'APROBADO') {
                    selectedEvaluacionData.estado = 'EN_PROCESO';
                    abrirConcertacionEvaluador(null, selectedEvaluacionData);
                } else {
                    cargarRecursosEvaluador(selectedEvaluacionData);
                }
            }
            cargarRecursosMiosEvaluador();
        })
        .catch(error => alert(error.message));
}

export function decidirRecurso(id) {
    const decision = document.getElementById(`decision-${id}`)?.value;
    const motivacion = prompt('Escribe la motivación de tu decisión sobre el recurso:');
    if (motivacion === null) return;
    if (!motivacion.trim()) { alert('La motivación es obligatoria para decidir el recurso.'); return; }
    enviarDecisionRecurso(id, decision, motivacion);
}

export function renderFirmasPlan(plan) {
    if (!plan) return '<span class="text-[10px] text-slate-400">El plan aún no ha sido creado.</span>';
    const row = (label, firmado, fecha) => `
        <div class="rounded-xl border border-slate-100 bg-white p-3">
            <p class="text-[10px] font-bold text-slate-500 uppercase">${label}</p>
            <p class="text-xs font-bold mt-1 ${firmado ? 'text-emerald-700' : 'text-amber-700'}">${firmado ? 'Firmado' : 'Sin firmar'}${fecha ? ` · ${escapeHtml(fecha)}` : ''}</p>
        </div>`;
    return row('Evaluador', !!plan.firmado_evaluador, plan.fecha_firma_evaluador)
        + row('Evaluado', !!plan.firmado_evaluado, plan.fecha_firma_evaluado);
}

export function cargarPlanMejoramientoEvaluador(ev) {
    const bloque = document.getElementById('bloque-plan-mejoramiento-evaluador');
    if (!bloque || !ev) return Promise.resolve();
    return fetchJson(`/evaluaciones/${ev.id_evaluacion}/plan-mejoramiento`)
        .then(res => res.json())
        .then(payload => {
            const habilitado = !!payload.habilitado;
            bloque.classList.toggle('hidden', !habilitado);
            if (!habilitado) return payload;
            const plan = payload.plan;
            selectedPlanData = plan;
            const congelado = !!(plan && plan.estado === 'CONCERTADO');
            const estado = document.getElementById('plan-estado-evaluador');
            if (estado) {
                estado.classList.remove('hidden');
                estado.innerText = plan && plan.firmado_evaluador && plan.firmado_evaluado ? 'CONCERTADO' : (plan ? (plan.estado || 'PENDIENTE') : 'PENDIENTE');
            }
            const textarea = document.getElementById('plan-temas-evaluador');
            if (textarea) {
                textarea.value = plan ? (plan.descripcion_temas || '') : '';
                textarea.readOnly = congelado;
                textarea.classList.toggle('bg-slate-100', congelado);
                textarea.classList.toggle('cursor-not-allowed', congelado);
            }
            const btnGuardar = document.getElementById('btn-guardar-plan-evaluador');
            if (btnGuardar) {
                btnGuardar.disabled = congelado;
                btnGuardar.classList.toggle('hidden', congelado);
            }
            const btnFirmar = document.getElementById('btn-firmar-plan-evaluador');
            if (btnFirmar) {
                btnFirmar.classList.toggle('hidden', congelado || !(plan && !plan.firmado_evaluador));
            }
            const firmas = document.getElementById('plan-firmas-evaluador');
            if (firmas) firmas.innerHTML = renderFirmasPlan(plan);
            return payload;
        })
        .catch(() => {});
}

export function guardarPlanMejoramiento(e) {
    e.preventDefault();
    if (!selectedEvaluacionId) return;
    const mensaje = document.getElementById('plan-mensaje-evaluador');
    const temas = (document.getElementById('plan-temas-evaluador')?.value || '').trim();
    if (!temas) {
        if (mensaje) { mensaje.classList.remove('hidden'); mensaje.className = 'text-xs font-semibold text-red-600'; mensaje.innerText = 'Describe los temas del plan de mejoramiento.'; }
        return;
    }
    fetchJson(`/evaluaciones/${selectedEvaluacionId}/plan-mejoramiento`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ descripcion_temas: temas }),
    })
        .then(async res => {
            const payload = await res.json().catch(() => ({}));
            if (!res.ok) throw new Error(parseErrorMessage(payload, 'No se pudo guardar el plan.'));
            if (mensaje) { mensaje.classList.remove('hidden'); mensaje.className = 'text-xs font-semibold text-[#00594E]'; mensaje.innerText = payload.message || 'Plan guardado. Ya puedes firmarlo.'; }
            if (selectedEvaluacionData) cargarPlanMejoramientoEvaluador(selectedEvaluacionData);
        })
        .catch(error => {
            if (mensaje) { mensaje.classList.remove('hidden'); mensaje.className = 'text-xs font-semibold text-red-600'; mensaje.innerText = error.message; }
        });
}

export function firmarPlanMejoramiento(rol) {
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
                headers: { 'Content-Type': 'application/json' },
                body: '{}',
            })
                .then(async res => {
                    const pData = await res.json().catch(() => ({}));
                    if (!res.ok) throw new Error(parseErrorMessage(pData, 'No se pudo firmar el plan.'));
                    alert(pData.message || 'Firma registrada con éxito.');
                    if (selectedEvaluacionData) cargarPlanMejoramientoEvaluador(selectedEvaluacionData);
                })
                .catch(error => alert(error.message));
        })
        .catch(() => alert('Ocurrió un error al obtener la información del plan.'));
}

export function firmarConcertacion(e, rol) {
    if (!confirm('¿Confirmas firmar la concertación? Una vez que ambas partes firmen, los compromisos y sus porcentajes quedarán bloqueados.')) {
        e.preventDefault();
        return false;
    }
    return true;
}

export function guardarCalificacionesCompromisos() {
    if (!selectedEvaluacionId) return;
    const inputs = Array.from(document.querySelectorAll('.compromiso-calificacion-input'));
    const compromisos = inputs.map(inp => ({
        id_compromiso: parseInt(inp.dataset.id, 10),
        calificacion_definitiva: inp.value === '' ? null : parseFloat(inp.value),
    })).filter(i => i.calificacion_definitiva !== null);
    if (!compromisos.length) {
        showInlineMessage('compromisos-calificacion-mensaje-evaluador', 'Ingresa al menos una calificación.', true);
        return;
    }
    fetchJson(`/evaluaciones/${selectedEvaluacionId}/calificar-compromisos`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ compromisos }),
    })
        .then(async res => {
            const data = await res.json().catch(() => ({}));
            if (!res.ok) throw new Error(parseErrorMessage(data, 'No se pudieron guardar las calificaciones de compromisos.'));
            showInlineMessage('compromisos-calificacion-mensaje-evaluador', data.message || 'Calificaciones de compromisos guardadas.');
            previsualizarCalculoEvaluador();
        })
        .catch(error => showInlineMessage('compromisos-calificacion-mensaje-evaluador', error.message, true));
}

export function cargarCompetenciasEvaluador(ev) {
    const contenedor = document.getElementById('competencias-lista-evaluador');
    if (!contenedor || !ev) return;
    const sistema = String(ev.sistema || '').trim().toUpperCase();
    const nivel = ev.evaluado_nivel_jerarquico || '';
    contenedor.innerHTML = '<div class="text-xs text-slate-400">Cargando competencias...</div>';

    Promise.all([
        fetchJson(`/catalogo/competencias?sistema=${encodeURIComponent(sistema)}&nivel=${encodeURIComponent(nivel)}`).then(res => res.json()),
        fetchJson(`/evaluaciones/${ev.id_evaluacion}/competencias`).then(res => res.json()),
    ])
        .then(([catalogo, existentes]) => {
            if (catalogo.error) {
                contenedor.innerHTML = `<div class="text-xs text-red-500">${escapeHtml(catalogo.error)}</div>`;
                return;
            }
            const guardadas = (existentes.competencias || []).reduce((acc, c) => {
                acc[c.id_competencia] = c;
                return acc;
            }, {});
            const bloqueado = !ev.concertacion_firmada || ev.estado === 'CALIFICADA' || !!ev.es_traslado;
            const bloqueadoMsg = document.getElementById('competencias-bloqueado-evaluador');
            if (bloqueadoMsg) {
                bloqueadoMsg.classList.toggle('hidden', !bloqueado);
                bloqueadoMsg.innerText = ev.estado === 'CALIFICADA'
                    ? 'Esta evaluación ya fue calificada y calculada; las notas quedaron congeladas.'
                    : 'La concertación debe estar firmada por ambas partes antes de calificar competencias.';
            }
            const btn = document.getElementById('btn-guardar-competencias-evaluador');
            if (btn) btn.classList.toggle('hidden', bloqueado);

            const renderGrupo = (rows) => rows.map(c => {
                const existente = guardadas[c.id_competencia] || {};
                const valor = existente.calificacion_definitiva ?? '';
                return `
                    <div class="rounded-xl border border-slate-100 bg-slate-50/50 p-3 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                        <div class="min-w-0">
                            <p class="text-xs font-bold text-slate-800">${escapeHtml(c.nombre)}</p>
                            ${c.afirmacion ? `<p class="text-[10px] text-slate-400 mt-0.5">${escapeHtml(c.afirmacion)}</p>` : ''}
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <label class="text-[10px] font-bold text-slate-500 uppercase">Nota (0-100)</label>
                            <input type="number" min="0" max="100" step="0.01" class="competencia-calificacion-input w-24 text-xs rounded-lg border border-slate-200 p-1.5 disabled:bg-slate-100 disabled:text-slate-500" data-id="${c.id_competencia}" value="${valor}" onblur="clampCalificacion(this)" ${bloqueado ? 'disabled' : ''} />
                        </div>
                    </div>`;
            }).join('');

            let html = '';
            const comunes = catalogo.comun || [];
            const nivelRows = catalogo.nivel_jerarquico || [];
            if (comunes.length) {
                html += `<p class="text-[10px] font-bold uppercase text-slate-500">Competencias comunes</p>` + renderGrupo(comunes);
            }
            if (nivelRows.length) {
                html += `<p class="text-[10px] font-bold uppercase text-slate-500 mt-3">Competencias del nivel jerárquico</p>` + renderGrupo(nivelRows);
            }
            contenedor.innerHTML = html || '<div class="text-xs text-slate-400">No hay competencias en el catálogo para este sistema y nivel.</div>';
        })
        .catch(() => {
            contenedor.innerHTML = '<div class="text-xs text-red-500">Error al cargar competencias.</div>';
        });
}

export function guardarCalificacionesCompetencias() {
    if (!selectedEvaluacionId) return;
    const inputs = Array.from(document.querySelectorAll('.competencia-calificacion-input'));
    const competencias = inputs.map(inp => ({
        id_competencia: parseInt(inp.dataset.id, 10),
        calificacion_definitiva: inp.value === '' ? null : parseFloat(inp.value),
    })).filter(i => i.calificacion_definitiva !== null);
    if (!competencias.length) {
        showInlineMessage('competencias-mensaje-evaluador', 'Ingresa al menos una calificación.', true);
        return;
    }
    fetchJson(`/evaluaciones/${selectedEvaluacionId}/calificar-competencias`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ competencias }),
    })
        .then(async res => {
            const data = await res.json().catch(() => ({}));
            if (!res.ok) throw new Error(parseErrorMessage(data, 'No se pudieron guardar las competencias.'));
            showInlineMessage('competencias-mensaje-evaluador', data.message || 'Competencias guardadas.');
            previsualizarCalculoEvaluador();
        })
        .catch(error => showInlineMessage('competencias-mensaje-evaluador', error.message, true));
}

export function cargarEjesEvaluador(ev) {
    const contenedor = document.getElementById('ejes-lista-evaluador');
    if (!contenedor || !ev) return;
    if (ev.sistema !== 'ACUERDO_GESTION' || !ev.aplica_eje_misional) {
        contenedor.innerHTML = '';
        return;
    }
    const etiquetas = {
        DOCENCIA: 'Docencia (eje base)',
        INVESTIGACION: 'Horas de Investigación',
        PROYECCION_SOCIAL: 'Proyección Social',
    };
    fetchJson(`/evaluaciones/${ev.id_evaluacion}/calculo`)
        .then(res => res.json())
        .then(calculo => {
            const ejesActivos = calculo.ejes_activos || [];
            const notas = calculo.notas_ejes_raw || {};
            const pesos = (calculo.pesos && calculo.pesos.ejes) || {};
            const bloqueado = !ev.concertacion_firmada || ev.estado === 'CALIFICADA' || !!ev.es_traslado;
            const bloqueadoMsg = document.getElementById('ejes-bloqueado-evaluador');
            if (bloqueadoMsg) {
                bloqueadoMsg.classList.toggle('hidden', !bloqueado);
                bloqueadoMsg.innerText = ev.estado === 'CALIFICADA'
                    ? 'Esta evaluación ya fue calificada y calculada; las notas quedaron congeladas.'
                    : 'La concertación debe estar firmada por ambas partes antes de calificar ejes misionales.';
            }
            const btn = document.getElementById('btn-guardar-ejes-evaluador');
            if (btn) btn.classList.toggle('hidden', bloqueado);

            if (!ejesActivos.length) {
                contenedor.innerHTML = '<div class="text-xs text-slate-400">Esta evaluación no tiene ejes misionales activos.</div>';
                return;
            }
            contenedor.innerHTML = ejesActivos.map(eje => {
                const nota = notas[eje];
                const valor = nota === undefined ? '' : nota;
                return `
                    <div class="rounded-xl border border-slate-100 bg-slate-50/50 p-3 space-y-2">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                            <div>
                                <p class="text-xs font-bold text-slate-800">${etiquetas[eje] || eje}</p>
                                <p class="text-[10px] text-slate-400">Peso ${pesos[eje] ?? '-'}%</p>
                            </div>
                            <div class="flex items-center gap-2 shrink-0">
                                <label class="text-[10px] font-bold text-slate-500 uppercase">Nota (0-100)</label>
                                <input type="number" min="0" max="100" step="0.01" class="eje-calificacion-input w-24 text-xs rounded-lg border border-slate-200 p-1.5 disabled:bg-slate-100 disabled:text-slate-500" data-eje="${eje}" value="${valor}" onblur="clampCalificacion(this)" ${bloqueado ? 'disabled' : ''} />
                            </div>
                        </div>
                        <textarea class="eje-calificacion-observacion w-full text-xs rounded-lg border border-slate-200 p-2 disabled:bg-slate-100 disabled:text-slate-500" rows="2" data-eje="${eje}" placeholder="Observaciones (opcional)" ${bloqueado ? 'disabled' : ''}></textarea>
                    </div>`;
            }).join('');
        })
        .catch(() => {
            contenedor.innerHTML = '<div class="text-xs text-red-500">Error al cargar ejes misionales.</div>';
        });
}

export function guardarCalificacionesEjes() {
    if (!selectedEvaluacionId) return;
    const inputs = Array.from(document.querySelectorAll('.eje-calificacion-input'));
    const ejes = inputs.map(inp => ({
        tipo_eje: inp.dataset.eje,
        calificacion: parseFloat(inp.value),
        observacion: document.querySelector(`.eje-calificacion-observacion[data-eje="${inp.dataset.eje}"]`)?.value?.trim() || '',
    })).filter(i => !isNaN(i.calificacion));
    if (!ejes.length) {
        showInlineMessage('ejes-mensaje-evaluador', 'Ingresa al menos una calificación.', true);
        return;
    }
    fetchJson(`/evaluaciones/${selectedEvaluacionId}/calificar-ejes`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ ejes }),
    })
        .then(async res => {
            const data = await res.json().catch(() => ({}));
            if (!res.ok) throw new Error(parseErrorMessage(data, 'No se pudieron guardar los ejes misionales.'));
            showInlineMessage('ejes-mensaje-evaluador', data.message || 'Ejes misionales calificados.');
            previsualizarCalculoEvaluador();
        })
        .catch(error => showInlineMessage('ejes-mensaje-evaluador', error.message, true));
}

export function previsualizarCalculoEvaluador() {
    if (!selectedEvaluacionId) return;
    fetchJson(`/evaluaciones/${selectedEvaluacionId}/calculo`)
        .then(res => res.json())
        .then(calculo => {
            const resultado = document.getElementById('resultado-calculo-evaluador');
            if (resultado) {
                resultado.classList.remove('hidden');
                renderResultado(calculo, 'resultado-calculo-evaluador', 'evaluador');
            }
        })
        .catch(() => {});
}

export function calcularNotaFinal() {
    if (!selectedEvaluacionId) return;
    if (!confirm('¿Confirmas calcular la nota final? La evaluación quedará calificada y las notas se congelarán.')) return;
    fetchJson(`/evaluaciones/${selectedEvaluacionId}/calcular-final`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: '{}',
    })
        .then(async res => {
            const data = await res.json().catch(() => ({}));
            if (!res.ok) throw new Error(parseErrorMessage(data, 'No se pudo calcular la nota final.'));
            showInlineMessage('compromisos-calificacion-mensaje-evaluador', `${data.message || 'Nota final calculada.'} El evaluado ya puede firmar la notificación de la calificación o registrar renuencia.`);
            if (selectedEvaluacionData) {
                selectedEvaluacionData = { ...selectedEvaluacionData, estado: 'CALIFICADA' };
            }
            const resultado = document.getElementById('resultado-calculo-evaluador');
            if (resultado && data.calculo) {
                resultado.classList.remove('hidden');
                renderResultado(data.calculo, 'resultado-calculo-evaluador', 'evaluador');
            }
        })
        .catch(error => showInlineMessage('compromisos-calificacion-mensaje-evaluador', error.message, true));
}

export function mostrarModalRenuencia() {
    const modal = document.getElementById('modal-renuencia');
    const form = document.getElementById('form-renuencia-accion');
    if (modal && form) {
        modal.classList.toggle('hidden');
        form.action = `/evaluaciones/${selectedEvaluacionId}/renuencia`;
    }
}

export function mostrarModalImpedimento() {
    const modal = document.getElementById('modal-impedimento');
    const form = document.getElementById('form-impedimento-accion');
    if (modal && form) {
        modal.classList.toggle('hidden');
        form.action = `/evaluacion/${selectedEvaluacionId}/impedimento`;
    }
}
// --- Instancia Externa Functions ---
export function cargarListaInstanciaExterna() {
    const contenedor = document.getElementById('instancia-externa-lista');
    if (!contenedor) return;
    fetchJson('/instancia-externa/evaluaciones')
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

export function abrirInstanciaExterna(card, ev) {
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
        avisoTexto = 'Aún no se ha creado la evaluación de este periodo para esta persona. El evaluador debe abrirla primero antes de poder cargar notas.';
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

export function guardarNotasInstanciaExterna(e) {
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

    fetchJson(`/evaluaciones/${selectedEvalExternaId}/ejes-externa`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
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

export function seleccionarEvaluacionPorId(idEvaluacion, targetTab = 'recursos') {
    const sec = document.getElementById('section-evaluaciones-evaluador');
    if (sec && sec.classList.contains('hidden')) {
        navegarMenu(null, 'evaluaciones-evaluador');
    }

    const card = document.querySelector(`.evaluacion-evaluador-card[data-id-evaluacion="${idEvaluacion}"]`);
    if (card) {
        card.click();
        if (targetTab) {
            cambiarTabEvaluador(targetTab);
        }
        const panel = document.getElementById('panel-concertacion-evaluador');
        if (panel) {
            panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        if (targetTab === 'recursos' && selectedEvaluacionData) {
            cargarPlanMejoramientoEvaluador(selectedEvaluacionData).then(() => {
                const planBlock = document.getElementById('bloque-plan-mejoramiento-evaluador');
                if (planBlock && !planBlock.classList.contains('hidden')) {
                    planBlock.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            });
        }
    }
}

// Global window exposure
window.seleccionarEvaluacionPorId = seleccionarEvaluacionPorId;
window.seleccionarPersonaEvaluador = seleccionarPersonaEvaluador;
window.abrirConcertacionEvaluador = abrirConcertacionEvaluador;
window.cambiarTabEvaluador = cambiarTabEvaluador;
window.agregarCompromisoEvaluador = agregarCompromisoEvaluador;
window.eliminarCompromisoEvaluador = eliminarCompromisoEvaluador;
window.editarCompromisoEvaluador = editarCompromisoEvaluador;
window.cancelarEdicionCompromisoEvaluador = cancelarEdicionCompromisoEvaluador;
window.guardarEdicionCompromisoEvaluador = guardarEdicionCompromisoEvaluador;
window.aprobarEvidencia = aprobarEvidencia;
window.guardarObservacionCompromiso = guardarObservacionCompromiso;
window.confirmarObservacionCompromiso = confirmarObservacionCompromiso;
window.decidirRecurso = decidirRecurso;
window.guardarPlanMejoramiento = guardarPlanMejoramiento;
window.firmarPlanMejoramiento = firmarPlanMejoramiento;
window.firmarConcertacion = firmarConcertacion;
window.guardarCalificacionesCompromisos = guardarCalificacionesCompromisos;
window.guardarCalificacionesCompetencias = guardarCalificacionesCompetencias;
window.guardarCalificacionesEjes = guardarCalificacionesEjes;
window.calcularNotaFinal = calcularNotaFinal;
window.previsualizarCalculoEvaluador = previsualizarCalculoEvaluador;
window.cargarListaInstanciaExterna = cargarListaInstanciaExterna;
window.abrirInstanciaExterna = abrirInstanciaExterna;
window.guardarNotasInstanciaExterna = guardarNotasInstanciaExterna;
window.mostrarModalRenuencia = mostrarModalRenuencia;
window.mostrarModalImpedimento = mostrarModalImpedimento;
window.addEventListener('DOMContentLoaded', () => {
    const activeRole = window.APP_CONFIG?.activeRole || 'evaluador';
    if (activeRole === 'instancia_externa') {
        navegarMenu(null, 'instancia-externa');
        cargarListaInstanciaExterna();
    } else {
        navegarMenu(null, 'evaluaciones-evaluador');
        cargarRecursosMiosEvaluador();
        const firstEvaluacion = document.querySelector('.evaluacion-evaluador-card');
        if (firstEvaluacion) firstEvaluacion.click();
    }
});
