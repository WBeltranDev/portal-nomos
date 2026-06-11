@extends('layouts.app')

@section('content')
<header class="flex justify-between items-center px-margin-desktop h-16 w-full fixed top-0 bg-surface-container-lowest z-50">
    <div class="flex items-center gap-2">
        <span class="material-symbols-outlined text-primary text-headline-md" style="font-variation-settings: 'FILL' 1;">eco</span>
        <span class="font-headline-md text-headline-md font-bold text-primary">Unitrópico</span>
    </div>
    <div class="text-on-surface-variant font-label-md text-label-md tracking-wider">
        SISTEMA DE EVALUACIÓN DEL DESEMPEÑO
    </div>
</header>

<main class="flex-grow flex items-center justify-center pt-16 min-h-[calc(100vh-64px)]">
    <div class="w-full max-w-[400px] flex flex-col items-center">
        
        <div class="mb-12 text-center">
            <h1 class="font-headline-lg text-headline-lg text-primary mb-2">Bienvenido</h1>
            <div class="w-10 h-1 bg-[#B5A160] mx-auto mb-4"></div>
            <p class="font-body-lg text-body-lg text-on-surface-variant">
                Ingresa para gestionar tu evaluación de desempeño
            </p>
        </div>

        <div id="login-error" class="hidden w-full mb-6 flex items-start gap-3 p-3 bg-error-container border border-error/20 rounded-lg text-xs text-on-error-container">
            <span class="material-symbols-outlined text-sm mt-0.5">error</span>
            <p>Código institucional o contraseña incorrectos.</p>
        </div>

        <form id="form-login" class="w-full space-y-10">
            
            <div class="segmented-control flex w-full p-1 bg-surface-container rounded-lg">
                <input checked="" class="hidden" id="role-evaluado" name="role" type="radio"/>
                <label class="flex-1 text-center py-2 rounded font-label-md text-label-md cursor-pointer transition-all duration-200 text-on-surface-variant" for="role-evaluado">
                    Evaluado
                </label>
                <input class="hidden" id="role-evaluador" name="role" type="radio"/>
                <label class="flex-1 text-center py-2 rounded font-label-md text-label-md cursor-pointer transition-all duration-200 text-on-surface-variant" for="role-evaluador">
                    Evaluador
                </label>
                <input class="hidden" id="role-admin" name="role" type="radio"/>
                <label class="flex-1 text-center py-2 rounded font-label-md text-label-md cursor-pointer transition-all duration-200 text-on-surface-variant" for="role-admin">
                    Admin
                </label>
            </div>

            <div class="space-y-8">
                <div class="relative group">
                    <input class="peer w-full bg-transparent border-t-0 border-x-0 border-b-2 border-primary-container focus:ring-0 focus:border-primary-container p-0 pb-2 text-body-lg text-on-surface transition-colors placeholder-transparent" id="codigo" placeholder=" " required="" type="text"/>
                    <label class="absolute left-0 -top-4 text-primary font-label-md text-label-md transition-all peer-placeholder-shown:text-body-lg peer-placeholder-shown:top-0 peer-placeholder-shown:text-on-surface-variant peer-focus:-top-4 peer-focus:text-primary peer-focus:text-label-md" for="codigo">
                        Código institucional
                    </label>
                </div>
                <div class="relative group">
                    <input class="peer w-full bg-transparent border-t-0 border-x-0 border-b-2 border-primary-container focus:ring-0 focus:border-primary-container p-0 pb-2 text-body-lg text-on-surface transition-colors placeholder-transparent" id="password" placeholder=" " required="" type="password"/>
                    <label class="absolute left-0 -top-4 text-primary font-label-md text-label-md transition-all peer-placeholder-shown:text-body-lg peer-placeholder-shown:top-0 peer-placeholder-shown:text-on-surface-variant peer-focus:-top-4 peer-focus:text-primary peer-focus:text-label-md" for="password">
                        Contraseña
                    </label>
                </div>
            </div>

            <button class="w-full py-4 bg-[#B5A160] text-on-primary font-title-lg text-title-lg rounded-lg transition-transform active:scale-95 hover:brightness-110" type="submit">
                Iniciar sesión
            </button>
        </form>
    </div>
</main>

<footer class="w-full px-margin-desktop py-8">
    <div class="border-t border-outline-variant pt-6 flex flex-col md:flex-row justify-between items-center gap-4">
        <span class="font-label-md text-label-md text-on-surface-variant">
            © 2024 Unitrópico - Institución de Educación Superior. Todos los derechos reservados.
        </span>
        <div class="flex gap-6">
            <a class="text-[#006AB4] font-label-md text-label-md hover:underline flex items-center gap-1" href="#">
                <span class="material-symbols-outlined text-[16px]">contact_support</span>
                Soporte Técnico
            </a>
            <a class="text-on-surface-variant font-label-md text-label-md hover:text-primary" href="#">
                Términos
            </a>
            <a class="text-on-surface-variant font-label-md text-label-md hover:text-primary" href="#">
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