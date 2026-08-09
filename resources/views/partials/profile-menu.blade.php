<div id="profile-menu" class="profile-menu p-2">
    <div class="px-4 py-3 border-b border-slate-100">
        <p class="text-sm font-bold text-slate-800">{{ $usuario['nombres'] ?? '' }} {{ $usuario['apellidos'] ?? '' }}</p>
        <p class="text-xs text-slate-500">{{ $usuario['correo'] ?? '' }}</p>
        <p class="text-[10px] uppercase tracking-[0.18em] text-[#00594E] font-bold mt-1">{{ $rolActivo }}</p>
    </div>
    @if(count($usuario['roles'] ?? []) > 1)
    <a href="/seleccionar-rol" class="w-full text-left px-4 py-3 rounded-lg hover:bg-slate-50 text-sm font-medium text-slate-700 flex items-center gap-2">
        <span class="material-symbols-outlined text-base">swap_horiz</span>
        Cambiar perfil
    </a>
    @endif
    <button type="button" onclick="openPasswordModal()" class="w-full text-left px-4 py-3 rounded-lg hover:bg-slate-50 text-sm font-medium text-slate-700 flex items-center gap-2">
        <span class="material-symbols-outlined text-base">lock_reset</span>
        Cambiar contraseña
    </button>
    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit" class="w-full text-left px-4 py-3 rounded-lg hover:bg-red-50 text-sm font-medium text-red-600 flex items-center gap-2">
            <span class="material-symbols-outlined text-base">logout</span>
            Cerrar sesión
        </button>
    </form>
</div>
