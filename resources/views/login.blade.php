@extends('layouts.app')

@section('content')
<style>
    body {
        overflow-y: auto !important;
        height: auto !important;
        min-height: 100vh;
        background:
            radial-gradient(circle at top left, rgba(181, 161, 96, 0.14), transparent 30%),
            radial-gradient(circle at bottom right, rgba(0, 64, 55, 0.12), transparent 28%),
            linear-gradient(180deg, #f8faf8 0%, #f3f6f4 100%);
    }

    .login-shell {
        width: min(100%, 38rem);
    }

    .login-card {
        background: rgba(255, 255, 255, 0.82);
        backdrop-filter: blur(14px);
        border: 1px solid rgba(15, 23, 42, 0.08);
        box-shadow: 0 20px 60px rgba(15, 23, 42, 0.12);
    }
</style>

<header class="flex justify-between items-center px-4 sm:px-margin-desktop h-16 w-full fixed top-0 bg-surface-container-lowest z-50 border-b border-slate-100">
    <div class="flex items-center gap-2">
        <span class="material-symbols-outlined text-primary text-xl" style="font-variation-settings: 'FILL' 1;">eco</span>
        <span class="text-base sm:text-lg font-bold text-primary">Unitrópico</span>
    </div>
    <div class="text-on-surface-variant text-[10px] sm:text-xs font-semibold tracking-wider text-right max-w-[200px] sm:max-w-none">
        SISTEMA DE EVALUACIÓN DEL DESEMPEÑO
    </div>
</header>

<main class="flex-grow flex items-center justify-center pt-20 sm:pt-24 pb-6 sm:pb-8 px-4 w-full min-h-[calc(100vh-64px)] overflow-x-hidden">
    <div class="login-shell w-full flex flex-col items-stretch">
        <div class="login-card rounded-3xl p-6 sm:p-8 lg:p-10">
            <div class="mb-8 sm:mb-10 text-center">
                <div class="inline-flex items-center gap-2 rounded-full bg-[#EAF2EF] px-4 py-1.5 mb-4">
                    <span class="material-symbols-outlined text-primary text-base">verified_user</span>
                    <span class="text-xs font-semibold tracking-wide text-primary uppercase">Acceso institucional</span>
                </div>
                <h1 class="text-3xl sm:text-4xl lg:text-5xl font-bold text-primary mb-3 leading-tight">Bienvenido</h1>
                <div class="w-16 h-1.5 bg-[#B5A160] mx-auto mb-4 rounded-full"></div>
                <p class="text-sm sm:text-base lg:text-lg text-on-surface-variant max-w-lg mx-auto leading-relaxed">
                    Ingresa para gestionar tu evaluación de desempeño
                </p>
            </div>

            <div id="login-error" class="{{ $errors->has('login') ? '' : 'hidden' }} w-full mb-6 flex items-start gap-3 p-3 bg-error-container border border-error/20 rounded-lg text-xs text-on-error-container">
                <span class="material-symbols-outlined text-sm mt-0.5">error</span>
                <p>{{ $errors->first('login', 'Correo institucional o contraseña incorrectos.') }}</p>
            </div>

            <form id="form-login" class="w-full space-y-6 sm:space-y-8" method="POST" action="{{ route('login.store') }}">
                @csrf

                <div class="space-y-5 sm:space-y-6">
                    <div class="relative group">
                        <input class="peer w-full rounded-2xl border border-slate-200 bg-white px-4 py-4 sm:px-5 sm:py-4 text-base sm:text-lg text-on-surface transition-all placeholder-transparent focus:outline-none focus:border-primary focus:ring-4 focus:ring-primary/10 shadow-sm" id="correo" name="correo" placeholder=" " required type="email" value="{{ old('correo') }}" />
                        <label class="absolute left-4 top-4 text-slate-400 text-sm sm:text-base transition-all duration-200 pointer-events-none bg-white px-1 peer-placeholder-shown:top-4 peer-placeholder-shown:text-base peer-focus:-top-2 peer-focus:text-xs peer-focus:text-primary peer-[:not(:placeholder-shown)]:-top-2 peer-[:not(:placeholder-shown)]:text-xs peer-[:not(:placeholder-shown)]:text-primary" for="correo">
                            Correo institucional
                        </label>
                    </div>
                    <div class="relative group">
                        <input class="peer w-full rounded-2xl border border-slate-200 bg-white px-4 py-4 sm:px-5 sm:py-4 text-base sm:text-lg text-on-surface transition-all placeholder-transparent focus:outline-none focus:border-primary focus:ring-4 focus:ring-primary/10 shadow-sm" id="password" name="password" placeholder=" " required type="password" />
                        <label class="absolute left-4 top-4 text-slate-400 text-sm sm:text-base transition-all duration-200 pointer-events-none bg-white px-1 peer-placeholder-shown:top-4 peer-placeholder-shown:text-base peer-focus:-top-2 peer-focus:text-xs peer-focus:text-primary peer-[:not(:placeholder-shown)]:-top-2 peer-[:not(:placeholder-shown)]:text-xs peer-[:not(:placeholder-shown)]:text-primary" for="password">
                            Contraseña
                        </label>
                    </div>
                </div>

                <button class="w-full py-4 sm:py-4.5 bg-[#B5A160] text-on-primary text-base sm:text-lg font-bold rounded-2xl transition-all active:scale-[0.99] hover:brightness-110 shadow-lg shadow-[#B5A160]/25" type="submit">
                    Iniciar sesión
                </button>
            </form>
        </div>
    </div>
</main>

<footer class="w-full px-4 sm:px-margin-desktop py-6 border-t border-outline-variant bg-white/80 backdrop-blur-sm text-center">
    <span class="text-xs sm:text-sm font-medium text-on-surface-variant">© 2026 Unitrópico</span>
</footer>

<script>
    document.querySelectorAll('input').forEach(input => {
        input.addEventListener('focus', () => {
            input.parentElement.classList.add('is-focused');
        });
        input.addEventListener('blur', () => {
            input.parentElement.classList.remove('is-focused');
        });
    });

    const submitBtn = document.querySelector('button[type="submit"]');
    submitBtn.addEventListener('mousedown', () => submitBtn.style.transform = 'scale(0.98)');
    submitBtn.addEventListener('mouseup', () => submitBtn.style.transform = 'scale(1)');
    submitBtn.addEventListener('mouseleave', () => submitBtn.style.transform = 'scale(1)');
</script>
@endsection
