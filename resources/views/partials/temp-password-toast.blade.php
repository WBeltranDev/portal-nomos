@if (session('temp_password'))
<div id="temp-password-toast" class="fixed bottom-4 right-4 z-50 max-w-sm rounded-2xl bg-slate-900 text-white p-4 shadow-2xl">
    <p class="text-xs uppercase tracking-[0.18em] text-[#B5A160] font-bold">Contraseña temporal generada</p>
    <p class="mt-2 text-sm">Entrega esta contraseña al usuario para su primer acceso.</p>
    <div class="flex items-center gap-2 mt-3">
        <div class="flex-1 rounded-xl bg-white/10 p-3 text-lg font-black tracking-wider text-center" id="temp-password-value">{{ session('temp_password') }}</div>
        <button onclick="navigator.clipboard.writeText(document.getElementById('temp-password-value').innerText).then(() => { this.innerText = 'Copiado!'; setTimeout(() => this.innerText = 'Copiar', 2000); })" class="bg-[#B5A160] hover:bg-[#a38e4a] text-white px-3 py-3 rounded-xl text-xs font-bold transition">Copiar</button>
    </div>
    <button onclick="document.getElementById('temp-password-toast').remove()" class="mt-3 text-xs font-bold text-white/80" type="button">Cerrar</button>
</div>
@endif
