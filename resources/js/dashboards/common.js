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
