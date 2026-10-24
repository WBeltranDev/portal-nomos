<script>
    // S14 (regla 11): bitácora de una evaluación, compartida por los tres roles.
    // El modal se crea en <body> al abrirlo, para no quedar dentro del encabezado.
    (function() {
        const esc = (value) => {
            const d = document.createElement('div');
            d.textContent = value ?? '';
            return d.innerHTML;
        };

        const TITULOS = {
            EVALUACION_ABIERTA: 'Evaluación abierta',
            EVALUACION_CALIFICADA: 'Evaluación calificada',
            CAMBIO_EVALUADOR: 'Cambio de evaluador',
            CAMBIO_EVALUADOR_PROGRAMADO: 'Cambio de evaluador programado',
            CAMBIO_EVALUADOR_CANCELADO: 'Cambio de evaluador cancelado',
            TRAMO_CERRADO: 'Tramo cerrado por cambio de evaluador',
            ANULADA_POR_TRASLADO: 'Anulada por traslado (tramo sin días)',
            IMPEDIMENTO_SOLICITADO: 'Impedimento o recusación solicitado',
            IMPEDIMENTO_RESUELTO: 'Impedimento o recusación resuelto',
            DESACUERDO_REGISTRADO: 'Desacuerdo del evaluado',
            EXTRATIEMPO_HABILITADO: 'Concertación en extratiempo habilitada',
            EJES_DEFINIDOS: 'Ejes misionales definidos',
            COMPROMISOS_COPIADOS: 'Compromisos copiados de un tramo anterior',
            COMPROMISO_CREADO: 'Compromiso creado',
            COMPROMISO_EDITADO: 'Compromiso editado',
            COMPROMISO_ELIMINADO: 'Compromiso eliminado',
            MODIFICACION_SOLICITADA: 'Modificación de compromiso solicitada',
            MODIFICACION_RESUELTA: 'Modificación de compromiso resuelta',
            FIRMA_CONCERTACION: 'Firma de la concertación',
            OBSERVACION_REGISTRADA: 'Observación de seguimiento',
            EVIDENCIA_CARGADA: 'Evidencia cargada',
            EVIDENCIAS_CONFIRMADAS: 'Evidencias confirmadas',
            EVIDENCIA_REVISADA: 'Evidencia revisada',
            CALIFICACION_COMPROMISOS: 'Calificación de compromisos',
            CALIFICACION_COMPETENCIAS: 'Calificación de competencias',
            CALIFICACION_EJES: 'Calificación de ejes misionales',
            NOTIFICACION_FIRMADA: 'Notificación de la nota firmada',
            DOCUMENTO_NOTIFICACION: 'Documento firmado de la notificación',
            RECURSO_RADICADO: 'Recurso radicado',
            RECURSO_DECIDIDO: 'Recurso decidido',
            PLAN_MEJORAMIENTO_GUARDADO: 'Plan de mejoramiento guardado',
            PLAN_MEJORAMIENTO_FIRMADO: 'Plan de mejoramiento firmado',
        };

        const ETIQUETAS = {
            id_vinc_evaluador: 'Evaluador',
            id_vinc_evaluador_anterior: 'Evaluador anterior',
            id_vinc_evaluador_nuevo: 'Evaluador nuevo',
            id_vinc_receptor: 'Receptor',
            id_vinc_funcionario_origen: 'Copiados de',
            receptor_talento_humano: 'La decide Talento Humano',
            cantidad: 'Cantidad',
            es_copia: 'Compromiso copiado',
            origen: 'Origen', decision: 'Decisión', tipo: 'Tipo', tipo_recurso: 'Recurso', tipo_firma: 'Firma',
            tipo_evaluacion: 'Tipo de evaluación', tipo_documento: 'Documento', accion: 'Acción', firmante: 'Firmante',
            fecha_cambio: 'Fecha del cambio', fecha_limite: 'Fecha límite', dias_laborados: 'Días laborados',
            nota_final: 'Nota del tramo', nota_definitiva: 'Nota definitiva', categoria: 'Categoría',
            fase: 'Fase', porcentaje_peso: 'Peso (%)', numero_orden: 'Orden', numero_radicado: 'Radicado',
            estado_plan: 'Estado del plan', metas: 'Metas', evidencias: 'Evidencias adjuntas',
            concertacion_completa: 'Concertación completa', descripcion_modificada: 'Cambió la descripción',
            confirmada: 'Confirmada', nueva_firma: 'Primera firma', investigacion: 'Investigación', proyeccion_social: 'Proyección social',
            componente: 'Componente', compromisos: 'Compromisos', cambios: 'Cambios',
        };

        const valor = (v) => {
            if (v === null || v === undefined || v === '') return '—';
            if (typeof v === 'boolean') return v ? 'Sí' : 'No';
            return String(v);
        };

        function renderDatos(datos, vinculaciones) {
            if (!datos) return '';
            const filas = Object.entries(datos)
                .filter(([k]) => !k.startsWith('id_') || k.startsWith('id_vinc_'))
                .map(([k, v]) => {
                    const etiqueta = ETIQUETAS[k] || k;
                    let texto;
                    if (k.startsWith('id_vinc_')) {
                        texto = vinculaciones[v] || `#${v}`;
                    } else if (k === 'cambios' && Array.isArray(v)) {
                        texto = v.map(c => `${c.id_compromiso ? 'Compromiso #' + c.id_compromiso : c.id_competencia ? 'Competencia #' + c.id_competencia : c.eje || ''}: ${valor(c.antes)} → ${valor(c.despues)}`).join('; ');
                    } else if (Array.isArray(v)) {
                        texto = v.length ? v.join(', ') : '—';
                    } else if (v && typeof v === 'object' && ('antes' in v || 'despues' in v)) {
                        texto = `${valor(v.antes)} → ${valor(v.despues)}`;
                    } else {
                        texto = valor(v);
                    }
                    return `<p><span class="font-semibold text-slate-600">${esc(etiqueta)}:</span> ${esc(texto)}</p>`;
                });
            return filas.length ? `<div class="mt-1 space-y-0.5 text-[11px] text-slate-500">${filas.join('')}</div>` : '';
        }

        function modal() {
            let m = document.getElementById('bitacora-modal');
            if (m) return m;
            m = document.createElement('div');
            m.id = 'bitacora-modal';
            m.className = 'fixed inset-0 z-[70] hidden items-center justify-center bg-slate-900/50 p-4';
            m.innerHTML = `
                <div class="w-full max-w-2xl max-h-[85vh] flex flex-col rounded-3xl bg-white shadow-2xl">
                    <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
                        <div class="flex items-center gap-2 min-w-0">
                            <span class="material-symbols-outlined text-[#00594E]">history</span>
                            <h3 id="bitacora-titulo" class="text-sm font-black text-slate-900 truncate">Bitácora</h3>
                        </div>
                        <button type="button" data-cerrar class="p-1.5 rounded-lg text-slate-400 hover:bg-slate-100" aria-label="Cerrar"><span class="material-symbols-outlined">close</span></button>
                    </div>
                    <div id="bitacora-contenido" class="overflow-y-auto px-5 py-4"></div>
                </div>`;
            m.addEventListener('click', (e) => {
                if (e.target === m || e.target.closest('[data-cerrar]')) {
                    m.classList.add('hidden');
                    m.classList.remove('flex');
                }
            });
            document.body.appendChild(m);
            return m;
        }

        window.verBitacora = function(idEvaluacion) {
            const m = modal();
            const contenido = m.querySelector('#bitacora-contenido');
            m.querySelector('#bitacora-titulo').textContent = `Bitácora de la evaluación #${idEvaluacion}`;
            contenido.innerHTML = '<p class="py-8 text-center text-xs text-slate-400">Cargando...</p>';
            m.classList.remove('hidden');
            m.classList.add('flex');

            fetch(`/evaluaciones/${encodeURIComponent(idEvaluacion)}/bitacora`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
            })
            .then(r => {
                if (!r.ok) throw new Error(r.status === 403 ? 'No tienes acceso a la bitácora de esta evaluación.' : 'No se pudo cargar la bitácora.');
                return r.json();
            })
            .then(data => {
                const eventos = data.eventos || [];
                const vinculaciones = data.vinculaciones || {};
                if (!eventos.length) {
                    contenido.innerHTML = '<p class="py-8 text-center text-xs text-slate-400">Aún no hay eventos registrados para esta evaluación.</p>';
                    return;
                }
                contenido.innerHTML = `<ol class="relative space-y-4 border-l border-slate-200 pl-5">${eventos.map(e => {
                    const fecha = new Date(e.created_at).toLocaleString('es-CO', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
                    const actor = e.actor_nombre || e.actor_usuario || 'Sistema';
                    const estado = e.estado_anterior || e.estado_nuevo
                        ? `<span class="ml-1 whitespace-nowrap rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-600">${esc(valor(e.estado_anterior))} → ${esc(valor(e.estado_nuevo))}</span>`
                        : '';
                    return `<li class="relative">
                        <span class="absolute -left-[26px] top-1 h-2.5 w-2.5 rounded-full bg-[#00594E]"></span>
                        <p class="text-xs font-bold text-slate-800">${esc(TITULOS[e.tipo_evento] || e.tipo_evento)}${estado}</p>
                        <p class="text-[10px] text-slate-400">${esc(fecha)} · ${esc(actor)}${e.rol_actor ? ' (' + esc(e.rol_actor) + ')' : ''}</p>
                        ${renderDatos(e.datos, vinculaciones)}
                    </li>`;
                }).join('')}</ol>`;
            })
            .catch(err => {
                contenido.innerHTML = `<p class="py-8 text-center text-xs text-red-500">${esc(err.message)}</p>`;
            });
        };
    })();
</script>
