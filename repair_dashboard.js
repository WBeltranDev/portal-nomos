const fs = require('fs');
const path = require('path');

const filePath = path.join(__dirname, 'resources', 'views', 'dashboard.blade.php');
const content = fs.readFileSync(filePath, 'utf8');

const replacement = `<script>
    let selectedEvaluacionId = null;
    let selectedEvaluacionData = null;
    const periodosDisponibles = @js($periodos->map(fn ($p) => [
        'id_periodo' => $p->id_periodo,
        'sistema' => $p->sistema,
        'anio' => $p->anio,
        'semestre' => $p->semestre,
        'estado' => $p->estado,
        'fecha_inicio' => $p->fecha_inicio,
        'fecha_fin' => $p->fecha_fin,
    ])->values());

    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar-menu');
        const overlay = document.getElementById('sidebar-overlay');
        if (sidebar) sidebar.classList.toggle('-translate-x-full');
        if (overlay) overlay.classList.toggle('hidden');
    }

    function toggleProfileMenu() {
        const menu = document.getElementById('profile-menu');
        if (menu) menu.classList.toggle('open');
    }

    function openPasswordModal() {
        const modal = document.getElementById('password-modal');
        if (modal) {
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }
        const menu = document.getElementById('profile-menu');
        if (menu) menu.classList.remove('open');
    }

    function closePasswordModal() {
        const modal = document.getElementById('password-modal');
        if (modal) {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }
    }

    function navegarMenu(button, seccion) {
        const activeRole = "{{ $rolActivo }}";
        let targetSeccion = seccion;

        if (activeRole !== 'admin' && (seccion === 'usuarios' || seccion === 'empleados' || seccion === 'periodos' || seccion === 'ponderaciones')) {
            targetSeccion = activeRole === 'evaluador' && seccion === 'usuarios' ? 'usuarios-evaluador' : 'evaluaciones';
        }

        document.querySelectorAll('.section-content').forEach(el => el.classList.add('hidden'));
        const target = document.getElementById(\`section-\${targetSeccion}\`);
        if (target) target.classList.remove('hidden');

        document.querySelectorAll('.sidebar-link').forEach(btn => btn.classList.remove('active'));
        if (button) button.classList.add('active');

        if (window.innerWidth < 1024) toggleSidebar();
    }

    function filtrarEmpleados() {
        const texto = (document.getElementById('buscador-empleados')?.value || '').trim().toLowerCase();
        document.querySelectorAll('.empleado-card').forEach(card => {
            const nombre = card.dataset.nombre || '';
            const cedula = card.dataset.cedula || '';
            const correo = card.dataset.correo || '';
            const match = !texto || nombre.includes(texto) || cedula.includes(texto) || correo.includes(texto);
            card.classList.toggle('hidden', !match);
        });
    }

    function seleccionarEmpleado(card, empleado) {
        const setText = (id, value) => {
            const node = document.getElementById(id);
            if (node) node.innerText = value;
        };

        setText('empleado-avatar', (empleado.nombres?.[0] || '') + (empleado.apellidos?.[0] || ''));
        setText('empleado-nombre', \`\${empleado.nombres || ''} \${empleado.apellidos || ''}\`.trim());
        setText('empleado-cargo', empleado.nombre_cargo || 'Sin cargo');
        setText('empleado-correo', empleado.correo_institucional || 'Sin correo');
        setText('empleado-documento', \`\${empleado.tipo_documento || ''} \${empleado.documento_identidad || ''}\`.trim());
        setText('empleado-area', empleado.nombre_area || 'Sin área');
        setText('empleado-estado', empleado.activo ? 'Activo' : 'Inactivo');

        document.querySelectorAll('.empleado-card').forEach(el => el.classList.remove('ring-2', 'ring-[#00594E]'));
        if (card) card.classList.add('ring-2', 'ring-[#00594E]');
    }

    function seleccionarPersonaEvaluador(card, persona) {
        selectedEvaluacionData = persona;
        const nombreCompleto = \`\${persona.nombres || ''} \${persona.apellidos || ''}\`.trim();
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
        setText('empleado-cargo', \`\${persona.cargo || 'Sin cargo'} - \${persona.area || 'Sin área'}\`);
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
        setText('apertura-detalle', \`Tipo de acuerdo: \${persona.sistema_evaluacion || '-'}\`);
        setText('apertura-sistema', sistema === 'RENDIMIENTO_LABORAL' ? 'RL' : (sistema === 'ACUERDO_GESTION' ? 'AG' : (persona.sistema_evaluacion || '-')));

        const aperturaIdVinc = document.getElementById('apertura-id-vinc');
        const aperturaIdPeriodo = document.getElementById('apertura-id-periodo');
        const cicloSelect = document.getElementById('apertura-ciclo-select');
        const aperturaPeriodo = document.getElementById('apertura-periodo');
        const aperturaVigencia = document.getElementById('apertura-vigencia');
        const aperturaCiclo = document.getElementById('apertura-ciclo');
        const aperturaAviso = document.getElementById('apertura-aviso-periodo');

        if (aperturaIdVinc) aperturaIdVinc.value = persona.id_vinculacion || '';
        if (cicloSelect) cicloSelect.value = 'SEMESTRE_1';

        if (periodo) {
            if (aperturaIdPeriodo) aperturaIdPeriodo.value = periodo.id_periodo;
            if (aperturaPeriodo) aperturaPeriodo.innerText = \`\${periodo.sistema} (\${periodo.anio}-\${String(periodo.semestre).padStart(2, '0')})\`;
            if (aperturaVigencia) aperturaVigencia.innerText = \`\${periodo.fecha_inicio || '-'} a \${periodo.fecha_fin || '-'}\`;
            if (aperturaCiclo && cicloSelect) aperturaCiclo.innerText = cicloSelect.options[cicloSelect.selectedIndex].text;
            if (aperturaAviso) aperturaAviso.innerText = 'El período se asigna automáticamente según el tipo de acuerdo.';
        } else {
            if (aperturaIdPeriodo) aperturaIdPeriodo.value = '';
            if (aperturaPeriodo) aperturaPeriodo.innerText = 'No hay período abierto para este sistema';
            if (aperturaVigencia) aperturaVigencia.innerText = '-';
            if (aperturaCiclo && cicloSelect) aperturaCiclo.innerText = cicloSelect.options[cicloSelect.selectedIndex].text;
            if (aperturaAviso) aperturaAviso.innerText = 'Abre un período activo para este sistema antes de iniciar la evaluación.';
        }

        document.querySelectorAll('.evaluado-card').forEach(el => el.classList.remove('ring-2', 'ring-[#00594E]'));
        if (card) card.classList.add('ring-2', 'ring-[#00594E]');
    }

    function abrirConcertacionEvaluado(card, ev) {
        selectedEvaluacionId = ev.id_evaluacion;
        selectedEvaluacionData = ev;

        const panel = document.getElementById('panel-concertacion-evaluado');
        const empty = document.getElementById('panel-concertacion-evaluado-empty');
        if (empty) empty.classList.add('hidden');
        if (panel) panel.classList.remove('hidden');

        const tipo = document.getElementById('concertacion-evaluado-tipo');
        const evaluador = document.getElementById('concertacion-evaluado-evaluador');
        const form = document.getElementById('form-firmar-evaluado');
        if (tipo) tipo.innerText = ev.tipo_nombre || 'Tipo de Evaluación';
        if (evaluador) evaluador.innerText = \`Evaluador: \${ev.evalador_nombres || 'Mi Evaluador'} \${ev.evalador_apellidos || ''}\`.trim();
        if (form) form.action = \`/evaluaciones/\${ev.id_evaluacion}/firmar\`;

        const axesView = document.getElementById('ejes-misionales-seleccion-evaluado');
        const chkInv = document.getElementById('chk-eje-investigacion');
        const chkProj = document.getElementById('chk-eje-proyeccion');
        if (axesView) axesView.classList.add('hidden');
        if (chkInv) chkInv.checked = false;
        if (chkProj) chkProj.checked = false;

        fetch(\`/evaluaciones/\${ev.id_evaluacion}/ejes\`)
            .then(res => res.json())
            .then(ejes => {
                const aplica = ev.sistema === 'ACUERDO_GESTION' && !!ev.aplica_eje_misional;
                if (aplica && axesView) {
                    axesView.classList.remove('hidden');
                    if (chkInv) chkInv.checked = !!ejes.investigacion;
                    if (chkProj) chkProj.checked = !!ejes.proyeccion_social;
                }
                cargarCompromisosEvaluado(ev);
            })
            .catch(() => cargarCompromisosEvaluado(ev));

        document.querySelectorAll('.evaluacion-card').forEach(el => el.classList.remove('ring-2', 'ring-[#00594E]'));
        if (card) card.classList.add('ring-2', 'ring-[#00594E]');
    }

    function cargarCompromisosEvaluado(ev) {
        if (!selectedEvaluacionId) return;

        fetch(\`/evaluaciones/\${selectedEvaluacionId}/compromisos\`)
            .then(res => res.json())
            .then(compromisos => {
                const contenedor = document.getElementById('compromisos-lista-evaluado');
                if (!contenedor) return;
                contenedor.innerHTML = '';

                let sumaPesos = 0;
                const contador = compromisos.length;
                let targetWeight = 80.0;
                if (ev.sistema === 'ACUERDO_GESTION' && ev.aplica_eje_misional) {
                    targetWeight = 70.0;
                    if (document.getElementById('chk-eje-investigacion')?.checked) targetWeight -= 10.0;
                    if (document.getElementById('chk-eje-proyeccion')?.checked) targetWeight -= 10.0;
                }

                const minWeight = targetWeight < 70.0 ? 5 : 10;
                const weightInput = document.getElementById('comp-peso-evaluado');
                if (weightInput) {
                    weightInput.min = minWeight;
                    weightInput.placeholder = \`De \${minWeight}% a 40%\`;
                }

                const yaFirmado = !!ev.firmado;
                compromisos.forEach(c => {
                    sumaPesos += parseFloat(c.porcentaje_peso || 0);
                    const div = document.createElement('div');
                    div.className = 'flex justify-between items-start gap-4 p-4 rounded-xl border bg-white';
                    const metasHtml = (c.metas || []).map(m => `<span class="bg-[#EAF2EF] text-[#00594E] text-[10px] font-bold px-2 py-0.5 rounded-full">\${m}</span>`).join(' ');
                    const deleteBtn = yaFirmado ? '' : `<button type="button" class="text-red-500 hover:text-red-700 mt-1 flex items-center justify-center" onclick="eliminarCompromisoEvaluado(\${c.id_compromiso})"><span class="material-symbols-outlined text-lg">delete</span></button>`;
                    div.innerHTML = `\n                        <div class="flex-1">\n                            <div class="flex items-center gap-2">\n                                <span class="w-5 h-5 rounded-full bg-slate-100 flex items-center justify-center text-xs font-black text-slate-500">\${c.numero_orden}</span>\n                                <span class="font-bold text-slate-800 text-sm">\${c.porcentaje_peso}% peso</span>\n                            </div>\n                            <p class="text-xs text-slate-600 mt-1.5">\${c.descripcion}</p>\n                            <div class="flex flex-wrap gap-1 mt-2.5">\${metasHtml}</div>\n                        </div>\n                        \${deleteBtn}\n                    `;\n+                    contenedor.appendChild(div);\n+                });\n+\n+                const sumaNode = document.getElementById('compromisos-suma-peso-evaluado');\n+                const contadorNode = document.getElementById('compromisos-contador-evaluado');\n+                if (sumaNode) sumaNode.innerText = `\${sumaPesos}% / \${targetWeight}%`;\n+                if (contadorNode) contadorNode.innerText = `\${contador} compromisos (mín 7, máx 10)`;\n+\n+                const formContainer = document.getElementById('compromiso-formulario-evaluado-contenedor');\n+                const btnFirmar = document.getElementById('btn-firmar-evaluado');\n+                const firmaSec = document.getElementById('firma-evaluado-seccion');\n+                const okToSign = contador >= 7 && contador <= 10 && Math.abs(sumaPesos - targetWeight) < 0.01 && !yaFirmado;\n+\n+                if (formContainer) formContainer.classList.toggle('hidden', yaFirmado || Math.abs(sumaPesos - targetWeight) < 0.01);\n+                if (btnFirmar) {\n+                    btnFirmar.disabled = !okToSign;\n+                    btnFirmar.innerText = yaFirmado ? 'Firmado' : 'Firmar Concertación';\n+                }\n+                if (firmaSec) firmaSec.classList.toggle('hidden', yaFirmado);\n+            });\n+    }\n+\n+    function guardarEjesMisionales() {\n+        if (!selectedEvaluacionId) return;\n+        const investigacion = document.getElementById('chk-eje-investigacion')?.checked || false;\n+        const proyeccion = document.getElementById('chk-eje-proyeccion')?.checked || false;\n+\n+        fetch(`/evaluaciones/${selectedEvaluacionId}/ejes`, {\n+            method: 'POST',\n+            headers: {\n+                'Content-Type': 'application/json',\n+                'X-CSRF-TOKEN': '{{ csrf_token() }}'\n+            },\n+            body: JSON.stringify({\n+                investigacion: investigacion,\n+                proyeccion_social: proyeccion\n+            })\n+        }).then(() => {\n+            if (selectedEvaluacionData) cargarCompromisosEvaluado(selectedEvaluacionData);\n+        });\n+    }\n+\n+    function agregarCompromisoEvaluado(e) {\n+        e.preventDefault();\n+        if (!selectedEvaluacionId) return;\n+\n+        fetch(`/evaluaciones/${selectedEvaluacionId}/compromisos`, {\n+            method: 'POST',\n+            headers: {\n+                'Content-Type': 'application/json',\n+                'X-CSRF-TOKEN': '{{ csrf_token() }}'\n+            },\n+            body: JSON.stringify({\n+                descripcion: document.getElementById('comp-descripcion-evaluado')?.value || '',\n+                porcentaje_peso: parseFloat(document.getElementById('comp-peso-evaluado')?.value || '0'),\n+                metas: (document.getElementById('comp-metas-evaluado')?.value || '').split(',').map(m => m.trim()).filter(Boolean)\n+            })\n+        })\n+        .then(res => res.json())\n+        .then(data => {\n+            if (data.error) {\n+                alert(data.error);\n+            } else {\n+                document.getElementById('form-nuevo-compromiso-evaluado')?.reset();\n+                if (selectedEvaluacionData) cargarCompromisosEvaluado(selectedEvaluacionData);\n+            }\n+        });\n+    }\n+\n+    function eliminarCompromisoEvaluado(id) {\n+        if (!confirm('¿Deseas eliminar este compromiso?')) return;\n+\n+        fetch(`/compromisos/${id}`, {\n+            method: 'DELETE',\n+            headers: {\n+                'X-CSRF-TOKEN': '{{ csrf_token() }}'\n+            }\n+        })\n+        .then(res => res.json())\n+        .then(data => {\n+            if (data.error) {\n+                alert(data.error);\n+            } else if (selectedEvaluacionData) {\n+                cargarCompromisosEvaluado(selectedEvaluacionData);\n+            }\n+        });\n+    }\n+\n+    window.addEventListener('DOMContentLoaded', () => {\n+        const activeRole = \"{{ $rolActivo }}\";\n+        if (activeRole === 'admin') {\n+            navegarMenu(null, 'usuarios');\n+        } else if (activeRole === 'evaluador') {\n+            navegarMenu(null, 'usuarios-evaluador');\n+        } else {\n+            navegarMenu(null, 'evaluaciones');\n+        }\n+\n+        if (activeRole === 'evaluador') {\n+            const firstEvaluado = document.querySelector('.evaluado-card');\n+            if (firstEvaluado) firstEvaluado.click();\n+        }\n+\n+        const firstCard = document.querySelector('.empleado-card');\n+        if (activeRole === 'admin' && firstCard) {\n+            const raw = {\n+                nombres: firstCard.querySelector('h3')?.innerText?.split(' ').slice(0, -1).join(' ') || '',\n+                apellidos: firstCard.querySelector('h3')?.innerText?.split(' ').slice(-1).join(' ') || '',\n+                nombre_cargo: firstCard.dataset.cargo || '',\n+                correo_institucional: firstCard.dataset.correo || '',\n+                documento_identidad: firstCard.dataset.cedula || '',\n+                tipo_documento: '',\n+                nombre_area: firstCard.dataset.area || '',\n+                activo: (firstCard.dataset.estado || '').toLowerCase() === 'activo'\n+            };\n+            seleccionarEmpleado(firstCard, raw);\n+        }\n+    });\n+</script>`;\n+\n+const updated = content.replace(/<script>[\\s\\S]*?<\\/script>/, replacement);\n+fs.writeFileSync(filePath, updated, 'utf8');\n*** End Patch
