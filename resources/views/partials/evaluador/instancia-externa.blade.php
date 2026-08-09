<section id="section-instancia-externa" class="section-content hidden space-y-6">
    <div class="grid gap-6 lg:grid-cols-[0.8fr_1.2fr]">
        <div class="panel-card rounded-3xl p-6 h-fit">
            <div class="mb-3">
                <p class="text-xs font-bold uppercase tracking-[0.22em] text-[#00594E]">Instancia Externa</p>
                <h2 class="text-xl font-black text-slate-900">Evaluados de Acuerdo de Gestión</h2>
                <p class="text-xs text-slate-500 mt-1">Vicerrectoría de Investigación, Vicerrectoría de Proyección Social y CEDP cargan aquí las notas del componente académico (docencia, investigación, proyección social) para líderes de programa, departamento o director de escuela.</p>
            </div>
            <div id="instancia-externa-lista" class="space-y-3">
                <div class="py-6 text-center text-slate-500 text-xs">Cargando evaluados...</div>
            </div>
        </div>

        <div class="space-y-6 lg:sticky lg:top-6">
            <div id="panel-instancia-externa" class="panel-card rounded-3xl p-6 hidden">
                <div class="flex items-start justify-between gap-4 pb-4 border-b border-slate-100">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.22em] text-[#00594E]">Ejes misionales</p>
                        <h3 id="instancia-externa-nombre" class="text-xl font-black text-slate-900 mt-1 leading-snug">Selecciona un evaluado</h3>
                        <p id="instancia-externa-detalle" class="text-xs text-slate-500">-</p>
                    </div>
                </div>
                <form id="form-instancia-externa" class="mt-4 space-y-3" onsubmit="guardarNotasInstanciaExterna(event)">
                    <div id="instancia-externa-ejes-contenedor" class="space-y-3"></div>
                    <div class="flex items-center justify-between gap-3 pt-2">
                        <span id="instancia-externa-mensaje" class="hidden text-xs font-semibold"></span>
                        <button type="submit" class="bg-[#00594E] text-white px-4 py-2 rounded-xl text-xs font-bold hover:brightness-110 transition ml-auto">Guardar notas</button>
                    </div>
                </form>
            </div>

            <div id="panel-instancia-externa-empty" class="panel-card rounded-3xl p-8 flex flex-col items-center justify-center text-center text-slate-400">
                <span class="material-symbols-outlined text-5xl text-slate-300 mb-3">school</span>
                <p class="text-sm">Selecciona un evaluado de la lista para cargar sus notas de componente académico.</p>
            </div>
        </div>
    </div>
</section>
