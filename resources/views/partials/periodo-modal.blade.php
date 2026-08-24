<div id="modal-editar-periodo" class="fixed inset-0 z-50 hidden bg-slate-900/50 backdrop-blur-sm items-center justify-center overflow-y-auto">
    <div class="relative w-full max-w-lg bg-white rounded-3xl shadow-2xl m-4">
        <form id="form-editar-periodo" method="POST" action="" class="flex flex-col max-h-[90vh]">
            @csrf
            
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between shrink-0">
                <div>
                    <h3 class="text-lg font-black text-slate-900" id="editar-periodo-titulo">Editar Periodo</h3>
                    <p class="text-[10px] uppercase font-bold text-slate-400 mt-1">Ajuste de fechas y estados</p>
                </div>
                <button type="button" onclick="window.cerrarEditarPeriodo(event)" aria-label="Cerrar edición de período" class="w-8 h-8 flex items-center justify-center rounded-full bg-slate-100 text-slate-500 hover:bg-slate-200 transition">
                    <span class="material-symbols-outlined text-sm">close</span>
                </button>
            </div>

            <div class="p-6 overflow-y-auto space-y-4">
                <input type="hidden" id="editar-periodo-id" name="id">
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[10px] font-bold text-slate-600 uppercase mb-1">Fecha Inicio Periodo</label>
                        <input type="date" id="editar-periodo-inicio" name="fecha_inicio" class="w-full text-xs rounded-xl border border-slate-200 p-2.5 bg-white outline-none focus:border-[#00594E]" required>
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-600 uppercase mb-1">Fecha Fin Periodo</label>
                        <input type="date" id="editar-periodo-fin" name="fecha_fin" class="w-full text-xs rounded-xl border border-slate-200 p-2.5 bg-white outline-none focus:border-[#00594E]" required>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[10px] font-bold text-slate-600 uppercase mb-1 text-[#00594E]">Inicio Concertación</label>
                        <input type="date" id="editar-periodo-inicio-concertacion" name="fecha_inicio_concertacion" class="w-full text-xs rounded-xl border border-slate-200 p-2.5 bg-white outline-none focus:border-[#00594E]">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-600 uppercase mb-1 text-red-600">Cierre Concertación</label>
                        <input type="date" id="editar-periodo-fin-concertacion" name="fecha_fin_concertacion" class="w-full text-xs rounded-xl border border-slate-200 p-2.5 bg-white outline-none focus:border-red-500">
                    </div>
                </div>

                <div>
                    <label class="block text-[10px] font-bold text-slate-600 uppercase mb-1">Descripción</label>
                    <input type="text" id="editar-periodo-descripcion" name="descripcion" maxlength="200" class="w-full text-xs rounded-xl border border-slate-200 p-2.5 bg-white outline-none focus:border-[#00594E]">
                </div>

                <div>
                    <label class="block text-[10px] font-bold text-slate-600 uppercase mb-1">Estado</label>
                    <select id="editar-periodo-estado" name="estado" class="w-full text-xs rounded-xl border border-slate-200 p-2.5 bg-white outline-none focus:border-[#00594E]" required>
                        <option value="ABIERTO">ABIERTO</option>
                        <option value="CERRADO">CERRADO</option>
                    </select>
                </div>
            </div>

            <div class="px-6 py-4 border-t border-slate-100 bg-slate-50 rounded-b-3xl shrink-0">
                <button type="submit" class="w-full bg-[#B5A160] hover:brightness-110 text-white rounded-xl py-2.5 text-xs font-bold transition shadow-md shadow-[#B5A160]/20">
                    Guardar Cambios
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    window.abrirModalPeriodo = function(p) {
        document.getElementById('editar-periodo-id').value = p.id_periodo;
        document.getElementById('editar-periodo-inicio').value = p.fecha_inicio ? p.fecha_inicio.substring(0, 10) : '';
        document.getElementById('editar-periodo-fin').value = p.fecha_fin ? p.fecha_fin.substring(0, 10) : '';
        document.getElementById('editar-periodo-inicio-concertacion').value = p.fecha_inicio_concertacion ? p.fecha_inicio_concertacion.substring(0, 10) : '';
        document.getElementById('editar-periodo-fin-concertacion').value = p.fecha_fin_concertacion ? p.fecha_fin_concertacion.substring(0, 10) : '';
        document.getElementById('editar-periodo-descripcion').value = p.descripcion || '';
        document.getElementById('editar-periodo-estado').value = p.estado;
        document.getElementById('editar-periodo-titulo').innerText = `${p.sistema} · ${p.anio}-${Number(p.semestre) === 1 ? 'A' : 'B'}`;
        document.getElementById('form-editar-periodo').action = `/admin/periodos/${p.id_periodo}`;

        const modal = document.getElementById('modal-editar-periodo');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    };

    window.cerrarEditarPeriodo = function(event) {
        event?.preventDefault();
        const modal = document.getElementById('modal-editar-periodo');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    };
</script>
