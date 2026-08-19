<div id="modal-editar-delegacion" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-950/55 p-4 overflow-y-auto">
    <div class="w-full max-w-lg rounded-3xl bg-white p-6 shadow-2xl space-y-4 my-8">
        <div class="flex items-start justify-between gap-4 border-b border-slate-100 pb-3">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-amber-500/10 text-amber-700 flex items-center justify-center font-black">
                    <span class="material-symbols-outlined text-2xl">edit_calendar</span>
                </div>
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-[#00594E]">Delegación de funciones</p>
                    <h3 class="text-lg font-black text-slate-900">Editar / Ampliar Vigencia</h3>
                </div>
            </div>
            <button type="button" onclick="cerrarModalEditarDelegacion()" class="rounded-full p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>

        <form id="form-editar-delegacion" onsubmit="guardarEdicionDelegacion(event)" class="space-y-3.5">
            <input type="hidden" id="edit-delegacion-id" name="id_delegacion" />

            <div class="rounded-2xl border border-slate-200 bg-slate-50/80 p-3 text-xs space-y-1.5">
                <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-base text-[#00594E]">person</span>
                    <span class="text-slate-700"><b>Titular (Delegante):</b> <span id="edit-delegante-nombre" class="font-bold text-slate-900"></span></span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-base text-[#B5A160]">arrow_right_alt</span>
                    <span class="text-slate-700"><b>Delegado:</b> <span id="edit-delegado-nombre" class="font-bold text-slate-900"></span></span>
                </div>
            </div>

            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="block text-[10px] font-bold text-slate-600 uppercase mb-1">Fecha inicio (vigencia)</label>
                    <input type="date" id="edit-fecha-inicio" name="fecha_inicio" class="w-full text-xs rounded-xl border border-slate-200 p-2.5 bg-slate-100 text-slate-600 outline-none" readonly />
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-600 uppercase mb-1">Fecha fin (opcional — vigencia abierta)</label>
                    <input type="date" id="edit-fecha-fin" name="fecha_fin" class="w-full text-xs rounded-xl border border-slate-200 p-2.5 bg-white outline-none focus:border-[#00594E]" />
                    <p class="text-[9px] text-slate-400 mt-0.5">Vacío = vigencia abierta / indefinida</p>
                </div>
            </div>

            <div>
                <label class="block text-[10px] font-bold text-slate-600 uppercase mb-1">Motivo</label>
                <input type="text" id="edit-motivo" name="motivo" maxlength="500" class="w-full text-xs rounded-xl border border-slate-200 p-2.5 bg-white outline-none focus:border-[#00594E]" placeholder="Ej. Vacaciones, licencia, comisión" />
            </div>

            <!-- ACTO ADMINISTRATIVO (D-02) -->
            <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-3.5 space-y-3">
                <div class="flex items-center gap-2 text-slate-700 font-bold text-xs border-b border-slate-200/80 pb-2">
                    <span class="material-symbols-outlined text-base text-[#00594E]">description</span>
                    <span>Acto Administrativo de la Delegación</span>
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="block text-[10px] font-bold text-slate-600 uppercase mb-1">Tipo / Referencia</label>
                        <input type="text" id="edit-acto-administrativo" name="acto_administrativo" maxlength="255" class="w-full text-xs rounded-xl border border-slate-200 p-2 bg-white outline-none focus:border-[#00594E]" placeholder="Ej: Resolución Rectoral" />
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-600 uppercase mb-1">Número de acto</label>
                        <input type="text" id="edit-acto-numero" name="acto_administrativo_numero" maxlength="100" class="w-full text-xs rounded-xl border border-slate-200 p-2 bg-white outline-none focus:border-[#00594E]" placeholder="Ej: Res. No. 0450 de 2026" />
                    </div>
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="block text-[10px] font-bold text-slate-600 uppercase mb-1">Fecha de expedición</label>
                        <input type="date" id="edit-acto-fecha" name="acto_administrativo_fecha" class="w-full text-xs rounded-xl border border-slate-200 p-2 bg-white outline-none focus:border-[#00594E]" />
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-600 uppercase mb-1">Enlace documento (Drive)</label>
                        <input type="url" id="edit-acto-url" name="acto_administrativo_url" maxlength="1000" class="w-full text-xs rounded-xl border border-slate-200 p-2 bg-white outline-none focus:border-[#00594E]" placeholder="https://drive.google.com/..." />
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-end gap-2.5 pt-2">
                <button type="button" onclick="cerrarModalEditarDelegacion()" class="px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-100 rounded-xl transition">Cancelar</button>
                <button type="submit" id="btn-guardar-edicion-delegacion" class="px-4 py-2.5 text-xs font-bold text-white bg-[#00594E] hover:bg-[#00473e] rounded-xl transition shadow-md shadow-[#00594E]/20 flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-sm">save</span> Guardar cambios
                </button>
            </div>
        </form>
    </div>
</div>
