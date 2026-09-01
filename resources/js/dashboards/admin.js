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

export function filtrarUsuarios() {
    const texto = (document.getElementById('buscador-usuarios')?.value || '').trim().toLowerCase();
    document.querySelectorAll('.usuario-card').forEach(card => {
        const nombre = card.dataset.nombre || '';
        const cedula = card.dataset.cedula || '';
        const correo = card.dataset.correo || '';
        const match = !texto || nombre.includes(texto) || cedula.includes(texto) || correo.includes(texto);
        card.style.display = match ? 'block' : 'none';
    });
}

export function filtrarJefaturas() {
    const texto = (document.getElementById('buscador-jefaturas')?.value || '').trim().toLowerCase();
    document.querySelectorAll('.jefatura-fila').forEach(fila => {
        const nombre = fila.dataset.nombre || '';
        const cargo = fila.dataset.cargo || '';
        const area = fila.dataset.area || '';
        const match = !texto || nombre.includes(texto) || cargo.includes(texto) || area.includes(texto);
        fila.classList.toggle('hidden', !match);
    });
}

export function guardarJefeSuperior(id) {
    const select = document.getElementById(`jefe-superior-${id}`);
    const valor = select?.value?.trim();
    if (valor === String(id)) { alert('Un cargo no puede ser su propio jefe superior.'); return; }

    const mensaje = valor
        ? '¿Asignar este jefe superior a la vinculación?'
        : '¿Quitar el jefe superior de esta vinculación?';
    if (!confirm(mensaje)) return;

    fetchJson(`/admin/vinculaciones/${id}/jefe`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id_vinc_jefe: valor ? Number(valor) : null }),
    })
        .then(async res => {
            const data = await res.json().catch(() => ({}));
            if (!res.ok) throw new Error(parseErrorMessage(data, 'No se pudo asignar el jefe superior.'));
            alert(data.message || 'Jefe superior asignado.');
        })
        .catch(error => alert(error.message));
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
    setText('empleado-estado', empleado.es_vacante ? 'Vacante' : (empleado.activo ? 'Activo' : 'Inactivo'));
    
    const bloqueAcciones = document.getElementById('bloque-acciones-empleado');
    if (bloqueAcciones) {
        bloqueAcciones.classList.remove('hidden');
        const formDisable = document.getElementById('form-inhabilitar-empleado');
        if (formDisable && empleado.id_funcionario) {
            formDisable.action = `/admin/funcionarios/${empleado.id_funcionario}/disable`;
        }
        const formVacancia = document.getElementById('form-vacancia-empleado');
        const btnVacancia = document.getElementById('btn-toggle-vacancia');
        const inputVacancia = document.getElementById('vacancia-valor');
        if (formVacancia && empleado.id_vinculacion) {
            formVacancia.action = `/admin/vinculaciones/${empleado.id_vinculacion}/vacancia`;
            if (empleado.es_vacante) {
                if (btnVacancia) btnVacancia.innerText = 'Cerrar / Desactivar Vacante';
                if (inputVacancia) inputVacancia.value = '0';
            } else {
                if (btnVacancia) btnVacancia.innerText = 'Declarar Vacante en este Cargo';
                if (inputVacancia) inputVacancia.value = '1';
            }
        }
    }

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

export function autoMarcarSubalternos() {
    const evaluadorSel = document.getElementById('select-evaluador-asignacion');
    const idEvaluador = evaluadorSel?.value || '';
    document.querySelectorAll('#lista-evaluados-asignacion .checkbox-evaluado').forEach(item => {
        const cb = item.querySelector('input[name="id_vinc_evaluado[]"]');
        if (!cb) return;
        cb.checked = !!idEvaluador && item.dataset.jefe === idEvaluador;
    });
    contarAsignados();
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

export function enviarDecisionRecurso(id, decision, motivacion, id_vinc_nuevo_evaluador = null) {
    const bodyParams = { decision, motivacion };
    if (id_vinc_nuevo_evaluador) {
        bodyParams.id_vinc_nuevo_evaluador = id_vinc_nuevo_evaluador;
    }

    fetchJson(`/recursos/${id}/decision`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(bodyParams),
    })
        .then(async res => {
            const payload = await res.json().catch(() => ({}));
            if (!res.ok) throw new Error(parseErrorMessage(payload, 'No se pudo registrar la decisión.'));
            alert(payload.message || 'Decisión registrada.');
            cargarRecursosAdmin();
        })
        .catch(error => alert(error.message));
}

export function decidirRecursoAdmin(id, esApelacion) {
    const decision = document.getElementById(`decision-admin-${id}`)?.value;
    const motivacion = prompt('Escribe la motivación de la decisión sobre el recurso:');
    if (motivacion === null) return;
    if (!motivacion.trim()) { alert('La motivación es obligatoria para decidir el recurso.'); return; }

    let id_vinc_nuevo_evaluador = null;
    if (esApelacion && decision === 'APROBADO') {
        id_vinc_nuevo_evaluador = document.getElementById(`nuevo-evaluador-apelacion-${id}`)?.value;
        if (!id_vinc_nuevo_evaluador) {
            alert('Para aprobar una apelación, debes asignar un evaluador de Talento Humano u otro designado que hará la recalificación.');
            return;
        }
    }

    enviarDecisionRecurso(id, decision, motivacion, id_vinc_nuevo_evaluador);
}

export function toggleNuevoEvaluador(id) {
    const select = document.getElementById(`decision-admin-${id}`);
    const box = document.getElementById(`box-nuevo-evaluador-${id}`);
    if (box) {
        if (select?.value === 'APROBADO') {
            box.classList.remove('hidden');
        } else {
            box.classList.add('hidden');
        }
    }
}

export function cargarRecursosAdmin() {
    const lista = document.getElementById('recursos-admin-lista');
    const contador = document.getElementById('recursos-admin-contador');
    if (!lista) return;
    
    const evaluadores = window.APP_CONFIG?.evaluadores || [];
    const options = evaluadores.map(e => `<option value="${e.id_vinculacion}">${escapeHtml(e.nombre_completo)} - ${escapeHtml(e.cargo)}</option>`).join('');

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
                    <div class="pt-2 border-t border-slate-100 space-y-2">
                        <div class="grid sm:grid-cols-2 gap-2">
                            <select id="decision-admin-${r.id_recurso}" onchange="toggleNuevoEvaluador(${r.id_recurso})" class="w-full text-xs rounded-xl border border-slate-200 p-2 bg-white outline-none focus:border-[#00594E]">
                                <option value="APROBADO">Aprobado (Declarar fundado)</option>
                                <option value="NEGADO" selected>Negado (Declarar infundado)</option>
                            </select>
                            <button onclick="decidirRecursoAdmin(${r.id_recurso}, ${r.tipo_recurso === 'APELACION'})" class="bg-[#00594E] text-white px-3 py-2 rounded-xl text-xs font-bold hover:brightness-110 transition" type="button">Registrar decisión</button>
                        </div>
                        ${r.tipo_recurso === 'APELACION' ? `
                        <div id="box-nuevo-evaluador-${r.id_recurso}" class="hidden mt-2 border-t border-slate-100 pt-2">
                            <label class="block text-[10px] font-bold uppercase text-slate-500 mb-1">Nuevo evaluador asignado (Talento Humano)</label>
                            <select id="nuevo-evaluador-apelacion-${r.id_recurso}" class="w-full text-xs rounded-xl border border-slate-200 p-2 bg-white outline-none focus:border-[#00594E]">
                                <option value="">-- Seleccionar evaluador para recalificar --</option>
                                ${options}
                            </select>
                        </div>` : ''}
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
    setVal('editar-periodo-inicio-concertacion', btn.dataset.inicio_concertacion);
    setVal('editar-periodo-fin-concertacion', btn.dataset.fin_concertacion);
    setVal('editar-periodo-descripcion', btn.dataset.descripcion);
    const estadoSelect = document.getElementById('editar-periodo-estado');
    if (estadoSelect) estadoSelect.value = btn.dataset.estado;

    const titulo = document.getElementById('editar-periodo-titulo');
    if (titulo) titulo.innerText = `${btn.dataset.sistema} · ${btn.dataset.anio}-${Number(btn.dataset.semestre) === 1 ? 'A' : 'B'}`;

    const form = document.getElementById('form-editar-periodo');
    if (form) form.action = `/admin/periodos/${id}`;

    const panel = document.getElementById('panel-editar-periodo');
    if (panel) panel.classList.remove('hidden');
    const panelAud = document.getElementById('panel-auditoria-periodo');
    if (panelAud) panelAud.classList.add('hidden');
}

export function cerrarEditarPeriodo() {
    const modal = document.getElementById('modal-editar-periodo');
    if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    // Compatibilidad con el panel usado por versiones anteriores del tablero.
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
let delegacionesCache = [];

export function calcularDiasRestantes(fechaFinStr) {
    if (!fechaFinStr) return null;
    const parts = String(fechaFinStr).substring(0, 10).split('-');
    if (parts.length !== 3) return null;
    const year = parseInt(parts[0], 10);
    const month = parseInt(parts[1], 10) - 1;
    const day = parseInt(parts[2], 10);

    const fin = new Date(year, month, day);
    fin.setHours(0, 0, 0, 0);

    const now = new Date();
    const hoy = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    hoy.setHours(0, 0, 0, 0);

    const diffMs = fin.getTime() - hoy.getTime();
    return Math.round(diffMs / (1000 * 60 * 60 * 24));
}

export function abrirModalEditarDelegacion(id) {
    const d = delegacionesCache.find(x => x.id_delegacion === id || x.id_delegacion === Number(id));
    if (!d) return;

    const modal = document.getElementById('modal-editar-delegacion');
    if (!modal) return;

    document.getElementById('edit-delegacion-id').value = d.id_delegacion;
    document.getElementById('edit-delegante-nombre').innerText = `${d.delegante_nombres || ''} ${d.delegante_apellidos || ''} (${d.delegante_cargo || 'Titular'})`;
    document.getElementById('edit-delegado-nombre').innerText = `${d.delegado_nombres || ''} ${d.delegado_apellidos || ''} (${d.delegado_cargo || 'Delegado'})`;
    document.getElementById('edit-fecha-inicio').value = d.fecha_inicio ? String(d.fecha_inicio).substring(0, 10) : '';
    document.getElementById('edit-fecha-fin').value = d.fecha_fin ? String(d.fecha_fin).substring(0, 10) : '';
    document.getElementById('edit-motivo').value = d.motivo || '';
    document.getElementById('edit-acto-administrativo').value = d.acto_administrativo || '';
    document.getElementById('edit-acto-numero').value = d.acto_administrativo_numero || '';
    document.getElementById('edit-acto-fecha').value = d.acto_administrativo_fecha ? String(d.acto_administrativo_fecha).substring(0, 10) : '';
    document.getElementById('edit-acto-url').value = d.acto_administrativo_url || '';

    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

export function cerrarModalEditarDelegacion() {
    const modal = document.getElementById('modal-editar-delegacion');
    if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }
}

export function guardarEdicionDelegacion(event) {
    event.preventDefault();
    const id = document.getElementById('edit-delegacion-id').value;
    if (!id) return;

    const btn = document.getElementById('btn-guardar-edicion-delegacion');
    const originalText = btn ? btn.innerHTML : '';
    if (btn) {
        btn.disabled = true;
        btn.innerText = 'Guardando...';
    }

    const payload = {
        fecha_fin: document.getElementById('edit-fecha-fin').value || null,
        motivo: document.getElementById('edit-motivo').value || null,
        acto_administrativo: document.getElementById('edit-acto-administrativo').value || null,
        acto_administrativo_numero: document.getElementById('edit-acto-numero').value || null,
        acto_administrativo_fecha: document.getElementById('edit-acto-fecha').value || null,
        acto_administrativo_url: document.getElementById('edit-acto-url').value || null,
    };

    fetchJson(`/admin/delegaciones/${id}`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
        .then(async res => {
            const data = await res.json().catch(() => ({}));
            if (!res.ok) {
                throw new Error(parseErrorMessage(data, 'No se pudo actualizar la delegación.'));
            }
            cerrarModalEditarDelegacion();
            cargarDelegacionesAdmin();
        })
        .catch(error => {
            alert(error.message);
        })
        .finally(() => {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        });
}

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

export function renderNotificacionesDelegaciones(items) {
    const container = document.getElementById('delegaciones-alertas-container');
    const sidebarBadge = document.getElementById('sidebar-delegaciones-badge');

    const alertas1Dia = items.filter(d => d.estado === 'ACTIVA' && d.dias_restantes === 1);
    const alertasHoy = items.filter(d => d.estado === 'ACTIVA' && d.dias_restantes === 0);
    const alertasVencidas = items.filter(d => d.estado === 'ACTIVA' && d.dias_restantes !== null && d.dias_restantes < 0);

    // Actualizar badge en la barra lateral
    if (sidebarBadge) {
        const totalAvisos = alertas1Dia.length + alertasHoy.length + alertasVencidas.length;
        if (totalAvisos > 0) {
            sidebarBadge.innerText = alertas1Dia.length > 0 ? `${alertas1Dia.length} mañana` : `${totalAvisos}`;
            sidebarBadge.className = 'ml-auto px-2 py-0.5 text-[10px] font-black rounded-full bg-amber-500 text-white shadow-sm animate-pulse';
            sidebarBadge.classList.remove('hidden');
        } else {
            sidebarBadge.classList.add('hidden');
        }
    }

    if (!container) return;

    // Si no hay alertas de ningún tipo
    if (alertas1Dia.length === 0 && alertasHoy.length === 0 && alertasVencidas.length === 0) {
        container.innerHTML = `
            <div class="rounded-3xl border border-slate-200 bg-white/90 backdrop-blur-sm p-4 sm:p-5 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="flex items-center gap-3.5">
                    <div class="w-10 h-10 rounded-2xl bg-[#EAF2EF] text-[#00594E] flex items-center justify-center font-black shrink-0">
                        <span class="material-symbols-outlined text-2xl">event_available</span>
                    </div>
                    <div>
                        <h4 class="text-sm font-black text-slate-900">Estado de delegaciones: Sin retornos urgentes</h4>
                        <p class="text-xs text-slate-500">No hay funcionarios a 1 día de cumplir vigencia para volver al trabajo. Las delegaciones activas están al día.</p>
                    </div>
                </div>
                <span class="inline-flex items-center gap-1.5 text-[10px] font-extrabold uppercase rounded-full px-3 py-1.5 bg-[#EAF2EF] text-[#00594E] border border-[#00594E]/20 self-start sm:self-auto">
                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span> Todo al día
                </span>
            </div>
        `;
        return;
    }

    let html = '';

    // Prioridad 1: Notificaciones de 1 DÍA para vencer (Requerimiento Principal)
    if (alertas1Dia.length > 0) {
        html += `
            <div class="rounded-3xl border-2 border-amber-400 bg-gradient-to-br from-amber-50/95 via-amber-50/40 to-white p-5 sm:p-6 shadow-lg shadow-amber-500/10 space-y-4">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-amber-200/80 pb-4">
                    <div class="flex items-center gap-3">
                        <div class="w-11 h-11 rounded-2xl bg-amber-500 text-white flex items-center justify-center shadow-md shadow-amber-500/30 shrink-0 animate-bounce">
                            <span class="material-symbols-outlined text-2xl">notification_important</span>
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="text-base sm:text-lg font-black text-amber-950">Notificación de Retorno: Vencimiento en 1 Día</h3>
                                <span class="bg-amber-500 text-white text-[10px] font-black uppercase px-2 py-0.5 rounded-full shadow-sm">Urgente</span>
                            </div>
                            <p class="text-xs text-amber-800 mt-0.5">Los siguientes funcionarios titulares están a <b>1 día de vencer</b> su periodo de delegación y deben retornar a sus labores y evaluaciones.</p>
                        </div>
                    </div>
                    <span class="inline-flex items-center gap-1.5 text-xs font-black rounded-full px-3.5 py-1.5 bg-amber-100 text-amber-900 border border-amber-300 self-start sm:self-auto">
                        <span class="material-symbols-outlined text-sm">alarm</span> ${alertas1Dia.length} caso${alertas1Dia.length === 1 ? '' : 's'} por vencer mañana
                    </span>
                </div>

                <div class="grid gap-3.5 sm:grid-cols-1 ${alertas1Dia.length > 1 ? 'lg:grid-cols-2' : ''}">
                    ${alertas1Dia.map(d => {
                        const cantEvaluados = Array.isArray(d.detalle_transferencia?.evaluados_transferidos) ? d.detalle_transferencia.evaluados_transferidos.length : 0;
                        const actoHtml = (d.acto_administrativo || d.acto_administrativo_numero || d.acto_administrativo_url) ? `
                            <div class="rounded-xl border border-blue-100 bg-blue-50/60 p-2.5 text-[11px] text-blue-950 space-y-1">
                                <div class="flex items-center justify-between gap-2 flex-wrap">
                                    <div class="flex items-center gap-1.5 font-bold">
                                        <span class="material-symbols-outlined text-sm text-blue-700">description</span>
                                        <span>${escapeHtml(d.acto_administrativo || 'Acto Administrativo')}${d.acto_administrativo_numero ? ': ' + escapeHtml(d.acto_administrativo_numero) : ''}</span>
                                    </div>
                                    ${d.acto_administrativo_url ? `
                                        <a href="${escapeHtml(d.acto_administrativo_url)}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 text-[10px] font-extrabold text-blue-700 hover:text-blue-900 bg-white px-2 py-0.5 rounded-md border border-blue-200">
                                            <span class="material-symbols-outlined text-xs">open_in_new</span> Ver en Drive
                                        </a>
                                    ` : ''}
                                </div>
                            </div>
                        ` : '';
                        return `
                            <div class="rounded-2xl border border-amber-200 bg-white p-4 space-y-3 shadow-sm hover:shadow-md transition">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-2 mb-1.5">
                                            <span class="text-[10px] font-extrabold uppercase rounded-md px-2 py-0.5 bg-amber-100 text-amber-900 border border-amber-200 flex items-center gap-1">
                                                <span class="material-symbols-outlined text-[12px]">schedule</span> Vence mañana (${escapeHtml(d.fecha_fin_formateada || d.fecha_fin)})
                                            </span>
                                        </div>
                                        <h4 class="text-sm font-black text-slate-900 leading-snug">👤 ${escapeHtml(d.delegante_nombres || '')} ${escapeHtml(d.delegante_apellidos || '')}</h4>
                                        <p class="text-xs font-medium text-slate-500">Titular del cargo · ${escapeHtml(d.delegante_cargo || 'Funcionario')}${d.delegante_area ? ' · ' + escapeHtml(d.delegante_area) : ''}</p>
                                    </div>
                                    <div class="text-right shrink-0">
                                        <span class="text-xs font-black text-amber-700 bg-amber-50 border border-amber-200 px-2.5 py-1 rounded-xl block">1 día restante</span>
                                    </div>
                                </div>

                                <div class="rounded-xl border border-slate-100 bg-slate-50/80 p-3 space-y-2 text-xs">
                                    <div class="flex items-center gap-2 text-slate-700">
                                        <span class="material-symbols-outlined text-base text-[#00594E]">supervised_user_circle</span>
                                        <span class="min-w-0 font-medium">Delegado que asumió: <b>${escapeHtml(d.delegado_nombres || '')} ${escapeHtml(d.delegado_apellidos || '')}</b> (${escapeHtml(d.delegado_cargo || '')})</span>
                                    </div>
                                    <div class="grid grid-cols-2 gap-2 text-[11px] text-slate-600 border-t border-slate-200/60 pt-2">
                                        <div><span class="font-bold text-slate-700">Vigencia:</span> ${escapeHtml(d.fecha_inicio_formateada || d.fecha_inicio)} al ${escapeHtml(d.fecha_fin_formateada || d.fecha_fin)}</div>
                                        <div><span class="font-bold text-slate-700">Evaluados temporales:</span> ${cantEvaluados} persona${cantEvaluados === 1 ? '' : 's'}</div>
                                    </div>
                                    ${d.motivo ? `<div class="text-[11px] text-slate-500 italic border-t border-slate-200/60 pt-1.5"><span class="font-bold not-italic text-slate-600">Motivo:</span> "${escapeHtml(d.motivo)}"</div>` : ''}
                                </div>

                                ${actoHtml}

                                <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-2 pt-1">
                                    <button type="button" onclick="abrirModalEditarDelegacion(${d.id_delegacion})" class="inline-flex items-center justify-center gap-1 px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-bold text-xs transition shrink-0">
                                        <span class="material-symbols-outlined text-sm">edit_calendar</span> Editar vigencia
                                    </button>
                                    <button type="button" onclick="finalizarDelegacion(${d.id_delegacion})" class="inline-flex items-center justify-center gap-1.5 px-3.5 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-xl font-bold text-xs shadow-sm transition shrink-0">
                                        <span class="material-symbols-outlined text-sm">assignment_return</span> Finalizar delegación ahora
                                    </button>
                                </div>
                            </div>
                        `;
                    }).join('')}
                </div>
            </div>
        `;
    }

    // Alertas complementarias: Vencen hoy o vencidas pendientes de cierre
    if (alertasHoy.length > 0 || alertasVencidas.length > 0) {
        const otros = [...alertasHoy, ...alertasVencidas];
        html += `
            <div class="rounded-3xl border border-rose-200 bg-rose-50/50 p-5 sm:p-6 shadow-sm space-y-3">
                <div class="flex items-center justify-between gap-3">
                    <div class="flex items-center gap-2.5">
                        <span class="material-symbols-outlined text-rose-600 text-xl">warning</span>
                        <h4 class="text-sm font-black text-rose-950">Otras delegaciones pendientes de retorno / cierre (${otros.length})</h4>
                    </div>
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    ${otros.map(d => `
                        <div class="rounded-2xl border border-rose-200/80 bg-white p-3.5 text-xs space-y-2 shadow-sm">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="font-bold text-slate-900 truncate">👤 ${escapeHtml(d.delegante_nombres || '')} ${escapeHtml(d.delegante_apellidos || '')}</p>
                                    <p class="text-[10px] text-slate-500 truncate">${escapeHtml(d.delegante_cargo || '')}${d.delegante_area ? ' · ' + escapeHtml(d.delegante_area) : ''}</p>
                                </div>
                                <span class="text-[10px] font-black uppercase rounded-full px-2 py-0.5 ${d.dias_restantes === 0 ? 'bg-orange-100 text-orange-800 border border-orange-200' : 'bg-rose-100 text-rose-800 border border-rose-200'} shrink-0">
                                    ${d.dias_restantes === 0 ? 'Vence hoy' : `Vencida (${Math.abs(d.dias_restantes)}d)`}
                                </span>
                            </div>
                            <p class="text-[11px] text-slate-600">Fin vigencia: <b>${escapeHtml(d.fecha_fin_formateada || d.fecha_fin)}</b> · Delegado: ${escapeHtml(d.delegado_nombres || '')}</p>
                            <div class="grid grid-cols-2 gap-2 pt-1">
                                <button type="button" onclick="abrirModalEditarDelegacion(${d.id_delegacion})" class="w-full text-center py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-bold text-[11px] transition flex items-center justify-center gap-1">
                                    <span class="material-symbols-outlined text-xs">edit_calendar</span> Editar
                                </button>
                                <button type="button" onclick="finalizarDelegacion(${d.id_delegacion})" class="w-full text-center py-1.5 bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 rounded-xl font-bold text-[11px] transition">
                                    Finalizar
                                </button>
                            </div>
                        </div>
                    `).join('')}
                </div>
            </div>
        `;
    }

    container.innerHTML = html;
}

export function cargarDelegacionesAdmin() {
    const lista = document.getElementById('delegaciones-admin-lista');
    const contador = document.getElementById('delegaciones-admin-contador');
    if (!lista) return;
    fetchJson('/admin/delegaciones')
        .then(res => res.json())
        .then(delegaciones => {
            const rawItems = Array.isArray(delegaciones) ? delegaciones : [];
            const items = rawItems.map(d => {
                const dias = (typeof d.dias_restantes === 'number' || d.dias_restantes === null) ? d.dias_restantes : calcularDiasRestantes(d.fecha_fin);
                return { ...d, dias_restantes: dias };
            });

            delegacionesCache = items;

            // Renderizar notificaciones de retorno arriba
            renderNotificacionesDelegaciones(items);

            if (contador) contador.innerText = `${items.length} delegacion${items.length === 1 ? '' : 'es'}`;
            if (!items.length) {
                lista.innerHTML = '<div class="rounded-xl border border-dashed border-slate-200 bg-white p-8 text-xs text-slate-500 text-center">No hay delegaciones registradas.</div>';
                return;
            }

            lista.innerHTML = items.map(d => {
                const es1Dia = d.estado === 'ACTIVA' && d.dias_restantes === 1;
                const esHoy = d.estado === 'ACTIVA' && d.dias_restantes === 0;
                const esVencida = d.estado === 'ACTIVA' && d.dias_restantes !== null && d.dias_restantes < 0;
                const esAbierta = d.estado === 'ACTIVA' && (d.fecha_fin === null || d.dias_restantes === null);

                let badgeEstado = '';
                let borderCls = 'border-slate-100 bg-white';
                if (d.estado === 'FINALIZADA') {
                    badgeEstado = '<span class="text-[10px] font-bold uppercase rounded-full px-2.5 py-1 bg-slate-100 text-slate-500">FINALIZADA</span>';
                } else if (es1Dia) {
                    borderCls = 'border-amber-300 bg-amber-50/30 ring-2 ring-amber-200/60 shadow-sm';
                    badgeEstado = '<span class="text-[10px] font-extrabold uppercase rounded-full px-2.5 py-1 bg-amber-100 text-amber-800 border border-amber-300 flex items-center gap-1"><span class="material-symbols-outlined text-xs">alarm</span> Vence en 1 día</span>';
                } else if (esHoy) {
                    borderCls = 'border-orange-300 bg-orange-50/30 ring-1 ring-orange-200';
                    badgeEstado = '<span class="text-[10px] font-extrabold uppercase rounded-full px-2.5 py-1 bg-orange-100 text-orange-800">Vence hoy</span>';
                } else if (esVencida) {
                    borderCls = 'border-rose-300 bg-rose-50/30 ring-1 ring-rose-200';
                    badgeEstado = `<span class="text-[10px] font-extrabold uppercase rounded-full px-2.5 py-1 bg-rose-100 text-rose-800">Vencida (${Math.abs(d.dias_restantes)}d)</span>`;
                } else if (esAbierta) {
                    badgeEstado = `<span class="text-[10px] font-bold uppercase rounded-full px-2.5 py-1 bg-emerald-50 text-emerald-700 border border-emerald-200">ACTIVA (Abierta)</span>`;
                } else {
                    badgeEstado = `<span class="text-[10px] font-bold uppercase rounded-full px-2.5 py-1 bg-emerald-50 text-emerald-700">ACTIVA (${d.dias_restantes}d)</span>`;
                }

                const vigenciaStr = d.fecha_fin
                    ? `${escapeHtml(d.fecha_inicio_formateada || d.fecha_inicio)} → ${escapeHtml(d.fecha_fin_formateada || d.fecha_fin)}`
                    : `${escapeHtml(d.fecha_inicio_formateada || d.fecha_inicio)} → <span class="text-emerald-700 font-semibold">Vigencia abierta</span>`;

                const actoHtml = (d.acto_administrativo || d.acto_administrativo_numero || d.acto_administrativo_url) ? `
                    <div class="rounded-xl border border-blue-100 bg-blue-50/60 p-2.5 text-[11px] text-blue-950 space-y-1">
                        <div class="flex items-center justify-between gap-2 flex-wrap">
                            <div class="flex items-center gap-1.5 font-bold">
                                <span class="material-symbols-outlined text-sm text-blue-700">description</span>
                                <span>${escapeHtml(d.acto_administrativo || 'Acto')} ${d.acto_administrativo_numero ? 'No. ' + escapeHtml(d.acto_administrativo_numero) : ''}</span>
                                ${d.acto_administrativo_fecha ? `<span class="text-blue-600 font-normal">(${escapeHtml(d.acto_administrativo_fecha_formateada || d.acto_administrativo_fecha)})</span>` : ''}
                            </div>
                            ${d.acto_administrativo_url ? `
                                <a href="${escapeHtml(d.acto_administrativo_url)}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 text-[10px] font-extrabold text-blue-700 hover:text-blue-900 bg-white px-2 py-0.5 rounded-md border border-blue-200 shadow-2xs">
                                    <span class="material-symbols-outlined text-xs">open_in_new</span> Drive
                                </a>
                            ` : ''}
                        </div>
                    </div>
                ` : '';

                return `
                    <div class="rounded-2xl border ${borderCls} p-4 space-y-2.5 transition">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-xs font-black text-slate-800 truncate">${escapeHtml(d.delegante_nombres || '')} ${escapeHtml(d.delegante_apellidos || '')}</p>
                                <p class="text-[10px] text-slate-400">Titular · ${escapeHtml(d.delegante_cargo || '')}${d.delegante_area ? ' · ' + escapeHtml(d.delegante_area) : ''}</p>
                            </div>
                            ${badgeEstado}
                        </div>
                        <div class="flex items-center gap-2 text-xs text-slate-600 rounded-xl border border-slate-100 bg-slate-50/50 p-3">
                            <span class="material-symbols-outlined text-base text-[#B5A160]">arrow_right_alt</span>
                            <div class="min-w-0">
                                <p class="font-bold truncate">${escapeHtml(d.delegado_nombres || '')} ${escapeHtml(d.delegado_apellidos || '')}</p>
                                <p class="text-[10px] text-slate-400 truncate">Delegado · ${escapeHtml(d.delegado_cargo || '')}${d.delegado_nivel ? ' (' + escapeHtml(d.delegado_nivel) + ')' : ''}</p>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-2 text-[10px]">
                            <p class="text-slate-500"><span class="font-bold text-slate-600">Vigencia:</span> ${vigenciaStr}</p>
                            <p class="text-slate-500"><span class="font-bold text-slate-600">Evaluados:</span> ${Array.isArray(d.detalle_transferencia?.evaluados_transferidos) ? d.detalle_transferencia.evaluados_transferidos.length : 0}</p>
                        </div>
                        ${d.motivo ? `<p class="text-[10px] text-slate-500 italic bg-slate-50 p-2 rounded-lg"><span class="font-bold not-italic text-slate-600">Motivo:</span> "${escapeHtml(d.motivo)}"</p>` : ''}
                        ${actoHtml}
                        ${d.estado === 'ACTIVA' ? `
                            <div class="grid grid-cols-2 gap-2 pt-1">
                                <button type="button" onclick="abrirModalEditarDelegacion(${d.id_delegacion})" class="w-full bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl py-2 text-xs font-bold transition flex items-center justify-center gap-1">
                                    <span class="material-symbols-outlined text-sm">edit_calendar</span> Editar vigencia
                                </button>
                                <button type="button" onclick="finalizarDelegacion(${d.id_delegacion})" class="w-full ${es1Dia ? 'bg-amber-600 hover:bg-amber-700 text-white shadow-sm' : 'bg-red-50 text-red-700 border border-red-100 hover:bg-red-100'} rounded-xl py-2 text-xs font-bold transition">
                                    Finalizar
                                </button>
                            </div>
                        ` : ''}
                    </div>
                `;
            }).join('');
        })
        .catch(() => {
            if (contador) contador.innerText = 'Error';
        });
}

// Global window exposure
window.filtrarEmpleados = filtrarEmpleados;
window.filtrarUsuarios = filtrarUsuarios;
window.filtrarJefaturas = filtrarJefaturas;
window.guardarJefeSuperior = guardarJefeSuperior;
window.seleccionarEmpleado = seleccionarEmpleado;
window.filtrarOpcionesAsignacion = filtrarOpcionesAsignacion;
window.contarAsignados = contarAsignados;
window.filtrarCheckboxAsignacion = filtrarCheckboxAsignacion;
window.autoMarcarSubalternos = autoMarcarSubalternos;
window.mostrarEvaluadorActualTraslado = mostrarEvaluadorActualTraslado;
window.decidirRecursoAdmin = decidirRecursoAdmin;
window.abrirEditarPeriodo = abrirEditarPeriodo;
window.cerrarEditarPeriodo = cerrarEditarPeriodo;
window.verAuditoriaPeriodo = verAuditoriaPeriodo;
window.finalizarDelegacion = finalizarDelegacion;
window.cargarDelegacionesAdmin = cargarDelegacionesAdmin;
window.abrirModalEditarDelegacion = abrirModalEditarDelegacion;
window.cerrarModalEditarDelegacion = cerrarModalEditarDelegacion;
window.guardarEdicionDelegacion = guardarEdicionDelegacion;

window.addEventListener('DOMContentLoaded', () => {
    navegarMenu(null, 'usuarios');
    cargarRecursosAdmin();
    cargarPlanesAdmin();
    cargarRenuenciasAdmin();
    cargarTrasladosAdmin();
    cargarDelegacionesAdmin();
    cargarImpedimentosAdmin();
    cargarSolicitudesModificacion();

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

export function cargarImpedimentosAdmin() {
    const lista = document.getElementById('impedimentos-admin-lista');
    if (!lista) return;

    const impedimentos = window.APP_CONFIG?.impedimentos || [];

    if (!impedimentos.length) {
        lista.innerHTML = '<div class="col-span-full rounded-xl border border-dashed border-slate-200 bg-white p-8 text-xs text-slate-500 text-center">No hay impedimentos o recusaciones registrados.</div>';
        return;
    }

    const evaluadores = window.APP_CONFIG?.evaluadores || [];
    const options = evaluadores.map(e => `<option value="${e.id_vinculacion}">${escapeHtml(e.nombre_completo)} - ${escapeHtml(e.cargo)}</option>`).join('');

    lista.innerHTML = impedimentos.map(r => {
        const fechaPresentacion = r.created_at ? new Date(r.created_at).toLocaleDateString('es-CO', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' }) : '';
        return `
        <div class="rounded-2xl border border-slate-100 bg-white p-4 space-y-2 relative">
            <div class="flex items-center justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-xs font-black text-slate-800 truncate">${r.tipo === 'IMPEDIMENTO' ? 'Impedimento' : 'Recusación'}</p>
                    <p class="text-[10px] text-slate-400">Solicitante: ${escapeHtml(r.solicitante_nombres || '')} ${escapeHtml(r.solicitante_apellidos || '')}</p>
                    ${fechaPresentacion ? `<p class="text-[10px] text-slate-400 mt-0.5">Presentado: ${fechaPresentacion}</p>` : ''}
                </div>
                ${r.estado === 'PENDIENTE' ? '<span class="text-[10px] font-bold uppercase rounded-full px-2.5 py-1 bg-amber-50 text-amber-700">Pendiente</span>' : ''}
                ${r.estado === 'APROBADO' ? '<span class="text-[10px] font-bold uppercase rounded-full px-2.5 py-1 bg-emerald-50 text-emerald-700">Aprobado</span>' : ''}
                ${r.estado === 'RECHAZADO' ? '<span class="text-[10px] font-bold uppercase rounded-full px-2.5 py-1 bg-red-50 text-red-600">Rechazado</span>' : ''}
            </div>
            <p class="text-xs text-slate-600 whitespace-pre-wrap mt-2"><b>Motivo:</b> ${escapeHtml(r.motivo || '')}</p>
            ${r.evidencia_url ? `
                <a href="${escapeHtml(r.evidencia_url)}" target="_blank" rel="noopener noreferrer" class="flex items-center gap-1.5 text-xs text-[#00594E] hover:underline min-w-0 mt-2">
                    <span class="material-symbols-outlined text-sm shrink-0">open_in_new</span>
                    <span class="truncate">Ver Evidencia Anexa</span>
                </a>` : ''}

            ${r.estado === 'PENDIENTE' ? `
            <div class="mt-4 pt-3 border-t border-slate-100 space-y-3">
                <div>
                    <label class="block text-[10px] font-bold uppercase text-slate-500 mb-1">Respuesta (Talento Humano)</label>
                    <textarea id="respuesta-impedimento-${r.id_impedimento}" class="w-full text-xs rounded-xl border border-slate-200 p-2 bg-white outline-none focus:border-[#00594E]" rows="2" placeholder="Argumento de la decisión..."></textarea>
                </div>
                <div id="reasingar-box-${r.id_impedimento}" class="hidden">
                    <label class="block text-[10px] font-bold uppercase text-slate-500 mb-1">Reasignar a Evaluador Alterno</label>
                    <select id="reasignar-impedimento-${r.id_impedimento}" class="w-full text-xs rounded-xl border border-slate-200 p-2 bg-white outline-none focus:border-[#00594E]">
                        <option value="">-- Seleccionar nuevo evaluador --</option>
                        ${options}
                    </select>
                </div>
                <div class="flex gap-2">
                    <button type="button" onclick="document.getElementById('reasingar-box-${r.id_impedimento}').classList.remove('hidden'); this.style.display='none'; document.getElementById('btn-aprobar-${r.id_impedimento}').classList.remove('hidden');" class="flex-1 text-center py-2 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border border-emerald-200 rounded-xl font-bold text-xs transition">
                        Aprobar Solicitud
                    </button>
                    <button id="btn-aprobar-${r.id_impedimento}" type="button" onclick="decidirImpedimentoAdmin(${r.id_impedimento}, 'APROBADO')" class="hidden flex-1 text-center py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl font-bold text-xs transition">
                        Confirmar y Reasignar
                    </button>
                    <button type="button" onclick="decidirImpedimentoAdmin(${r.id_impedimento}, 'RECHAZADO')" class="flex-1 text-center py-2 bg-red-50 hover:bg-red-100 text-red-700 border border-red-200 rounded-xl font-bold text-xs transition">
                        Rechazar Solicitud
                    </button>
                </div>
            </div>` : `
            <div class="mt-3 pt-2 border-t border-slate-100">
                <p class="text-[10px] font-bold uppercase text-slate-500">Respuesta</p>
                <p class="text-xs text-slate-600 whitespace-pre-wrap">${escapeHtml(r.respuesta_admin || '')}</p>
            </div>
            `}
        </div>`;
    }).join('');
}

export function decidirImpedimentoAdmin(id, estado) {
    const respuesta = document.getElementById(`respuesta-impedimento-${id}`)?.value?.trim();
    if (!respuesta) {
        alert('Debe justificar la decisión (Respuesta obligatoria).');
        return;
    }

    const payload = { estado, respuesta };

    if (estado === 'APROBADO') {
        const reasignar = document.getElementById(`reasignar-impedimento-${id}`)?.value;
        if (!reasignar) {
            alert('Debe seleccionar el nuevo evaluador.');
            return;
        }
        payload.id_vinc_nuevo_evaluador = reasignar;
    }

    if (!confirm('¿Confirma guardar la decisión y notificar a los implicados?')) return;

    fetchJson(`/admin/impedimentos/${id}/resolver`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(async res => {
        const data = await res.json().catch(() => ({}));
        if (!res.ok) throw new Error(parseErrorMessage(data, 'No se pudo resolver la solicitud.'));
        alert('Solicitud resuelta con éxito.');
        window.location.reload();
    })
    .catch(err => alert(err.message));
}

window.cargarImpedimentosAdmin = cargarImpedimentosAdmin;
window.decidirImpedimentoAdmin = decidirImpedimentoAdmin;

export function cargarSolicitudesModificacion() {
    const lista = document.getElementById('solicitudes-modificacion-lista');
    if (!lista) return;

    fetchJson('/admin/compromisos/solicitudes')
        .then(res => res.json())
        .then(data => {
            const solicitudes = data.solicitudes || [];

            if (!solicitudes.length) {
                lista.innerHTML = '<div class="col-span-full rounded-xl border border-dashed border-slate-200 bg-white p-8 text-xs text-slate-500 text-center">No hay solicitudes de modificación de compromisos.</div>';
                return;
            }

            lista.innerHTML = solicitudes.map(s => {
                const detalle = s.detalle_cambio || {};
                const fecha = s.created_at ? new Date(s.created_at).toLocaleDateString('es-CO', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' }) : '';
                const estadoBadge = s.estado === 'PENDIENTE'
                    ? '<span class="text-[10px] font-bold uppercase rounded-full px-2.5 py-1 bg-amber-50 text-amber-700">Pendiente</span>'
                    : s.estado === 'APROBADO'
                        ? '<span class="text-[10px] font-bold uppercase rounded-full px-2.5 py-1 bg-emerald-50 text-emerald-700">Aprobado</span>'
                        : '<span class="text-[10px] font-bold uppercase rounded-full px-2.5 py-1 bg-red-50 text-red-600">Rechazado</span>';

                const bloqueDecision = s.estado === 'PENDIENTE' ? `
                    <div class="mt-4 pt-3 border-t border-slate-100 space-y-3">
                        <div>
                            <label class="block text-[10px] font-bold uppercase text-slate-500 mb-1">Respuesta (Talento Humano)</label>
                            <textarea id="respuesta-solmod-${s.id_solicitud}" class="w-full text-xs rounded-xl border border-slate-200 p-2 bg-white outline-none focus:border-[#00594E]" rows="2" placeholder="Argumento de la decisión..."></textarea>
                        </div>
                        <div class="flex gap-2">
                            <button type="button" onclick="decidirSolicitudModificacion(${s.id_solicitud}, 'APROBADO')" class="flex-1 text-center py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl font-bold text-xs transition">Aprobar</button>
                            <button type="button" onclick="decidirSolicitudModificacion(${s.id_solicitud}, 'RECHAZADO')" class="flex-1 text-center py-2 bg-red-50 hover:bg-red-100 text-red-700 border border-red-200 rounded-xl font-bold text-xs transition">Rechazar</button>
                        </div>
                    </div>` : `
                    <div class="mt-3 pt-2 border-t border-slate-100">
                        <p class="text-[10px] font-bold uppercase text-slate-500">Respuesta</p>
                        <p class="text-xs text-slate-600 whitespace-pre-wrap">${escapeHtml(s.respuesta_admin || '')}</p>
                    </div>`;

                const metasNuevas = (detalle.metas || []).join(', ');

                return `
                <div class="rounded-2xl border border-slate-100 bg-white p-4 space-y-2 relative">
                    <div class="flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-xs font-black text-slate-800 truncate">Solicitud #${s.id_solicitud}</p>
                            <p class="text-[10px] text-slate-400">Solicitante: ${escapeHtml(s.solicitante_nombres || '')} ${escapeHtml(s.solicitante_apellidos || '')}</p>
                            ${fecha ? `<p class="text-[10px] text-slate-400 mt-0.5">Presentado: ${fecha}</p>` : ''}
                        </div>
                        ${estadoBadge}
                    </div>
                    <p class="text-xs text-slate-600 whitespace-pre-wrap"><b>Motivo:</b> ${escapeHtml(s.motivo || '')}</p>
                    <p class="text-xs text-slate-600"><b>Evaluado:</b> ${escapeHtml(s.evaluado_nombres || '')} ${escapeHtml(s.evaluado_apellidos || '')}</p>
                    <div class="rounded-xl bg-slate-50 border border-slate-100 p-3 space-y-1 text-xs">
                        <p class="text-[10px] font-bold uppercase text-slate-500">Cambios propuestos</p>
                        <p class="text-slate-700"><b>Compromiso:</b> ${escapeHtml(s.compromiso_actual_desc || detalle.descripcion || '')}</p>
                        <p class="text-slate-700"><b>Nueva descripción:</b> ${escapeHtml(detalle.descripcion || '')}</p>
                        <p class="text-slate-700"><b>Nuevo peso:</b> ${detalle.porcentaje_peso || ''}%</p>
                        <p class="text-slate-700"><b>Nuevas metas:</b> ${escapeHtml(metasNuevas)}</p>
                        ${s.evidencia_url ? `<p class="text-slate-700"><b>Evidencia:</b> <a href="${escapeHtml(s.evidencia_url)}" target="_blank" rel="noopener" class="text-[#00594E] underline break-all">${escapeHtml(s.evidencia_url)}</a></p>` : ''}
                    </div>
                    ${bloqueDecision}
                </div>`;
            }).join('');
        })
        .catch(() => {
            lista.innerHTML = '<div class="col-span-full py-10 text-center text-red-500 text-xs">Error al cargar solicitudes.</div>';
        });
}

export function decidirSolicitudModificacion(id, estado) {
    const respuesta = document.getElementById(`respuesta-solmod-${id}`)?.value?.trim();
    if (!respuesta) {
        alert('Debe justificar la decisión (Respuesta obligatoria).');
        return;
    }

    if (!confirm('¿Confirma guardar la decisión?')) return;

    fetchJson(`/admin/compromisos/solicitudes/${id}/resolver`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ estado, respuesta })
    })
    .then(async res => {
        const data = await res.json().catch(() => ({}));
        if (!res.ok) throw new Error(parseErrorMessage(data, 'No se pudo resolver la solicitud.'));
        alert('Solicitud resuelta con éxito.');
        window.location.reload();
    })
    .catch(err => alert(err.message));
}

window.cargarSolicitudesModificacion = cargarSolicitudesModificacion;
window.decidirSolicitudModificacion = decidirSolicitudModificacion;

export function actualizarFormularioPeriodoParcial() {
    const periodo = document.getElementById('periodo-parcial-periodo');
    const funcionario = document.getElementById('periodo-parcial-funcionario');
    const inicio = document.getElementById('periodo-parcial-inicio');
    const fin = document.getElementById('periodo-parcial-fin');
    if (!periodo || !funcionario || !inicio || !fin) return;

    const opcion = periodo.options[periodo.selectedIndex];
    const sistema = opcion?.dataset?.sistema || '';
    const inicioPeriodo = opcion?.dataset?.inicio || '';
    const finPeriodo = opcion?.dataset?.fin || '';
    const seleccionadoSigueDisponible = funcionario.selectedOptions[0]?.dataset?.sistema === sistema;

    funcionario.disabled = !sistema;
    inicio.disabled = !sistema;
    fin.disabled = !sistema;
    inicio.min = inicioPeriodo;
    inicio.max = finPeriodo;
    fin.min = inicioPeriodo;
    fin.max = finPeriodo;
    if (!seleccionadoSigueDisponible) funcionario.value = '';

    Array.from(funcionario.options).forEach((option) => {
        if (!option.value) return;
        option.hidden = option.dataset.sistema !== sistema;
        option.disabled = option.dataset.sistema !== sistema;
    });
}

window.actualizarFormularioPeriodoParcial = actualizarFormularioPeriodoParcial;
