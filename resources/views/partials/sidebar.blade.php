<aside id="sidebar-menu" class="fixed lg:relative z-40 inset-y-0 left-0 w-64 -translate-x-full lg:translate-x-0 transition-transform duration-300 ease-in-out bg-white border-r border-slate-200 flex flex-col justify-between">
    <nav class="p-2.5 pt-1 space-y-1 overflow-y-auto">
        @if (in_array($rolActivo, ['admin', 'evaluador'], true))
        <button type="button" class="sidebar-link w-full @if($rolActivo !== 'admin') active @endif flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm text-slate-700 transition" onclick="navegarMenu(this, 'usuarios')">
            <span class="material-symbols-outlined">group</span>
            Usuarios
        </button>
        @endif

        @if ($rolActivo === 'admin')
        <button type="button" class="sidebar-link w-full flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm text-slate-700 transition" onclick="navegarMenu(this, 'empleados')">
            <span class="material-symbols-outlined">badge</span>
            Empleados
        </button>
        <button type="button" class="sidebar-link w-full flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm text-slate-700 transition" onclick="navegarMenu(this, 'periodos')">
            <span class="material-symbols-outlined">calendar_today</span>
            Periodos
        </button>
        <button type="button" class="sidebar-link w-full flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm text-slate-700 transition" onclick="navegarMenu(this, 'ponderaciones')">
            <span class="material-symbols-outlined">settings</span>
            Ponderaciones
        </button>
        <button type="button" class="sidebar-link w-full flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm text-slate-700 transition" onclick="navegarMenu(this, 'recursos-admin')">
            <span class="material-symbols-outlined">gavel</span>
            Recursos y planes
        </button>
        <button type="button" class="sidebar-link w-full flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm text-slate-700 transition" onclick="navegarMenu(this, 'traslados')">
            <span class="material-symbols-outlined">swap_horiz</span>
            Traslados
        </button>
        <button type="button" class="sidebar-link w-full flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm text-slate-700 transition" onclick="navegarMenu(this, 'delegaciones')">
            <span class="material-symbols-outlined">supervisor_account</span>
            <span class="flex-1 text-left">Delegaciones</span>
            <span id="sidebar-delegaciones-badge" class="hidden px-2 py-0.5 text-[10px] font-black rounded-full bg-amber-500 text-white shadow-sm animate-pulse" title="Funcionarios a 1 día de retornar al trabajo">0</span>
        </button>
        @endif

        @if ($rolActivo !== 'instancia_externa')
        <button type="button" class="sidebar-link w-full @if($rolActivo !== 'admin') active @endif flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm text-slate-700 transition" onclick="navegarMenu(this, '{{ $rolActivo === 'evaluador' ? 'evaluaciones-evaluador' : 'evaluaciones' }}')">
            <span class="material-symbols-outlined">fact_check</span>
            Evaluaciones
        </button>
        @if ($rolActivo === 'evaluado')
        <button type="button" class="sidebar-link w-full flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm text-slate-700 transition" onclick="navegarMenu(this, 'reportes')">
            <span class="material-symbols-outlined">description</span>
            Exportar PDF
        </button>
        @endif
        @endif

        @if ($rolActivo === 'instancia_externa' || $rolActivo === 'evaluador')
        <button type="button" class="sidebar-link w-full @if($rolActivo === 'instancia_externa') active @endif flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm text-slate-700 transition" onclick="navegarMenu(this, 'instancia-externa')">
            <span class="material-symbols-outlined">school</span>
            Notas Componente Académico
        </button>
        @endif
    </nav>

    <div class="p-4 border-t border-slate-100">
        <div class="rounded-xl bg-[#EAF2EF] p-4">
            <p class="text-xs font-bold text-[#00594E] uppercase tracking-[0.18em]">Sesión activa</p>
            <p class="text-sm font-semibold text-slate-800 mt-1">{{ $usuario['nombres'] ?? '' }} {{ $usuario['apellidos'] ?? '' }}</p>
            <p class="text-xs text-slate-500">{{ $usuario['correo'] ?? '' }}</p>
        </div>
    </div>
</aside>
