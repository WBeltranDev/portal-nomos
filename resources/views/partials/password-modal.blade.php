<div id="password-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-950/55 px-4">
    <div class="w-full max-w-md rounded-3xl bg-white p-6 shadow-2xl">
        <div class="flex items-start justify-between gap-4 mb-5">
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.18em] text-[#00594E]">Seguridad</p>
                <h3 class="text-xl font-black text-slate-900">Cambiar contraseña</h3>
            </div>
            <button type="button" onclick="closePasswordModal()" class="rounded-full p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <form method="POST" action="{{ route('password.update') }}" class="space-y-4">
            @csrf
            <div>
                <label class="block text-sm font-bold text-slate-700 mb-1">Contraseña actual</label>
                <input type="password" name="current_password" class="w-full rounded-xl border border-slate-200 px-4 py-3" required>
            </div>
            <div>
                <label class="block text-sm font-bold text-slate-700 mb-1">Nueva contraseña</label>
                <div class="flex items-stretch rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                    <input type="password" name="password" id="nueva-password" class="w-full bg-transparent px-4 py-3 outline-none" required>
                    <button type="button" onclick="togglePasswordVisibility('nueva-password', this)" class="flex flex-none items-center justify-center px-3 border-l border-slate-200 bg-slate-50 text-slate-500 hover:text-slate-700 hover:bg-slate-100 active:bg-slate-200 transition shadow-[inset_0_1px_2px_rgba(0,0,0,0.04)]" title="Mostrar contraseña">
                        <span class="material-symbols-outlined leading-none select-none pointer-events-none" style="font-size:20px;">visibility</span>
                    </button>
                </div>
            </div>
            <div>
                <label class="block text-sm font-bold text-slate-700 mb-1">Confirmar nueva contraseña</label>
                <div class="flex items-stretch rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                    <input type="password" name="password_confirmation" id="confirmar-password" class="w-full bg-transparent px-4 py-3 outline-none" required>
                    <button type="button" onclick="togglePasswordVisibility('confirmar-password', this)" class="flex flex-none items-center justify-center px-3 border-l border-slate-200 bg-slate-50 text-slate-500 hover:text-slate-700 hover:bg-slate-100 active:bg-slate-200 transition shadow-[inset_0_1px_2px_rgba(0,0,0,0.04)]" title="Mostrar contraseña">
                        <span class="material-symbols-outlined leading-none select-none pointer-events-none" style="font-size:20px;">visibility</span>
                    </button>
                </div>
            </div>
            <button type="submit" class="w-full rounded-2xl bg-[#00594E] text-white font-bold py-3">Guardar cambio</button>
        </form>
    </div>
</div>
