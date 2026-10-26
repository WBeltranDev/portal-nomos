<script>
    (function() {
        // Escapa texto antes de inyectarlo en innerHTML. Este script es clásico y
        // se ejecuta antes que los módulos de Vite, por eso no puede importar
        // escapeHtml() de common.js.
        const escNotif = (value) => {
            const d = document.createElement('div');
            d.textContent = value ?? '';
            return d.innerHTML;
        };
        // Lista blanca de secciones: evita inyectar JS en el onclick generado.
        const SECCIONES_NOTIF = ['periodos', 'recursos-planes', 'impedimentos-admin', 'delegaciones', 'compromisos-modificacion', 'evaluaciones', 'evaluaciones-evaluador'];

        const notifContainer = document.getElementById('notificaciones-container');
        const notifDropdown = document.getElementById('notif-dropdown');
        const notifLista = document.getElementById('notif-lista');
        const notifVacia = document.getElementById('notif-vacia');
        const notifBadge = document.getElementById('notif-badge');

        if (!notifContainer) return;

        // Talento Humano consulta las notificaciones generales; los demás roles, las suyas.
        const urlLista = notifContainer.dataset.urlLista;
        const urlLeidas = notifContainer.dataset.urlLeidas;

        const iconos = {
            'PERIODO_CERCA': 'schedule',
            'RECURSO_NUEVO': 'gavel',
            'PLAN_NUEVO': 'assignment',
            'IMPEDIMENTO_NUEVO': 'block',
            'RECUSACION_NUEVA': 'gavel',
            'DELEGACION_PROXIMA': 'swap_horiz',
            'IMPEDIMENTO_APROBADO': 'how_to_reg',
            'MODIFICACION_COMPROMISO': 'edit_note',
            'CAMBIO_EVALUADOR': 'swap_horiz',
            'NUEVO_EVALUADO': 'person_add',
            'NUEVA_EVALUACION': 'assignment_add',
        };

        const colores = {
            'PERIODO_CERCA': 'bg-amber-50 text-amber-700',
            'RECURSO_NUEVO': 'bg-blue-50 text-blue-700',
            'PLAN_NUEVO': 'bg-purple-50 text-purple-700',
            'IMPEDIMENTO_NUEVO': 'bg-red-50 text-red-600',
            'RECUSACION_NUEVA': 'bg-orange-50 text-orange-700',
            'DELEGACION_PROXIMA': 'bg-teal-50 text-teal-700',
            'IMPEDIMENTO_APROBADO': 'bg-emerald-50 text-emerald-700',
            'MODIFICACION_COMPROMISO': 'bg-sky-50 text-sky-700',
            'CAMBIO_EVALUADOR': 'bg-sky-50 text-sky-700',
            'NUEVO_EVALUADO': 'bg-emerald-50 text-emerald-700',
            'NUEVA_EVALUACION': 'bg-sky-50 text-sky-700',
        };

        window.toggleNotificaciones = function() {
            notifDropdown.classList.toggle('hidden');
            if (!notifDropdown.classList.contains('hidden')) {
                cargarNotificaciones();
            }
        };

        document.addEventListener('click', function(e) {
            if (!notifContainer.contains(e.target)) {
                notifDropdown.classList.add('hidden');
            }
        });

        function cargarNotificaciones() {
            fetch(urlLista, {
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
            })
            .then(r => r.json())
            .then(data => {
                const items = data.notificaciones || [];
                if (!items.length) {
                    notifLista.classList.add('hidden');
                    notifVacia.classList.remove('hidden');
                    return;
                }
                notifVacia.classList.add('hidden');
                notifLista.classList.remove('hidden');

                notifLista.innerHTML = items.map(n => {
                    const icon = iconos[n.tipo] || 'info';
                    const color = colores[n.tipo] || 'bg-slate-50 text-slate-600';
                    const fecha = new Date(n.created_at).toLocaleDateString('es-CO', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
                    const leida = n.leida ? 'opacity-60' : '';
                    const seccion = SECCIONES_NOTIF.includes(n.seccion) ? n.seccion : '';
                    const link = seccion ? `onclick="navegarMenu(document.querySelector('[data-section=\\'${seccion}\\']'), '${seccion}')"` : '';
                    return `<div class="px-4 py-3 hover:bg-slate-50 transition cursor-pointer ${leida}" ${link}>
                        <div class="flex items-start gap-3">
                            <span class="material-symbols-outlined text-lg mt-0.5 ${escNotif(color)} rounded-lg p-1.5 bg-opacity-50">${escNotif(icon)}</span>
                            <div class="min-w-0 flex-1">
                                <p class="text-xs font-bold text-slate-800 truncate">${escNotif(n.titulo)}</p>
                                <p class="text-[11px] text-slate-500 mt-0.5 leading-snug">${escNotif(n.mensaje)}</p>
                                <p class="text-[10px] text-slate-400 mt-1">${escNotif(fecha)}</p>
                            </div>
                            ${!n.leida ? '<span class="w-2 h-2 rounded-full bg-[#00594E] shrink-0 mt-1.5"></span>' : ''}
                        </div>
                    </div>`;
                }).join('');
            })
            .catch(() => {
                notifLista.innerHTML = '<div class="py-6 text-center text-xs text-red-400">Error al cargar notificaciones.</div>';
            });
        }

        window.marcarTodasLeidas = function() {
            fetch(urlLeidas, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                }
            })
            .then(r => r.json())
            .then(() => {
                if (notifBadge) notifBadge.classList.add('hidden');
                cargarNotificaciones();
            });
        };
    })();
</script>
