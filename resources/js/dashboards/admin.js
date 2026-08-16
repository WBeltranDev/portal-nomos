/**
 * Admin Dashboard JS Module
 */
import { escapeHtml, fetchJson, parseErrorMessage, navegarMenu } from './common.js';

export function filtrarEmpleados() {
    const texto = (document.getElementById('buscador-empleados')?.value || '').trim().toLowerCase();
    document.querySelectorAll('.empleado-card').forEach(card => {
        const nombre = card.dataset.nombre || '';
        const cedula = card.dataset.cedula || '';
        const correo = card.dataset.correo || '';
        const match = !texto || nombre.includes(texto) || cedula.includes(texto) || correo.includes(texto);
        card.classList.toggle('hidden', !match);
    });
}

export function seleccionarEmpleado(card, empleado) {
    const setText = (id, value) => {
        const node = document.getElementById(id);
        if (node) node.innerText = value;
    };
    setText('empleado-avatar', (empleado.nombres?.[0] || '') + (empleado.apellidos?.[0] || ''));
    setText('empleado-nombre', `${empleado.nombres || ''} ${empleado.apellidos || ''}`.trim());
    setText('empleado-cargo', empleado.nombre_cargo || 'Sin cargo');
    setText('empleado-correo', empleado.correo_institucional || 'Sin correo');
    setText('empleado-documento', `${empleado.tipo_documento || ''} ${empleado.documento_identidad || ''}`.trim());
    setText('empleado-area', empleado.nombre_area || 'Sin área');
    setText('empleado-estado', empleado.activo ? 'Activo' : 'Inactivo');
    document.querySelectorAll('.empleado-card').forEach(el => el.classList.remove('ring-2', 'ring-[#00594E]'));
    if (card) card.classList.add('ring-2', 'ring-[#00594E]');
}

export function filtrarOpcionesAsignacion(inputId, selectId) {
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

export function contarAsignados() {
    const contador = document.getElementById('contador-asignacion');
    if (!contador) return;
    const total = document.querySelectorAll('#lista-evaluados-asignacion input[name="id_vinc_evaluado[]"]:checked').length;
    contador.innerText = total + (total === 1 ? ' seleccionada' : ' seleccionadas');
}

export function filtrarCheckboxAsignacion() {
    const input = document.getElementById('buscar-evaluado-asignacion');
    const termino = (input?.value || '').trim().toLowerCase();
    document.querySelectorAll('#lista-evaluados-asignacion .checkbox-evaluado').forEach(item => {
        item.hidden = !termino || !item.dataset.buscar.includes(termino);
    });
}

export function mostrarEvaluadorActualTraslado() {
    const select = document.getElementById('select-funcionario-traslado');
    const box = document.getElementById('evaluador-origen-box');
    const textoNode = document.getElementById('evaluador-origen-texto');
    if (!select || !box || !textoNode) return;
    const idVinc = select.value;
    if (!idVinc) {
        box.classList.add('hidden');
        return;
    }
    fetchJson(`/admin/traslados/evaluador-actual/${idVinc}`)
        .then(res => res.json())
        .then(data => {
            if (data) {
                textoNode.innerText = `${data.nombres || ''} ${data.apellidos || ''} — ${data.cargo || 'Sin cargo'} (${data.area || 'Sin área'})`;
            } else {
                textoNode.innerText = 'Sin evaluador asignado';
            }
            box.classList.remove('hidden');
        })
        .catch(() => {
            textoNode.innerText = 'Sin evaluador asignado';
            box.classList.remove('hidden');
        });
}

export function badgeDecisionRecurso(decision) {
    if (decision === 'PENDIENTE') return '<span class="text-[10px] font-bold uppercase rounded-full px-2.5 py-1 bg-amber-50 text-amber-700">Pendiente</span>';
    if (decision === 'APROBADO') return '<span class="text-[10px] font-bold uppercase rounded-full px-2.5 py-1 bg-emerald-50 text-emerald-700">Aprobado</span>';
    return '<span class="text-[10px] font-bold uppercase rounded-full px-2.5 py-1 bg-red-50 text-red-600">Negado</span>';
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
            cargarRecursosAdmin();
        })
        .catch(error => alert(error.message));
}

export function decidirRecursoAdmin(id) {
    const decision = document.getElementById(`decision-admin-${id}`)?.value;
    const motivacion = prompt('Escribe la motivación de la decisión (Talento Humano) sobre el recurso:');
    if (motivacion === null) return;
    if (!motivacion.trim()) { alert('La motivación es obligatoria para decidir el recurso.'); return; }
    enviarDecisionRecurso(id, decision, motivacion);
}

export function cargarRecursosAdmin() {
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
                    ${Array.isArray(r.evidencias) && r.evidencias.length ? `
                    <div class="pt-2 border-t border-slate-100 space-y-1.5">
                        <p class="text-[10px] font-bold uppercase text-slate-500 flex items-center gap-1"><span class="material-symbols-outlined text-xs">link</span> Evidencias</p>
                        ${r.evidencias.map(ev => `
                            <a href="${escapeHtml(ev.url)}" target="_blank" rel="noopener noreferrer" class="flex items-center gap-1.5 text-xs text-[#00594E] hover:underline min-w-0">
                                <span class="material-symbols-outlined text-sm shrink-0">open_in_new</span>
                                <span class="truncate">${escapeHtml(ev.descripcion || ev.url)}</span>
                            </a>`).join('')}
                    </div>` : ''}
                    ${r.decision === 'PENDIENTE' ? `
                    <div class="pt-2 border-t border-slate-100 grid sm:grid-cols-2 gap-2">
                        <select id="decision-admin-${r.id_recurso}" class="w-full text-xs rounded-xl border border-slate-200 p-2 bg-white outline-none focus:border-[#00594E]">
                            <option value="APROBADO">Aprobado</option>
                            <option value="NEGADO">Negado</option>
                        </select>
                        <button onclick="decidirRecursoAdmin(${r.id_recurso})" class="bg-[#00594E] text-white px-3 py-2 rounded-xl text-xs font-bold hover:brightness-110 transition" type="button">Registrar decisión</button>
                    </div>` : ''}
                </div>`).join('');
        })
        .catch(() => {});
}

export function cargarPlanesAdmin() {
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

export function cargarRenuenciasAdmin() {
    const lista = document.getElementById('renuencias-admin-lista');
    if (!lista) return;
    fetchJson('/renuncias')
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
                    ${(r.testigos || []).length ? `
                        <div class="pt-2 border-t border-slate-100 space-y-1">
                            ${(r.testigos || []).map(t => `
                                <p class="text-[10px] text-slate-500">Testigo: <b>${escapeHtml(t.nombre_testigo)}</b> — ${escapeHtml(t.cargo_testigo)}</p>
                            `).join('')}
                        </div>` : ''}
                    ${(r.evidencias || []).length ? `
                        <div class="pt-2 border-t border-slate-100 space-y-1">
                            <p class="text-[10px] font-bold uppercase text-slate-500">Evidencia (acta digitalizada)</p>
                            ${(r.evidencias || []).map(ev => `
                                <a href="${escapeHtml(ev.url)}" target="_blank" rel="noopener noreferrer" class="flex items-center gap-1.5 text-[11px] text-[#00594E] hover:underline min-w-0">
                                    <span class="material-symbols-outlined text-sm shrink-0">open_in_new</span>
                                    <span class="truncate">${escapeHtml(ev.descripcion || ev.url)}</span>
                                </a>`).join('')}
                        </div>` : ''}
                </div>`).join('');
        })
        .catch(() => {});
}

export function cargarTrasladosAdmin() {
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
                    ${t.reemplazado_nombres ? `<p class="text-[10px] text-slate-500">Reemplaza a: <b>${escapeHtml(t.reemplazado_nombres)} ${escapeHtml(t.reemplazado_apellidos)}</b></p>` : ''}
                    ${t.motivo ? `<p class="text-[10px] text-slate-400 italic">${escapeHtml(t.motivo)}</p>` : ''}
                </div>`).join('');
        })
        .catch(() => {
            if (contador) contador.innerText = 'Error';
        });
}

// --- S8: EDICION DE PERIODOS Y AUDITORIA ---
export function abrirEditarPeriodo(btn) {
    const id = btn.dataset.id;
    const setVal = (idNode, value) => {
        const node = document.getElementById(idNode);
        if (node) node.value = value || '';
    };
    setVal('editar-periodo-id', id);
    setVal('editar-periodo-inicio', btn.dataset.inicio);
    setVal('editar-periodo-fin', btn.dataset.fin);
    setVal('editar-periodo-descripcion', btn.dataset.descripcion);
    const estadoSelect = document.getElementById('editar-periodo-estado');
    if (estadoSelect) estadoSelect.value = btn.dataset.estado;

    const titulo = document.getElementById('editar-periodo-titulo');
    if (titulo) titulo.innerText = `${btn.dataset.sistema} · ${btn.dataset.anio} · Semestre ${btn.dataset.semestre}`;

    const form = document.getElementById('form-editar-periodo');
    if (form) form.action = `/admin/periodos/${id}`;

    const panel = document.getElementById('panel-editar-periodo');
    if (panel) panel.classList.remove('hidden');
    const panelAud = document.getElementById('panel-auditoria-periodo');
    if (panelAud) panelAud.classList.add('hidden');
}

export function cerrarEditarPeriodo() {
    const panel = document.getElementById('panel-editar-periodo');
    if (panel) panel.classList.add('hidden');
}

export function verAuditoriaPeriodo(btn) {
    const id = btn.dataset.id;
    const panel = document.getElementById('panel-auditoria-periodo');
    const lista = document.getElementById('auditoria-periodo-lista');
    const titulo = document.getElementById('auditoria-periodo-titulo');
    if (!panel || !lista) return;

    const panelEdit = document.getElementById('panel-editar-periodo');
    if (panelEdit) panelEdit.classList.add('hidden');
    panel.classList.remove('hidden');
    lista.innerHTML = '<div class="text-slate-400 text-center py-4">Cargando auditoría...</div>';

    const accionBadge = (accion) => {
        const clases = {
            CREAR: 'bg-[#EAF2EF] text-[#00594E]',
            EDITAR: 'bg-slate-100 text-slate-600',
            ABRIR: 'bg-emerald-50 text-emerald-700',
            CERRAR: 'bg-red-50 text-red-600',
        };
        return `<span class="text-[9px] font-bold uppercase rounded-full px-2 py-0.5 ${clases[accion] || 'bg-slate-100 text-slate-500'}">${accion}</span>`;
    };

    fetchJson(`/admin/periodos/${id}/auditoria`)
        .then(res => {
            if (!res.ok) throw new Error('HTTP ' + res.status);
            return res.json();
        })
        .then(items => {
            const registros = Array.isArray(items) ? items : [];
            if (titulo) titulo.innerText = `Periodo #${id} · ${registros.length} registro(s)`;
            if (!registros.length) {
                lista.innerHTML = '<div class="text-slate-400 text-center py-4">Sin cambios registrados aún.</div>';
                return;
            }
            lista.innerHTML = registros.map(r => {
                const cambios = r.cambios && typeof r.cambios === 'object'
                    ? Object.entries(r.cambios).map(([campo, detalle]) => {
                        const isDiff = detalle && typeof detalle === 'object' && ('antes' in detalle || 'despues' in detalle);
                        if (isDiff) {
                            const antes = detalle.antes;
                            const despues = detalle.despues;
                            return `<span class="inline-flex items-center gap-1.5 rounded-md bg-slate-50 border border-slate-100 px-2 py-1">
                                <b>${escapeHtml(campo)}</b>:
                                <s class="text-slate-400">${escapeHtml(antes === null || antes === undefined ? '—' : String(antes))}</s>
                                <span class="text-[#00594E] font-bold">→</span>
                                <span>${escapeHtml(despues === null || despues === undefined ? '—' : String(despues))}</span>
                            </span>`;
                        }
                        return `<span class="inline-flex items-center gap-1.5 rounded-md bg-slate-50 border border-slate-100 px-2 py-1">
                            <b>${escapeHtml(campo)}</b>:
                            <span>${escapeHtml(detalle === null || detalle === undefined ? '—' : String(detalle))}</span>
                        </span>`;
                    }).join('')
                    : '';
                return `
                    <div class="rounded-xl border border-slate-100 bg-white p-3 flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">${accionBadge(r.accion)}<span class="text-[10px] text-slate-400">${escapeHtml(r.created_at || '')}</span></div>
                            <div class="mt-1.5 flex flex-wrap gap-1.5">${cambios || '<span class="text-[10px] text-slate-400">Sin detalle</span>'}</div>
                        </div>
                        <span class="text-[10px] text-slate-400 font-medium shrink-0">${escapeHtml(r.usuario_nombre || r.email || 'Admin')}</span>
                    </div>`;
            }).join('');
        })
        .catch(() => {
            lista.innerHTML = '<div class="text-red-500 text-center py-4">No se pudo cargar la auditoría.</div>';
        });
}

// --- S8: DELEGACIONES DE FUNCIONES DEL CARGO ---
export function finalizarDelegacion(id) {
    if (!confirm('¿Finalizar esta delegación? El titular (delegante) retomará sus evaluaciones pendientes y la responsabilidad de la firma final.')) return;
    fetchJson(`/admin/delegaciones/${id}/finalizar`, { method: 'POST' })
        .then(async res => {
            if (!res.ok) {
                const payload = await res.json().catch(() => ({}));
                throw new Error(parseErrorMessage(payload, 'No se pudo finalizar la delegación.'));
            }
            cargarDelegacionesAdmin();
        })
        .catch(error => alert(error.message));
}

export function cargarDelegacionesAdmin() {
    const lista = document.getElementById('delegaciones-admin-lista');
    const contador = document.getElementById('delegaciones-admin-contador');
    if (!lista) return;
    fetchJson('/admin/delegaciones')
        .then(res => res.json())
        .then(delegaciones => {
            const items = Array.isArray(delegaciones) ? delegaciones : [];
            if (contador) contador.innerText = `${items.length} delegacion${items.length === 1 ? '' : 'es'}`;
            if (!items.length) {
                lista.innerHTML = '<div class="rounded-xl border border-dashed border-slate-200 bg-white p-8 text-xs text-slate-500 text-center">No hay delegaciones registradas.</div>';
                return;
            }
            lista.innerHTML = items.map(d => `
                <div class="rounded-2xl border border-slate-100 bg-white p-4 space-y-2">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-xs font-black text-slate-800 truncate">${escapeHtml(d.delegante_nombres || '')} ${escapeHtml(d.delegante_apellidos || '')}</p>
                            <p class="text-[10px] text-slate-400">Titular del cargo · ${escapeHtml(d.delegante_cargo || '')}</p>
                        </div>
                        <span class="text-[10px] font-bold uppercase rounded-full px-2.5 py-1 ${d.estado === 'ACTIVA' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500'}">${d.estado}</span>
                    </div>
                    <div class="flex items-center gap-2 text-xs text-slate-600 rounded-xl border border-slate-100 bg-slate-50/50 p-3">
                        <span class="material-symbols-outlined text-base text-[#B5A160]">arrow_right_alt</span>
                        <div class="min-w-0">
                            <p class="font-bold truncate">${escapeHtml(d.delegado_nombres || '')} ${escapeHtml(d.delegado_apellidos || '')}</p>
                            <p class="text-[10px] text-slate-400 truncate">Delegado · ${escapeHtml(d.delegado_cargo || '')}</p>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-2 text-[10px]">
                        <p class="text-slate-500"><span class="font-bold text-slate-600">Vigencia:</span> ${escapeHtml(d.fecha_inicio || '')} → ${escapeHtml(d.fecha_fin || '')}</p>
                        <p class="text-slate-500"><span class="font-bold text-slate-600">Evaluados:</span> ${Array.isArray(d.detalle_transferencia?.evaluados_transferidos) ? d.detalle_transferencia.evaluados_transferidos.length : 0}</p>
                    </div>
                    ${d.motivo ? `<p class="text-[10px] text-slate-400 italic">${escapeHtml(d.motivo)}</p>` : ''}
                    ${d.estado === 'ACTIVA' ? `
                        <button type="button" onclick="finalizarDelegacion(${d.id_delegacion})" class="w-full bg-red-50 text-red-700 border border-red-100 rounded-xl py-2 text-xs font-bold hover:bg-red-100 transition">Finalizar delegación (el titular retoma)</button>
                    ` : ''}
                </div>`).join('');
        })
        .catch(() => {
            if (contador) contador.innerText = 'Error';
        });
}

// Global window exposure
window.filtrarEmpleados = filtrarEmpleados;
window.seleccionarEmpleado = seleccionarEmpleado;
window.filtrarOpcionesAsignacion = filtrarOpcionesAsignacion;
window.contarAsignados = contarAsignados;
window.filtrarCheckboxAsignacion = filtrarCheckboxAsignacion;
window.mostrarEvaluadorActualTraslado = mostrarEvaluadorActualTraslado;
window.decidirRecursoAdmin = decidirRecursoAdmin;
window.abrirEditarPeriodo = abrirEditarPeriodo;
window.cerrarEditarPeriodo = cerrarEditarPeriodo;
window.verAuditoriaPeriodo = verAuditoriaPeriodo;
window.finalizarDelegacion = finalizarDelegacion;

window.addEventListener('DOMContentLoaded', () => {
    navegarMenu(null, 'usuarios');
    cargarRecursosAdmin();
    cargarPlanesAdmin();
    cargarRenuenciasAdmin();
    cargarTrasladosAdmin();
    cargarDelegacionesAdmin();

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
