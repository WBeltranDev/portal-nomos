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
