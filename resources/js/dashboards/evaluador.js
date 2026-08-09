/**
 * Evaluador & Instancia Externa Dashboard JS Module
 */
import { escapeHtml, fetchJson, parseErrorMessage, navegarMenu } from './common.js';

let selectedEvaluacionId = null;
let selectedEstadoEvaluacion = null;
let selectedEvaluacionData = null;
let selectedEvaluacionEjes = {};
let selectedPlanData = null;
let selectedEvalExternaId = null;

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

export function seleccionarPersonaEvaluador(card, persona) {
    selectedEvaluacionData = persona;
    const periodosDisponibles = window.APP_CONFIG?.periodosDisponibles || [];
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
    const aperturaRef = document.getElementById('apertura-referencia-input');
    if (aperturaRef) aperturaRef.value = '';
    const aperturaOpcionParcial = document.getElementById('apertura-opcion-parcial');
    if (aperturaOpcionParcial) aperturaOpcionParcial.classList.toggle('hidden', !persona.tiene_periodo_parcial);

    toggleAperturaDiasLaborados();
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
        if (aperturaAviso) aperturaAviso.innerText = 'El periodo se asigna automáticamente según el tipo de acuerdo.';
    } else {
        if (aperturaIdPeriodo) aperturaIdPeriodo.value = '';
        if (aperturaPeriodo) aperturaPeriodo.innerText = 'No hay periodo abierto para este sistema';
        if (aperturaVigencia) aperturaVigencia.innerText = '-';
        if (aperturaCiclo && cicloSelect) aperturaCiclo.innerText = cicloSelect.options[cicloSelect.selectedIndex].text;
        if (aperturaAviso) aperturaAviso.innerText = 'Abre un periodo activo para este sistema antes de iniciar la evaluacion.';
    }
    document.querySelectorAll('.evaluado-card').forEach(el => el.classList.remove('ring-[#00594E]', 'ring-2'));
    if (card) card.classList.add('ring-2', 'ring-[#00594E]');
}

export function toggleAperturaDiasLaborados() {
    const select = document.getElementById('apertura-ciclo-select');
    const wrap = document.getElementById('apertura-dias-laborados-wrap');
    const refWrap = document.getElementById('apertura-referencia-wrap');
    if (select && wrap) wrap.classList.toggle('hidden', select.value !== 'PARCIAL');
    if (select && refWrap) refWrap.classList.toggle('hidden', select.value !== 'PARCIAL');
}

export function cambiarTabEvaluador(tab) {
    const tabs = ['compromisos', 'competencias', 'ejes', 'recursos'];
    tabs.forEach(t => {
        const panel = document.getElementById(`tab-evaluador-${t}`);
        if (panel) panel.classList.toggle('hidden', t !== tab);
        const btn = document.getElementById(`tabbtn-evaluador-${t}`);
        if (btn) btn.classList.toggle('active', t === tab);
    });
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

    const formFirmar = document.getElementById('form-firmar-evaluacion');
    if (formFirmar) formFirmar.action = `/evaluaciones/${ev.id_evaluacion}/firmar`;

    const avisoTraslado = document.getElementById('aviso-traslado-evaluador');
    if (avisoTraslado) avisoTraslado.classList.toggle('hidden', !ev.es_traslado);

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

    fetchJson(`/evaluaciones/${selectedEvaluacionId}/compromisos/${idCompromiso}/observacion`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ texto, confirmar }),
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
            const compromisos = payload.compromisos || [];
            const evidencias = payload.evidencias || [];
            const observaciones = payload.observaciones || [];
            const objetivo = calcularObjetivoCompromisos(ev, ejes);

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
            const sumaPeso = compromisos.reduce((acc, item) => acc + parseFloat(item.peso_porcentaje || 0), 0);
            if (sumaPesoNode) sumaPesoNode.innerText = `${sumaPeso.toFixed(1)}% / ${objetivo.toFixed(1)}%`;
            if (contadorNode) contadorNode.innerText = `${total} compromisos (mín 7, máx 10)`;

            const cumpleConcertacion = total >= 7 && total <= 10 && Math.abs(sumaPeso - objetivo) < 0.01;
            const btnFirmar = document.getElementById('btn-firmar-evaluador');
            if (btnFirmar) btnFirmar.disabled = !cumpleConcertacion || !!ev.evaluador_firmado || !!ev.es_traslado;

            if (!compromisos.length) {
                contenedor.innerHTML = '<div class="py-8 text-center text-slate-500 text-xs">No hay compromisos registrados aún.</div>';
                return;
            }

            contenedor.innerHTML = compromisos.map((c, idx) => `
                <div class="rounded-2xl border border-slate-100 bg-slate-50/50 p-4 space-y-3">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <span class="text-[10px] font-black uppercase text-[#00594E] tracking-wide">Compromiso #${idx + 1}</span>
                            <p class="text-xs font-semibold text-slate-800 mt-0.5">${escapeHtml(c.descripcion)}</p>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <span class="text-xs font-black rounded-xl px-2.5 py-1 bg-[#EAF2EF] text-[#00594E]">${c.peso_porcentaje}%</span>
                            ${!ev.concertacion_firmada && !ev.es_traslado ? `
                            <button type="button" onclick="eliminarCompromisoEvaluador(${c.id_compromiso})" class="text-red-400 hover:text-red-600 p-1" title="Eliminar compromiso">
                                <span class="material-symbols-outlined text-base">delete</span>
                            </button>` : ''}
                        </div>
                    </div>
                    <p class="text-[11px] text-slate-500"><span class="font-bold">Metas:</span> ${escapeHtml(c.metas_subtemas || '-')}</p>
                    <div class="pt-2 border-t border-slate-100">
                        <p class="text-[10px] font-bold uppercase text-slate-400 mb-1">Evidencias</p>
                        ${renderEvidenciasEvaluadorAccion(gruposEvidencias[String(c.id_compromiso)] || [], !!ev.concertacion_firmada)}
                    </div>
                    ${renderObservacionEvaluador(c, gruposObservaciones[String(c.id_compromiso)], !ev.concertacion_firmada)}
                </div>
            `).join('');
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
        body: JSON.stringify({ descripcion: desc, peso_porcentaje: peso, metas_subtemas: metas }),
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
            if (selectedEvaluacionData) cargarRecursosEvaluador(selectedEvaluacionData);
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
    if (!bloque || !ev) return;
    fetchJson(`/evaluaciones/${ev.id_evaluacion}/plan-mejoramiento`)
        .then(res => res.json())
        .then(payload => {
            const requiere = !!payload.requiere_plan;
            bloque.classList.toggle('hidden', !requiere);
            if (!requiere) return;
            const plan = payload.plan;
            selectedPlanData = plan;
            const estado = document.getElementById('plan-estado-evaluador');
            if (estado) {
                estado.classList.remove('hidden');
                estado.innerText = plan && plan.firmado_evaluador && plan.firmado_evaluado ? 'CONCERTADO' : (plan ? (plan.estado || 'PENDIENTE') : 'PENDIENTE');
            }
            const textarea = document.getElementById('plan-temas-evaluador');
            if (textarea && plan) textarea.value = plan.descripcion_temas || '';
            const btnFirmar = document.getElementById('btn-firmar-plan-evaluador');
            if (btnFirmar) {
                btnFirmar.classList.toggle('hidden', !(plan && !plan.firmado_evaluador));
            }
            const firmas = document.getElementById('plan-firmas-evaluador');
            if (firmas) firmas.innerHTML = renderFirmasPlan(plan);
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

// Global window exposure
window.seleccionarPersonaEvaluador = seleccionarPersonaEvaluador;
window.toggleAperturaDiasLaborados = toggleAperturaDiasLaborados;
window.abrirConcertacionEvaluador = abrirConcertacionEvaluador;
window.cambiarTabEvaluador = cambiarTabEvaluador;
window.agregarCompromisoEvaluador = agregarCompromisoEvaluador;
window.eliminarCompromisoEvaluador = eliminarCompromisoEvaluador;
window.aprobarEvidencia = aprobarEvidencia;
window.guardarObservacionCompromiso = guardarObservacionCompromiso;
window.confirmarObservacionCompromiso = confirmarObservacionCompromiso;
window.decidirRecurso = decidirRecurso;
window.guardarPlanMejoramiento = guardarPlanMejoramiento;
window.firmarPlanMejoramiento = firmarPlanMejoramiento;
window.firmarConcertacion = firmarConcertacion;
window.cargarListaInstanciaExterna = cargarListaInstanciaExterna;
window.abrirInstanciaExterna = abrirInstanciaExterna;
window.guardarNotasInstanciaExterna = guardarNotasInstanciaExterna;

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
