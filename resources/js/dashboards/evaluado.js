/**
 * Evaluado Dashboard JS Module
 */
import { escapeHtml, fetchJson, parseErrorMessage, navegarMenu } from './common.js';

let selectedEvaluacionId = null;
let selectedEvaluacionData = null;
let selectedPlanData = null;

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

export function renderEvidenciasCompactas(evidencias = []) {
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

export function renderObservacionEvaluado(observacion = null) {
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

export function cambiarTabEvaluado(tab) {
    const tabs = ['compromisos', 'competencias', 'ejes', 'recursos'];
    tabs.forEach(t => {
        const panel = document.getElementById(`tab-evaluado-${t}`);
        if (panel) panel.classList.toggle('hidden', t !== tab);
        const btn = document.getElementById(`tabbtn-evaluado-${t}`);
        if (btn) btn.classList.toggle('active', t === tab);
    });
}

export function abrirConcertacionEvaluado(card, ev) {
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
    if (evaluador) evaluador.innerText = `Quién lo evaluó: ${ev.evaluador_nombres || 'Mi Evaluador'} ${ev.evaluador_apellidos || ''}`.trim();
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

    const tabBtnRecursos = document.getElementById('tabbtn-evaluado-recursos');
    if (tabBtnRecursos) {
        const puedeRecursos = ev.estado === 'CALIFICADA' && ev.categoria_final === 'NO_SATISFACTORIO';
        tabBtnRecursos.classList.toggle('hidden', !puedeRecursos);
    }

    const tabBtnCompromisos = document.getElementById('tabbtn-evaluado-compromisos');
    if (tabBtnCompromisos) tabBtnCompromisos.classList.add('active');

    cambiarTabEvaluado('compromisos');

    fetchJson(`/evaluaciones/${ev.id_evaluacion}/ejes`)
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

export function cargarCompromisosEvaluado(ev) {
    const contenedor = document.getElementById('compromisos-lista-evaluado');
    const sumaPesoNode = document.getElementById('compromisos-suma-peso-evaluado');
    const contadorNode = document.getElementById('compromisos-contador-evaluado');
    if (!contenedor) return;

    fetchJson(`/evaluaciones/${ev.id_evaluacion}/compromisos`)
        .then(res => res.json())
        .then(payload => {
            const compromisos = payload.compromisos || [];
            const evidencias = payload.evidencias || [];
            const observaciones = payload.observaciones || [];
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

            const totalCompromisos = compromisos.length;
            const sumaPeso = compromisos.reduce((acc, item) => acc + parseFloat(item.peso_porcentaje || 0), 0);
            if (sumaPesoNode) sumaPesoNode.innerText = `${sumaPeso.toFixed(1)}%`;
            if (contadorNode) contadorNode.innerText = `${totalCompromisos} compromisos`;

            const btnFirmar = document.getElementById('btn-firmar-evaluado');
            if (btnFirmar) {
                const firmable = ev.concertacion_firmada || (ev.evaluador_firmado && !ev.evaluado_firmado);
                btnFirmar.disabled = !firmable;
            }

            if (!compromisos.length) {
                contenedor.innerHTML = '<div class="py-8 text-center text-slate-500 text-xs">Aún no hay compromisos registrados por el evaluador.</div>';
                return;
            }

            contenedor.innerHTML = compromisos.map((c, idx) => `
                <div class="rounded-2xl border border-slate-100 bg-slate-50/50 p-4 space-y-3">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <span class="text-[10px] font-black uppercase text-[#00594E] tracking-wide">Compromiso #${idx + 1}</span>
                            <p class="text-xs font-semibold text-slate-800 mt-0.5">${escapeHtml(c.descripcion)}</p>
                        </div>
                        <span class="text-xs font-black rounded-xl px-2.5 py-1 bg-[#EAF2EF] text-[#00594E] shrink-0">${c.peso_porcentaje}%</span>
                    </div>
                    <p class="text-[11px] text-slate-500"><span class="font-bold">Metas:</span> ${escapeHtml(c.metas_subtemas || '-')}</p>
                    <div class="pt-2 border-t border-slate-100">
                        <p class="text-[10px] font-bold uppercase text-slate-400 mb-1">Evidencias</p>
                        ${renderEvidenciasCompactas(gruposEvidencias[String(c.id_compromiso)] || [])}
                    </div>
                    ${renderObservacionEvaluado(gruposObservaciones[String(c.id_compromiso)])}
                </div>
            `).join('');
        })
        .catch(() => {
            contenedor.innerHTML = '<div class="py-8 text-center text-red-500 text-xs">Error al cargar compromisos.</div>';
        });
}

export function cargarCompetenciasEvaluado() {
    if (!selectedEvaluacionId) return;
    const contenedor = document.getElementById('competencias-lista-evaluado');
    if (!contenedor) return;
    contenedor.innerHTML = '<div class="text-xs text-slate-400">Cargando competencias...</div>';
    fetchJson(`/evaluaciones/${selectedEvaluacionId}/competencias`)
        .then(res => res.json())
        .then(payload => {
            const competencias = payload.competencias || [];
            if (!competencias.length) {
                contenedor.innerHTML = '<div class="rounded-xl border border-dashed border-slate-200 bg-white p-4 text-xs text-slate-500 text-center">El evaluador aún no ha calificado competencias.</div>';
                return;
            }
            contenedor.innerHTML = competencias.map(c => `
                <div class="rounded-xl border border-slate-100 bg-slate-50/50 p-3 flex justify-between items-center">
                    <div>
                        <p class="text-xs font-bold text-slate-800">${escapeHtml(c.nombre_competencia || c.competencia)}</p>
                        <p class="text-[10px] text-slate-400 uppercase">${c.tipo}</p>
                    </div>
                    <span class="text-xs font-black px-2.5 py-1 rounded-lg bg-[#EAF2EF] text-[#00594E]">${c.nivel_desarrollo ?? '-'}</span>
                </div>
            `).join('');
        })
        .catch(() => {
            contenedor.innerHTML = '<div class="text-xs text-red-500">Error al cargar competencias.</div>';
        });
}

export function cargarEjesEvaluado(ev) {
    if (!ev || ev.sistema !== 'ACUERDO_GESTION' || !ev.aplica_eje_misional) return;
    const contenedor = document.getElementById('ejes-lista-evaluado');
    if (!contenedor) return;
    fetchJson(`/evaluaciones/${ev.id_evaluacion}/ejes`)
        .then(res => res.json())
        .then(ejes => {
            const items = [];
            if (ejes.docencia) items.push({ nombre: 'Docencia (eje base)', nota: ejes.docencia_nota });
            if (ejes.investigacion) items.push({ nombre: 'Horas de Investigación', nota: ejes.investigacion_nota });
            if (ejes.proyeccion_social) items.push({ nombre: 'Proyección Social', nota: ejes.proyeccion_social_nota });
            if (!items.length) {
                contenedor.innerHTML = '<div class="text-xs text-slate-400">Sin ejes misionales configurados.</div>';
                return;
            }
            contenedor.innerHTML = items.map(i => `
                <div class="rounded-xl border border-slate-100 bg-slate-50/50 p-3 flex justify-between items-center">
                    <span class="text-xs font-bold text-slate-800">${i.nombre}</span>
                    <span class="text-xs font-black px-2.5 py-1 rounded-lg bg-[#EAF2EF] text-[#00594E]">${i.nota ?? 'Sin nota'}</span>
                </div>
            `).join('');
        })
        .catch(() => {});
}

export function renderTarjetaRecurso(r, contexto) {
    const tipo = r.tipo_recurso === 'REPOSICION' ? 'Reposición' : 'Apelación';
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
            <p class="text-xs text-slate-600 whitespace-pre-wrap">${escapeHtml(r.motivacion || '')}</p>
            ${evidencias}
        </div>`;
}

export function cargarRecursosEvaluado(ev) {
    const bloque = document.getElementById('bloque-recursos-evaluado');
    if (!bloque || !ev) return;
    fetchJson(`/evaluaciones/${ev.id_evaluacion}/recursos`)
        .then(res => res.json())
        .then(payload => {
            const recursos = payload.recursos || [];
            const estado = payload.estado;
            const categoria = payload.categoria_final;
            const esNoSatisfactorio = estado === 'CALIFICADA' && categoria === 'NO_SATISFACTORIO';
            const tabBtnRecursos = document.getElementById('tabbtn-evaluado-recursos');
            if (tabBtnRecursos) tabBtnRecursos.classList.toggle('hidden', !esNoSatisfactorio);
            if (!esNoSatisfactorio) {
                if (tabBtnRecursos && tabBtnRecursos.classList.contains('active')) cambiarTabEvaluado('compromisos');
                return;
            }
            const tienePendiente = recursos.some(r => r.decision === 'PENDIENTE');
            const form = document.getElementById('form-recurso-evaluado');
            if (form) form.classList.toggle('hidden', tienePendiente);
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
        })
        .catch(() => {});
}

export function radicarRecurso(e) {
    e.preventDefault();
    if (!selectedEvaluacionId) return;
    const mensaje = document.getElementById('recurso-mensaje-evaluado');
    const tipo = document.getElementById('recurso-tipo-evaluado')?.value || 'REPOSICION';
    const folios = parseInt(document.getElementById('recurso-folios-evaluado')?.value || '0', 10);
    const motivacion = (document.getElementById('recurso-motivacion-evaluado')?.value || '').trim();
    if (!folios || folios < 1) {
        if (mensaje) { mensaje.classList.remove('hidden'); mensaje.className = 'text-xs font-semibold text-red-600'; mensaje.innerText = 'Indica el número de folios del recurso.'; }
        return;
    }
    if (!motivacion) {
        if (mensaje) { mensaje.classList.remove('hidden'); mensaje.className = 'text-xs font-semibold text-red-600'; mensaje.innerText = 'Escribe la motivación del recurso.'; }
        return;
    }
    const evidencias = Array.from(document.querySelectorAll('#recurso-evidencias-lista-evaluado .recurso-evidencia-url'))
        .map(input => {
            const url = (input.value || '').trim();
            if (!url) return null;
            const row = input.closest('div');
            const descripcion = (row?.querySelector('.recurso-evidencia-desc')?.value || '').trim();
            return { url, descripcion };
        })
        .filter(Boolean);
    fetchJson(`/evaluaciones/${selectedEvaluacionId}/recursos`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ tipo_recurso: tipo, numero_folios: folios, motivacion, evidencias }),
    })
        .then(async res => {
            const payload = await res.json().catch(() => ({}));
            if (!res.ok) throw new Error(parseErrorMessage(payload, 'No se pudo radicar el recurso.'));
            if (mensaje) { mensaje.classList.remove('hidden'); mensaje.className = 'text-xs font-semibold text-[#00594E]'; mensaje.innerText = payload.message || 'Recurso radicado.'; }
            e.target.reset();
            const listaEvidencias = document.getElementById('recurso-evidencias-lista-evaluado');
            if (listaEvidencias) listaEvidencias.innerHTML = '';
            cargarRecursosEvaluado(selectedEvaluacionData);
        })
        .catch(error => {
            if (mensaje) { mensaje.classList.remove('hidden'); mensaje.className = 'text-xs font-semibold text-red-600'; mensaje.innerText = error.message; }
        });
}

export function agregarEvidenciaRecurso() {
    const contenedor = document.getElementById('recurso-evidencias-lista-evaluado');
    if (!contenedor) return;
    const fila = document.createElement('div');
    fila.className = 'flex items-start gap-2';
    fila.innerHTML = `
        <div class="grid gap-1.5 flex-1">
            <input type="url" class="recurso-evidencia-url w-full text-xs rounded-xl border border-slate-200 p-2.5 bg-white outline-none focus:border-[#00594E]" placeholder="https://ejemplo.com/evidencia" />
            <input type="text" class="recurso-evidencia-desc w-full text-xs rounded-xl border border-slate-200 p-2.5 bg-white outline-none focus:border-[#00594E]" placeholder="Descripción (opcional)" maxlength="200" />
        </div>
        <button type="button" onclick="eliminarEvidenciaRecurso(this)" class="mt-1 text-red-400 hover:text-red-600 shrink-0" title="Quitar evidencia">
            <span class="material-symbols-outlined text-base">close</span>
        </button>`;
    contenedor.appendChild(fila);
    fila.querySelector('.recurso-evidencia-url')?.focus();
}

export function eliminarEvidenciaRecurso(btn) {
    const fila = btn.closest('div');
    if (fila) fila.remove();
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

export function cargarPlanMejoramientoEvaluado(ev) {
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
            }
            const firmas = document.getElementById('plan-firmas-evaluado');
            if (firmas) firmas.innerHTML = renderFirmasPlan(plan);
        })
        .catch(() => {});
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
                    if (selectedEvaluacionData) cargarPlanMejoramientoEvaluado(selectedEvaluacionData);
                })
                .catch(error => alert(error.message));
        })
        .catch(() => alert('Ocurrió un error al obtener la información del plan.'));
}

export function firmarConcertacion(e, rol) {
    if (!confirm('¿Confirmas firmar la concertación? Una vez que ambas partes firmen, los compromisos y sus porcentajes quedarán bloqueados y no se podrán editar.')) {
        e.preventDefault();
        return false;
    }
    return true;
}

// Global window exposure
window.abrirConcertacionEvaluado = abrirConcertacionEvaluado;
window.cambiarTabEvaluado = cambiarTabEvaluado;
window.radicarRecurso = radicarRecurso;
window.agregarEvidenciaRecurso = agregarEvidenciaRecurso;
window.eliminarEvidenciaRecurso = eliminarEvidenciaRecurso;
window.firmarPlanMejoramiento = firmarPlanMejoramiento;
window.firmarConcertacion = firmarConcertacion;

window.addEventListener('DOMContentLoaded', () => {
    navegarMenu(null, 'evaluaciones');
});
