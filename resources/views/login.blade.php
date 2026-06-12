@extends('layouts.app')

@section('content')
<style>
    body {
        overflow-y: auto !important;
        height: auto !important;
        min-height: 100vh;
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

<main class="flex-grow flex items-center justify-center pt-24 pb-8 px-4 w-full min-h-[calc(100vh-64px)] overflow-x-hidden">
    <div class="w-full max-w-[400px] flex flex-col items-center">
        
        <div class="mb-8 text-center">
            <h1 class="text-2xl sm:text-3xl font-bold text-primary mb-2">Bienvenido</h1>
            <div class="w-10 h-1 bg-[#B5A160] mx-auto mb-4"></div>
            <p class="text-sm sm:text-base text-on-surface-variant">
                Ingresa para gestionar tu evaluación de desempeño
            </p>
        </div>

        <div id="login-error" class="hidden w-full mb-6 flex items-start gap-3 p-3 bg-error-container border border-error/20 rounded-lg text-xs text-on-error-container">
            <span class="material-symbols-outlined text-sm mt-0.5">error</span>
            <p>Código institucional o contraseña incorrectos.</p>
        </div>

        <form id="form-login" class="w-full space-y-8">
            
            <div class="segmented-control flex w-full p-1 bg-surface-container rounded-lg">
                <input checked="" class="hidden" id="role-evaluado" name="role" type="radio"/>
                <label class="flex-1 text-center py-2 rounded text-xs sm:text-sm cursor-pointer transition-all duration-200 text-on-surface-variant font-medium" for="role-evaluado">
                    Evaluado
                </label>
                <input class="hidden" id="role-evaluador" name="role" type="radio"/>
                <label class="flex-1 text-center py-2 rounded text-xs sm:text-sm cursor-pointer transition-all duration-200 text-on-surface-variant font-medium" for="role-evaluador">
                    Evaluador
                </label>
                <input class="hidden" id="role-admin" name="role" type="radio"/>
                <label class="flex-1 text-center py-2 rounded text-xs sm:text-sm cursor-pointer transition-all duration-200 text-on-surface-variant font-medium" for="role-admin">
                    Admin
                </label>
            </div>

            <div class="space-y-6">
                <div class="relative group">
                    <input class="peer w-full bg-transparent border-t-0 border-x-0 border-b-2 border-primary-container focus:ring-0 focus:border-primary-container p-0 pb-1.5 text-sm sm:text-base text-on-surface transition-colors placeholder-transparent focus:outline-none" id="codigo" placeholder=" " required="" type="text"/>
                    <label class="absolute left-0 top-1 text-slate-400 text-sm sm:text-base transition-all duration-200 pointer-events-none peer-placeholder-shown:top-1 peer-placeholder-shown:text-sm sm:peer-placeholder-shown:text-base peer-focus:-top-4 peer-focus:text-xs peer-focus:text-primary peer-[:not(:placeholder-shown)]:-top-4 peer-[:not(:placeholder-shown)]:text-xs peer-[:not(:placeholder-shown)]:text-primary" for="codigo">
                        Código institucional
                    </label>
                </div>
                <div class="relative group">
                    <input class="peer w-full bg-transparent border-t-0 border-x-0 border-b-2 border-primary-container focus:ring-0 focus:border-primary-container p-0 pb-1.5 text-sm sm:text-base text-on-surface transition-colors placeholder-transparent focus:outline-none" id="password" placeholder=" " required="" type="password"/>
                    <label class="absolute left-0 top-1 text-slate-400 text-sm sm:text-base transition-all duration-200 pointer-events-none peer-placeholder-shown:top-1 peer-placeholder-shown:text-sm sm:peer-placeholder-shown:text-base peer-focus:-top-4 peer-focus:text-xs peer-focus:text-primary peer-[:not(:placeholder-shown)]:-top-4 peer-[:not(:placeholder-shown)]:text-xs peer-[:not(:placeholder-shown)]:text-primary" for="password">
                        Contraseña
                    </label>
                </div>
            </div>

            <button class="w-full py-3 bg-[#B5A160] text-on-primary text-sm sm:text-base font-bold rounded-lg transition-transform active:scale-95 hover:brightness-110" type="submit">
                Iniciar sesión
            </button>
        </form>
    </div>
</main>

<footer class="w-full px-4 sm:px-margin-desktop py-6 border-t border-outline-variant bg-surface-container-lowest">
    <div class="flex flex-col md:flex-row justify-between items-center gap-4 text-center md:text-left">
        <span class="text-[10px] sm:text-xs text-on-surface-variant font-medium">
            © 2024 Unitrópico - Institución de Educación Superior. Todos los derechos reservados.
        </span>
        <div class="flex flex-wrap justify-center gap-4 sm:gap-6">
            <a class="text-[#006AB4] text-[10px] sm:text-xs font-semibold hover:underline flex items-center gap-1" href="#">
                <span class="material-symbols-outlined text-[14px]">contact_support</span>
                Soporte Técnico
            </a>
            <a class="text-on-surface-variant text-[10px] sm:text-xs hover:text-primary font-medium" href="#">
                Términos
            </a>
            <a class="text-on-surface-variant text-[10px] sm:text-xs hover:text-primary font-medium" href="#">
                Privacidad
            </a>
        </div>
    </div>
</footer>

<script>
    // Micro-interacciones originales para los inputs
    document.querySelectorAll('input').forEach(input => {
        input.addEventListener('focus', () => {
            input.parentElement.classList.add('is-focused');
        });
        input.addEventListener('blur', () => {
            input.parentElement.classList.remove('is-focused');
        });
    });

    // Efecto del botón submit
    const submitBtn = document.querySelector('button[type="submit"]');
    submitBtn.addEventListener('mousedown', () => submitBtn.style.transform = 'scale(0.98)');
    submitBtn.addEventListener('mouseup', () => submitBtn.style.transform = 'scale(1)');
    submitBtn.addEventListener('mouseleave', () => submitBtn.style.transform = 'scale(1)');

    // Interceptor hardcodeado para redirigir al Dashboard aprobado
    document.getElementById('form-login').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const codigo = document.getElementById('codigo').value.trim();
        const pass = document.getElementById('password').value;
        const errorDiv = document.getElementById('login-error');
        
        if (codigo === 'admin' && pass === '123456') {
            errorDiv.classList.add('hidden');
            window.location.href = '/dashboard';
        } else {
            errorDiv.classList.remove('hidden');
        }
    });
</script>
@endsection