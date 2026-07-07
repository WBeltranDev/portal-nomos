@extends('layouts.app')

@section('content')
<main class="min-h-screen flex items-center justify-center px-4 py-10 bg-[radial-gradient(circle_at_top_left,_rgba(181,161,96,0.14),_transparent_30%),radial-gradient(circle_at_bottom_right,_rgba(0,64,55,0.12),_transparent_28%),linear-gradient(180deg,_#f8faf8_0%,_#f3f6f4_100%)]">
    <div class="w-full max-w-xl bg-white/85 backdrop-blur-md rounded-3xl shadow-xl border border-slate-100 p-6 sm:p-8 lg:p-10">
        <div class="mb-8">
            <div class="inline-flex items-center gap-2 rounded-full bg-[#EAF2EF] px-4 py-1.5 mb-4">
                <span class="material-symbols-outlined text-primary text-base">swap_horiz</span>
                <span class="text-xs font-semibold tracking-wide text-primary uppercase">Múltiples perfiles</span>
            </div>
            <h1 class="text-3xl sm:text-4xl font-bold text-primary">Selecciona cómo ingresar</h1>
            <p class="mt-3 text-sm sm:text-base text-slate-600 max-w-lg leading-relaxed">
                Tu correo tiene más de un perfil disponible. Elige el acceso que deseas usar.
            </p>
        </div>

        <form method="POST" action="{{ route('role.select') }}" class="space-y-3 sm:space-y-4">
            @csrf
            @foreach ($roles as $role)
                <label class="flex items-center gap-4 rounded-2xl border border-slate-200 px-5 py-4 cursor-pointer hover:border-primary hover:bg-slate-50 transition-all">
                    <input type="radio" name="rol" value="{{ $role }}" class="text-primary scale-110" @checked($loop->first) />
                    <span class="font-semibold capitalize text-slate-800 text-base sm:text-lg">{{ $role }}</span>
                </label>
            @endforeach

            <button type="submit" class="w-full mt-5 py-4 rounded-2xl bg-[#B5A160] text-white font-bold text-base sm:text-lg hover:brightness-110 transition shadow-lg shadow-[#B5A160]/20">
                Continuar
            </button>
        </form>
    </div>
</main>
@endsection
