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

export function clampCalificacion(input) {
    if (!input || input.value === '') return;
    let val = parseFloat(input.value);
    if (isNaN(val)) val = 0;
    if (val < 0) val = 0;
    if (val > 100) val = 100;
    input.value = val;
}

export function showInlineMessage(id, text, isError = false) {
    const node = document.getElementById(id);
    if (!node) return;
    node.classList.remove('hidden');
    node.className = `text-xs font-semibold ${isError ? 'text-red-600' : 'text-[#00594E]'}`;
    node.innerText = text;
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

    const categoriaLabel = {
        SOBRESALIENTE: 'Sobresaliente (91-100)',
        BUENO: 'Bueno (81-90)',
        APROBADO_MEJORA: 'Aprobado - Susceptible de mejora (71-80)',
        NO_SATISFACTORIO: 'No satisfactorio (0-70)',
    }[calculo.categoria] || calculo.categoria || '-';
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

    const pdfHtml = (contexto === 'evaluado' && calculo.estado === 'CALIFICADA' && evaluacionId) ? `
        <div class="mt-3 flex flex-wrap gap-2">
            <a href="/evaluaciones/${evaluacionId}/informe" class="inline-flex items-center gap-2 rounded-xl bg-[#00594E] text-white px-4 py-2 text-xs font-bold hover:brightness-110 transition">
                <span class="material-symbols-outlined text-sm">picture_as_pdf</span> Descargar PDF semestral
            </a>
            ${calculo.informe_anual_disponible ? `
            <a href="/evaluaciones/${evaluacionId}/informe-anual" class="inline-flex items-center gap-2 rounded-xl bg-[#B5A160] text-white px-4 py-2 text-xs font-bold hover:brightness-110 transition">
                <span class="material-symbols-outlined text-sm">picture_as_pdf</span> Descargar PDF anual
            </a>` : ''}
        </div>
    ` : '';

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

export function navegarMenu(button, seccion) {
    const activeRole = window.APP_CONFIG?.activeRole || 'evaluado';
    let targetSeccion = seccion;
    if (activeRole !== 'admin' && (seccion === 'usuarios' || seccion === 'empleados' || seccion === 'periodos' || seccion === 'ponderaciones')) {
        targetSeccion = (activeRole === 'evaluador' && seccion === 'usuarios') ? 'usuarios-evaluador' : 'evaluaciones';
    }
    document.querySelectorAll('.section-content').forEach(el => el.classList.add('hidden'));
    const target = document.getElementById(`section-${targetSeccion}`);
    if (target) target.classList.remove('hidden');
    document.querySelectorAll('.sidebar-link').forEach(btn => btn.classList.remove('active'));
    if (button) button.classList.add('active');
    if (window.innerWidth < 1024) toggleSidebar();
}

// Make globally available for onclick handlers in HTML
window.toggleSidebar = toggleSidebar;
window.toggleProfileMenu = toggleProfileMenu;
window.openPasswordModal = openPasswordModal;
window.closePasswordModal = closePasswordModal;
window.navegarMenu = navegarMenu;
window.escapeHtml = escapeHtml;
window.clampCalificacion = clampCalificacion;
window.showInlineMessage = showInlineMessage;
window.renderResultado = renderResultado;
window.formatPendientes = formatPendientes;
