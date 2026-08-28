<header class="app-header flex items-center justify-between px-4 sm:px-6 lg:px-8">
    <div class="flex items-center gap-3 min-w-0">
        <button onclick="toggleSidebar()" class="lg:hidden p-2 rounded-lg hover:bg-white/10" type="button" aria-label="Abrir menú">
            <span class="material-symbols-outlined">menu</span>
        </button>
        <img src="/logo.png" alt="SERAG" class="h-9 sm:h-10 w-auto object-contain drop-shadow -mt-1" />
        <div class="hidden sm:flex flex-col">
            <span class="text-[10px] sm:text-xs font-bold uppercase tracking-[0.18em] text-[#B5A160]">SERAG</span>
            <span class="text-xs sm:text-sm font-semibold text-white/90">Sistema de Evaluación del Desempeño</span>
        </div>
        <div class="sm:hidden text-xs font-semibold text-white/90">SERAG</div>
    </div>

    <div class="flex items-center gap-3 sm:gap-4">
        <div class="hidden sm:flex flex-col items-end text-right leading-tight">
            <span class="text-sm font-semibold text-white">{{ $usuario['nombres'] ?? '' }} {{ $usuario['apellidos'] ?? '' }}</span>
            <span class="text-[10px] uppercase tracking-[0.18em] text-[#B5A160] font-bold">{{ $rolActivo }}</span>
        </div>

        {{-- Campana de notificaciones (Solo Admin) --}}
        @if(($rolActivo ?? '') === 'admin')
        <div class="relative" id="notificaciones-container">
            <button type="button" onclick="toggleNotificaciones()" class="relative p-2 rounded-lg hover:bg-white/10 transition" title="Notificaciones">
                <span class="material-symbols-outlined text-white/80 text-xl">notifications</span>
                <span id="notif-badge" class="absolute -top-0.5 -right-0.5 min-w-[18px] h-[18px] flex items-center justify-center rounded-full bg-red-500 text-white text-[10px] font-bold px-1 {{ ($notificacionesNoLeidas ?? 0) > 0 ? '' : 'hidden' }}">{{ $notificacionesNoLeidas ?? 0 }}</span>
            </button>

            <div id="notif-dropdown" class="hidden absolute right-0 mt-2 w-80 sm:w-96 bg-white rounded-2xl shadow-2xl border border-slate-100 z-50 overflow-hidden">
                <div class="flex items-center justify-between px-4 py-3 border-b border-slate-100">
                    <h3 class="text-xs font-black text-slate-800 uppercase tracking-wide">Notificaciones</h3>
                    <button type="button" onclick="marcarTodasLeidas()" class="text-[10px] font-bold text-[#00594E] hover:underline">Marcar todas leídas</button>
                </div>
                <div id="notif-lista" class="max-h-80 overflow-y-auto divide-y divide-slate-50">
                    <div class="py-6 text-center text-xs text-slate-400">Cargando...</div>
                </div>
                <div id="notif-vacia" class="hidden py-6 text-center text-xs text-slate-400">No hay notificaciones.</div>
            </div>
        </div>
        @endif

        <div class="relative">
            <button type="button" onclick="toggleProfileMenu()" class="flex items-center gap-3 rounded-full pl-1 pr-3 py-1.5 hover:bg-white/10 transition">
                <div class="w-10 h-10 rounded-full bg-[#B5A160] text-white font-black flex items-center justify-center shadow-lg">
                    {{ strtoupper(substr($usuario['nombres'] ?? 'U', 0, 1) . substr($usuario['apellidos'] ?? 'X', 0, 1)) }}
                </div>
                <span class="material-symbols-outlined text-white/80 text-base hidden sm:block">expand_more</span>
            </button>

            @include('partials.profile-menu')
        </div>
    </div>
</header>
