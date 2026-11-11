/**
 * Common Helper Utilities for Dashboard Modules
 */

export function getConfig() {
    return window.APP_CONFIG || {};
}

export function getCsrfToken() {
    return window.APP_CONFIG?.csrfToken || document.querySelector('meta[name="csrf-token"]')?.content || '';
}

export function escapeHtml(value) {
    const div = document.createElement('div');
    div.innerText = value ?? '';
    return div.innerHTML;
}

export function fetchJson(url, options = {}) {
    const headers = {
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json',
        ...(options.headers || {})
    };
    if (getCsrfToken() && !headers['X-CSRF-TOKEN']) {
        headers['X-CSRF-TOKEN'] = getCsrfToken();
    }
    return fetch(url, { ...options, headers });
}

export function parseErrorMessage(payload, fallback) {
    if (payload?.error) return payload.error;
    if (payload?.message) return payload.message;
    if (payload?.errors) {
        const first = Object.values(payload.errors)[0];
        if (Array.isArray(first) && first.length) return first[0];
        if (typeof first === 'string') return first;
    }
    return fallback;
}

/**
 /**
 * Escala institucional vigente que el servidor publica en APP_CONFIG. Toda
 * etiqueta y todo tope de los campos de nota se derivan de aquí, para que un
 * cambio de escala no deje números o rótulos desactualizados en pantalla.
 */
export function getEscala() {
    return window.APP_CONFIG?.escalaCalificacion || null;
}

/**
 * Etiqueta de una categoría con su rango, tomada de la escala institucional
 * vigente (1.0 a 5.0 con sus cuatro bandas). Nunca se escriben los rangos a
 * mano, para que un cambio de escala no deje etiquetas desactualizadas.
 */
export function etiquetaCategoria(categoria) {
    if (!categoria) return '-';
    const escala = getEscala();
    const banda = escala?.bandas?.find(b => b.nivel === categoria);
    if (!banda) return categoria;
    const dec = escala?.decimales ?? 1;
    const rango = `${Number(banda.desde).toFixed(dec)} a ${Number(banda.hasta).toFixed(dec)}`;
    return `${banda.etiqueta} (${rango})`;
}

/**
 * Acota una calificación a la escala institucional: de 1.0 a 5.0 con un (1)
 * decimal. Se aplica al salir del campo, para que el evaluador nunca pueda
 * dejar una nota fuera de rango o con más decimales de los permitidos.
 */
export function clampCalificacion(input) {
    const escala = getEscala();
    const min = escala?.minimo ?? 1;
    const max = escala?.maximo ?? 5;
    const dec = escala?.decimales ?? 1;

    if (!input || input.value === '') return;
    let val = parseFloat(input.value);
    if (isNaN(val)) val = min;
    val = Math.round(val * 10 ** dec) / 10 ** dec;
    if (val < min) val = min;
    if (val > max) val = max;
    input.value = val;
}

export function showInlineMessage(id, text, isError = false) {
    const node = document.getElementById(id);
    if (!node) return;
    node.classList.remove('hidden');
    node.className = `text-xs font-semibold ${isError ? 'text-red-600' : 'text-[#00594E]'}`;
    node.innerText = text;
}

export function actualizarFaseLabel(idEvaluacion, fase, contexto = 'evaluado') {
    const node = document.getElementById(`fase-label-${contexto}-${idEvaluacion}`);
    if (!node) return;
    const nombres = {
        1: 'Concertación Pendiente',
        2: 'Concertación Parcial',
        3: 'Subir Evidencias',
        4: 'Calificación',
        5: 'Nota Final',
    };
    node.innerText = `Fase ${fase}: ${nombres[fase] || ''}`;
}

export function toggleSidebar() {
    const sidebar = document.getElementById('sidebar-menu');
    const overlay = document.getElementById('sidebar-overlay');
    if (sidebar) sidebar.classList.toggle('-translate-x-full');
    if (overlay) overlay.classList.toggle('hidden');
}

export function toggleProfileMenu() {
    const menu = document.getElementById('profile-menu');
    if (menu) menu.classList.toggle('open');
}

export function openPasswordModal() {
    const modal = document.getElementById('password-modal');
    if (modal) {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }
    const menu = document.getElementById('profile-menu');
    if (menu) menu.classList.remove('open');
}

export function closePasswordModal() {
    const modal = document.getElementById('password-modal');
    if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }
}

export function togglePasswordVisibility(id, button) {
    const input = document.getElementById(id);
    if (!input) return;
    const isPassword = input.type === 'password';
    input.type = isPassword ? 'text' : 'password';
    const icon = button.querySelector('.material-symbols-outlined');
    if (icon) icon.textContent = isPassword ? 'visibility_off' : 'visibility';
}

export const EJE_LABELS = {
    DOCENCIA: 'Docencia',
    INVESTIGACION: 'Investigación',
    PROYECCION_SOCIAL: 'Proyección Social',
};

export function formatPendientes(pendientes = {}) {
    const items = [];
    if (pendientes.compromisos_sin_calificar) {
        items.push(`${pendientes.compromisos_sin_calificar} compromiso(s) sin calificación`);
    }
    (pendientes.competencias_comunes_faltantes || []).forEach(n => items.push(`Competencia común: ${n}`));
    (pendientes.competencias_nivel_faltantes || []).forEach(n => items.push(`Competencia de nivel: ${n}`));
    (pendientes.ejes_faltantes || []).forEach(e => items.push(`Eje misional: ${EJE_LABELS[e] || e}`));
    return items;
}

export function renderResultado(calculo, containerId, contexto = 'evaluador', evaluacionId = null) {
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

    const categoriaLabel = etiquetaCategoria(calculo.categoria);
    const categoriaClass = {
        SOBRESALIENTE: 'bg-[#EAF2EF] text-[#00594E]',
        BUENO: 'bg-blue-50 text-blue-700',
        APROBADO_MEJORA: 'bg-amber-50 text-amber-700',
        NO_SATISFACTORIO: 'bg-red-50 text-red-600',
    }[calculo.categoria] || 'bg-slate-100 text-slate-600';

    const tieneEjes = String(calculo.sistema || '').toUpperCase() === 'ACUERDO_GESTION'
        && Array.isArray(calculo.ejes_activos)
        && calculo.ejes_activos.length > 0;

    let ejeMisionalHtml = '';
    if (tieneEjes) {
        const pesoEjesTotal = calculo.pesos?.ejes
            ? Object.values(calculo.pesos.ejes).reduce((a, b) => a + Number(b || 0), 0)
            : (calculo.subtotal_ejes_total ? 20 : 0);
        ejeMisionalHtml = `
            <div class="flex justify-between text-xs text-slate-600"><span>Ejes misionales (${pesoEjesTotal}%)</span><span class="font-bold">${calculo.subtotal_ejes_total ?? 0}</span></div>
        `;
    }

    const prorrateoHtml = (calculo.nota_prorrateo !== null && calculo.nota_prorrateo !== undefined) ? `
        <div class="mt-3 pt-3 border-t border-slate-100 text-xs text-slate-600">
            <p class="font-bold text-slate-700">Evaluación eventual (RF3)</p>
            <p>Días laborados: ${calculo.dias_laborados ?? '-'} · Factor: ${calculo.factor_prorrateo ?? '-'}</p>
            <p>Nota antes de prorrateo: ${calculo.nota_final} → Nota con prorrateo: <span class="font-black text-[#00594E]">${calculo.nota_prorrateo}</span></p>
        </div>
    ` : '';

    const pdfHtml = '';

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
                ${ejeMisionalHtml}
            </div>
            ${prorrateoHtml}
        </div>
        ${pdfHtml}
    `;
}

// Memoria de la pestaña y de la evaluación abiertas: una acción que recarga la página
// (guardar, firmar, error de validación) no debe devolver al usuario a la pantalla inicial.
const CLAVE_SECCION = 'serag_seccion_activa';
export const CLAVE_EVALUACION = 'serag_evaluacion_activa';

export function recordarSeleccion(clave, valor) {
    try {
        sessionStorage.setItem(clave, JSON.stringify({ rol: window.APP_CONFIG?.activeRole || null, valor }));
    } catch (e) { /* sin almacenamiento: no se recuerda */ }
}

export function seleccionRecordada(clave) {
    try {
        const guardado = JSON.parse(sessionStorage.getItem(clave) || 'null');
        return guardado && guardado.rol === (window.APP_CONFIG?.activeRole || null) ? guardado.valor : null;
    } catch (e) {
        return null;
    }
}

/** La página se cargó por una recarga o por la redirección de una acción hecha en el propio tablero. */
export function volvioDeUnaAccion() {
    try {
        const navegacion = performance.getEntriesByType('navigation')[0];
        if (navegacion?.type === 'reload') return true;
        const referente = document.referrer ? new URL(document.referrer) : null;
        return !!referente && referente.origin === window.location.origin && referente.pathname.startsWith('/dashboard');
    } catch (e) {
        return false;
    }
}

// Se lee al cargar el módulo, antes de que el tablero abra su pantalla por defecto y la sobrescriba.
const seccionRecordada = seleccionRecordada(CLAVE_SECCION);
const restaurarSeccion = volvioDeUnaAccion();

export function navegarMenu(button, seccion) {
    const activeRole = window.APP_CONFIG?.activeRole || 'evaluado';
    let targetSeccion = seccion;
    if (activeRole !== 'admin' && (seccion === 'usuarios' || seccion === 'empleados' || seccion === 'periodos' || seccion === 'ponderaciones')) {
        targetSeccion = (activeRole === 'evaluador' && seccion === 'usuarios') ? 'usuarios-evaluador' : 'evaluaciones';
    }
    const target = document.getElementById(`section-${targetSeccion}`);
    // No ocultar el contenido actual si un enlace apunta a una sección que no
    // existe en el tablero cargado.
    if (!target) return;

    document.querySelectorAll('.section-content').forEach(el => el.classList.add('hidden'));
    target.classList.remove('hidden');
    document.querySelectorAll('.sidebar-link').forEach(btn => btn.classList.remove('active'));
    if (!button) button = document.querySelector(`.sidebar-link[onclick*="'${targetSeccion}'"]`);
    if (button) button.classList.add('active');
    recordarSeleccion(CLAVE_SECCION, targetSeccion);
    if (targetSeccion === 'delegaciones' && typeof window.cargarDelegacionesAdmin === 'function') {
        window.cargarDelegacionesAdmin();
    }
    if (targetSeccion === 'plazas' && typeof window.cargarPlazasAdmin === 'function') {
        window.cargarPlazasAdmin();
    }
    if (window.innerWidth < 1024) toggleSidebar();
}

// Tras la recarga, vuelve a la pestaña donde estaba el usuario (después del arranque del tablero).
window.addEventListener('DOMContentLoaded', () => {
    if (!restaurarSeccion || !seccionRecordada) return;
    setTimeout(() => navegarMenu(null, seccionRecordada), 0);
});

// Make globally available for onclick handlers in HTML
window.toggleSidebar = toggleSidebar;
window.toggleProfileMenu = toggleProfileMenu;
window.openPasswordModal = openPasswordModal;
window.closePasswordModal = closePasswordModal;
window.togglePasswordVisibility = togglePasswordVisibility;
window.navegarMenu = navegarMenu;
window.escapeHtml = escapeHtml;
window.clampCalificacion = clampCalificacion;
window.getEscala = getEscala;
window.etiquetaCategoria = etiquetaCategoria;
window.showInlineMessage = showInlineMessage;
window.renderResultado = renderResultado;
window.formatPendientes = formatPendientes;
window.actualizarFaseLabel = actualizarFaseLabel;

/**
 * Enlaces de un recurso: lo que adjuntó el evaluado al radicar, el oficio con
 * la respuesta y los soportes de la decisión (acta del CEDP, otros).
 */
export function renderEnlacesRecurso(r) {
    const evidencias = Array.isArray(r.evidencias) ? r.evidencias : [];
    const enlace = (url, texto) => `
        <a href="${escapeHtml(url)}" target="_blank" rel="noopener noreferrer" class="flex items-center gap-1.5 text-xs text-[#00594E] hover:underline min-w-0">
            <span class="material-symbols-outlined text-sm shrink-0">open_in_new</span>
            <span class="truncate">${escapeHtml(texto || url)}</span>
        </a>`;
    const bloque = (icono, titulo, contenido) => `
        <div class="pt-2 border-t border-slate-100 space-y-1.5">
            <p class="text-[10px] font-bold uppercase text-slate-500 flex items-center gap-1"><span class="material-symbols-outlined text-xs">${icono}</span> ${titulo}</p>
            ${contenido}
        </div>`;

    const radicacion = evidencias.filter(e => (e.origen || 'RADICACION') === 'RADICACION');
    const soportes = evidencias.filter(e => e.origen === 'DECISION');

    return (radicacion.length ? bloque('link', 'Evidencias', radicacion.map(e => enlace(e.url, e.descripcion)).join('')) : '')
        + (r.url_oficio ? bloque('description', 'Oficio de respuesta', enlace(r.url_oficio, 'Ver oficio de respuesta')) : '')
        + (soportes.length ? bloque('attach_file', 'Soportes de la decisión', soportes.map(e => enlace(e.url, e.descripcion)).join('')) : '');
}

/**
 * Ventana para decidir un recurso: motivación, enlace obligatorio del oficio y
 * enlaces de soporte opcionales. Llama a onConfirmar({ motivacion, url_oficio,
 * soportes }) cuando los datos obligatorios están completos.
 */
export function abrirModalDecisionRecurso({ decision, onConfirmar }) {
    document.getElementById('modal-decision-recurso')?.remove();

    const favorable = decision === 'APROBADO';
    const modal = document.createElement('div');
    modal.id = 'modal-decision-recurso';
    modal.className = 'fixed inset-0 z-[100] flex items-center justify-center bg-slate-900/50 p-4';
    modal.innerHTML = `
        <div class="w-full max-w-lg max-h-[90vh] overflow-y-auto rounded-3xl bg-white p-6 shadow-2xl space-y-4">
            <div>
                <p class="text-[10px] font-black uppercase tracking-widest text-[#B5A160]">Decisión del recurso</p>
                <h3 class="text-lg font-black text-slate-900">${favorable ? 'Decisión favorable (se acepta el recurso)' : 'Decisión desfavorable (se niega el recurso)'}</h3>
            </div>
            <div>
                <label class="block text-[10px] font-bold uppercase text-slate-500 mb-1">Motivación / observaciones</label>
                <textarea id="decision-recurso-motivacion" rows="4" maxlength="3000" class="w-full text-sm rounded-xl border border-slate-200 p-3 outline-none focus:border-[#00594E]" placeholder="Explica los fundamentos de tu decisión"></textarea>
            </div>
            <div>
                <label class="block text-[10px] font-bold uppercase text-slate-500 mb-1">Enlace del oficio con la respuesta (obligatorio)</label>
                <input id="decision-recurso-oficio" type="url" maxlength="1000" class="w-full text-sm rounded-xl border border-slate-200 p-3 outline-none focus:border-[#00594E]" placeholder="https://...">
            </div>
            <div>
                <div class="flex items-center justify-between mb-1">
                    <label class="block text-[10px] font-bold uppercase text-slate-500">Soportes adicionales (acta del CEDP, otros)</label>
                    <button type="button" id="decision-recurso-agregar" class="text-[11px] font-bold text-[#00594E] hover:underline">+ Agregar enlace</button>
                </div>
                <div id="decision-recurso-soportes" class="space-y-2"></div>
            </div>
            <p id="decision-recurso-error" class="hidden text-xs font-semibold text-red-600"></p>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" id="decision-recurso-cancelar" class="rounded-xl border border-slate-200 px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-50">Cancelar</button>
                <button type="button" id="decision-recurso-confirmar" class="rounded-xl bg-[#00594E] px-4 py-2 text-xs font-bold text-white hover:brightness-110">Registrar decisión</button>
            </div>
        </div>`;
    document.body.appendChild(modal);

    const lista = modal.querySelector('#decision-recurso-soportes');
    const error = modal.querySelector('#decision-recurso-error');
    const mostrarError = texto => { error.innerText = texto; error.classList.remove('hidden'); };

    modal.querySelector('#decision-recurso-agregar').addEventListener('click', () => {
        const fila = document.createElement('div');
        fila.className = 'soporte-fila grid grid-cols-[1fr_1fr_auto] gap-2';
        fila.innerHTML = `
            <input type="text" maxlength="200" class="soporte-descripcion text-xs rounded-xl border border-slate-200 p-2 outline-none focus:border-[#00594E]" placeholder="Descripción">
            <input type="url" maxlength="1000" class="soporte-url text-xs rounded-xl border border-slate-200 p-2 outline-none focus:border-[#00594E]" placeholder="https://...">
            <button type="button" class="soporte-quitar text-slate-400 hover:text-red-600" title="Quitar"><span class="material-symbols-outlined text-base">close</span></button>`;
        fila.querySelector('.soporte-quitar').addEventListener('click', () => fila.remove());
        lista.appendChild(fila);
    });

    modal.querySelector('#decision-recurso-cancelar').addEventListener('click', () => modal.remove());
    modal.querySelector('#decision-recurso-confirmar').addEventListener('click', () => {
        const motivacion = modal.querySelector('#decision-recurso-motivacion').value.trim();
        const urlOficio = modal.querySelector('#decision-recurso-oficio').value.trim();
        if (!motivacion) return mostrarError('La motivación es obligatoria para decidir el recurso.');
        if (!/^https?:\/\/\S+$/i.test(urlOficio)) return mostrarError('Pega el enlace del oficio con la respuesta (debe empezar por https://).');

        const soportes = [];
        for (const fila of lista.querySelectorAll('.soporte-fila')) {
            const url = fila.querySelector('.soporte-url').value.trim();
            const descripcion = fila.querySelector('.soporte-descripcion').value.trim();
            if (!url && !descripcion) continue;
            if (!/^https?:\/\/\S+$/i.test(url)) return mostrarError('Cada soporte necesita un enlace válido (https://...).');
            soportes.push({ url, descripcion });
        }

        modal.remove();
        onConfirmar({ motivacion, url_oficio: urlOficio, soportes });
    });

    modal.querySelector('#decision-recurso-motivacion').focus();
}

/**
 * Agrega un campo de búsqueda a un <select> largo. Filtra las opciones al escribir
 * (sin distinguir tildes ni mayúsculas) reconstruyéndolas, porque no todos los
 * navegadores permiten ocultar <option>. Conserva la opción vacía y la elegida.
 */
export function habilitarBuscadorSelect(select, placeholder = 'Buscar…') {
    if (!select || select.dataset.buscadorListo === '1') return;
    select.dataset.buscadorListo = '1';

    const normalizar = texto => (texto || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
    const guardar = () => Array.from(select.querySelectorAll('option')).map(o => ({
        value: o.value, text: o.textContent, grupo: o.parentElement?.tagName === 'OPTGROUP' ? o.parentElement.label : null,
        data: { ...o.dataset }, disabled: o.disabled,
    }));
    let opciones = guardar();

    const entrada = document.createElement('input');
    entrada.type = 'search';
    entrada.placeholder = placeholder;
    entrada.autocomplete = 'off';
    entrada.className = 'w-full text-xs rounded-xl border border-slate-200 p-2 mb-1 bg-white outline-none focus:border-[#00594E]';
    select.parentNode.insertBefore(entrada, select);

    const pintar = () => {
        const buscado = normalizar(entrada.value.trim());
        const elegido = select.value;
        const visibles = opciones.filter(o => o.value === '' || o.value === elegido || !buscado || normalizar(o.text).includes(buscado));
        select.innerHTML = '';
        const grupos = new Map();
        visibles.forEach(o => {
            const opcion = document.createElement('option');
            opcion.value = o.value;
            opcion.textContent = o.text;
            opcion.disabled = o.disabled;
            Object.entries(o.data).forEach(([k, v]) => { opcion.dataset[k] = v; });
            if (o.value === elegido) opcion.selected = true;
            if (o.grupo) {
                if (!grupos.has(o.grupo)) {
                    const grupo = document.createElement('optgroup');
                    grupo.label = o.grupo;
                    grupos.set(o.grupo, grupo);
                    select.appendChild(grupo);
                }
                grupos.get(o.grupo).appendChild(opcion);
            } else {
                select.appendChild(opcion);
            }
        });
        if (buscado && visibles.filter(o => o.value !== '' && o.value !== elegido).length === 0) {
            const vacio = document.createElement('option');
            vacio.disabled = true;
            vacio.textContent = 'Sin resultados';
            select.appendChild(vacio);
        }
    };

    entrada.addEventListener('input', pintar);
    // Con una sola coincidencia, Enter la elige.
    entrada.addEventListener('keydown', evento => {
        if (evento.key !== 'Enter') return;
        evento.preventDefault();
        const coincidencias = Array.from(select.options).filter(o => o.value !== '' && !o.disabled);
        if (coincidencias.length >= 1) {
            select.value = coincidencias[0].value;
            select.dispatchEvent(new Event('change', { bubbles: true }));
        }
    });
}

export function habilitarBuscadoresSelect(raiz = document) {
    raiz.querySelectorAll('select[data-buscable]').forEach(select => habilitarBuscadorSelect(select, select.dataset.buscable || 'Buscar…'));
}

/**
 * Confirmación propia (Tailwind) en lugar de window.confirm(): no congela el
 * navegador, respeta el diseño y no la silencian los bloqueadores. Devuelve una
 * promesa con true/false.
 */
export function confirmarAccion(mensaje, { titulo = 'Confirmar acción', textoConfirmar = 'Confirmar', textoCancelar = 'Cancelar' } = {}) {
    return new Promise(resolver => {
        document.getElementById('modal-confirmar-accion')?.remove();

        const modal = document.createElement('div');
        modal.id = 'modal-confirmar-accion';
        modal.setAttribute('role', 'dialog');
        modal.setAttribute('aria-modal', 'true');
        modal.className = 'fixed inset-0 z-[10000] flex items-center justify-center bg-slate-900/50 p-4';
        modal.innerHTML = `
            <div class="w-full max-w-md rounded-3xl bg-white p-6 shadow-2xl space-y-4">
                <div class="flex items-start gap-3">
                    <span class="material-symbols-outlined text-[#B5A160] text-2xl shrink-0">help</span>
                    <div class="min-w-0">
                        <h3 class="text-base font-black text-slate-900">${escapeHtml(titulo)}</h3>
                        <p class="mt-1 text-sm text-slate-600 whitespace-pre-line">${escapeHtml(mensaje)}</p>
                    </div>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" data-accion="cancelar" class="rounded-xl border border-slate-200 px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-50">${escapeHtml(textoCancelar)}</button>
                    <button type="button" data-accion="confirmar" class="rounded-xl bg-[#00594E] px-4 py-2 text-xs font-bold text-white hover:brightness-110">${escapeHtml(textoConfirmar)}</button>
                </div>
            </div>`;
        document.body.appendChild(modal);

        const cerrar = resultado => {
            document.removeEventListener('keydown', teclas, true);
            modal.remove();
            resolver(resultado);
        };
        const teclas = evento => {
            if (evento.key === 'Escape') { evento.preventDefault(); cerrar(false); }
            if (evento.key === 'Enter') { evento.preventDefault(); cerrar(true); }
        };
        modal.querySelector('[data-accion="cancelar"]').addEventListener('click', () => cerrar(false));
        modal.querySelector('[data-accion="confirmar"]').addEventListener('click', () => cerrar(true));
        modal.addEventListener('click', evento => { if (evento.target === modal) cerrar(false); });
        document.addEventListener('keydown', teclas, true);
        modal.querySelector('[data-accion="confirmar"]').focus();
    });
}

/**
 * Aviso no bloqueante (toast) en lugar de window.alert(). tipo: 'info', 'exito' o 'error'.
 */
export function avisar(mensaje, tipo = 'info') {
    let contenedor = document.getElementById('avisos-serag');
    if (!contenedor) {
        contenedor = document.createElement('div');
        contenedor.id = 'avisos-serag';
        contenedor.setAttribute('aria-live', 'polite');
        contenedor.className = 'fixed bottom-4 right-4 z-[10001] flex w-[min(24rem,calc(100vw-2rem))] flex-col gap-2';
        document.body.appendChild(contenedor);
    }

    const estilos = {
        info: ['border-slate-200 bg-white text-slate-800', 'info', 'text-[#00594E]'],
        exito: ['border-emerald-200 bg-emerald-50 text-emerald-900', 'check_circle', 'text-emerald-600'],
        error: ['border-red-200 bg-red-50 text-red-900', 'error', 'text-red-600'],
    };
    const [clases, icono, colorIcono] = estilos[tipo] || estilos.info;

    const aviso = document.createElement('div');
    aviso.className = `flex items-start gap-2 rounded-2xl border p-3 text-sm shadow-lg ${clases}`;
    aviso.innerHTML = `
        <span class="material-symbols-outlined text-lg shrink-0 ${colorIcono}">${icono}</span>
        <p class="min-w-0 flex-1 whitespace-pre-line break-words">${escapeHtml(String(mensaje ?? ''))}</p>
        <button type="button" class="shrink-0 text-slate-400 hover:text-slate-700" aria-label="Cerrar"><span class="material-symbols-outlined text-base">close</span></button>`;
    const quitar = () => aviso.remove();
    aviso.querySelector('button').addEventListener('click', quitar);
    contenedor.appendChild(aviso);
    setTimeout(quitar, tipo === 'error' ? 9000 : 6000);
}

window.confirmarAccion = confirmarAccion;
window.avisar = avisar;
