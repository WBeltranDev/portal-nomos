<!DOCTYPE html>
<html class="light" lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SERAG - Sistema de Evaluación del Desempeño</title>

    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="icon" type="image/png" href="/favicon.png">

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"/>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />

    @vite('resources/css/app.css')
</head>
<body class="bg-surface-container-lowest min-h-screen w-full flex flex-col font-sans text-on-surface antialiased overflow-x-hidden m-0 p-0">
    @yield('content')
    <!-- Modal Global Procesando -->
    <div id="modal-procesando" class="fixed inset-0 z-[9999] hidden items-center justify-center bg-slate-900/50 backdrop-blur-sm transition-opacity">
        <div class="rounded-3xl bg-white p-8 shadow-2xl text-center max-w-sm w-full mx-4 animate-scale-in">
            <div class="mb-4 flex justify-center">
                <svg class="h-12 w-12 animate-spin text-[#00594E]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            </div>
            <h3 class="text-xl font-black text-slate-900 mb-2">Procesando solicitud</h3>
            <p class="text-sm text-slate-500">Por favor, espera un momento. Estamos procesando la información...</p>
        </div>
    </div>

    <script>
        document.addEventListener('submit', function(e) {
            const form = e.target;
            
            // Allow bypassing this modal via data-no-modal attribute
            if (form.hasAttribute('data-no-modal')) return;

            const modalProcesando = document.getElementById('modal-procesando');
            if (modalProcesando) {
                modalProcesando.classList.remove('hidden');
                modalProcesando.classList.add('flex');
            }

            const submitBtn = form.querySelector('button[type="submit"], input[type="submit"]');
            if (submitBtn) {
                // Ensure it gets sent before disabling
                setTimeout(() => {
                    submitBtn.disabled = true;
                    if (submitBtn.tagName === 'BUTTON') submitBtn.innerHTML = 'Procesando...';
                    submitBtn.classList.add('opacity-75', 'cursor-not-allowed');
                }, 0);
            }
        });
    </script>
</body>
</html>
