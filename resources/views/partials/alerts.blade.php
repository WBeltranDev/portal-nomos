@if(session('success_periodo') || session('success_ponderacion') || session('success_asignacion') || session('success_import') || session('success_firma') || session('success_traslado'))
    <div class="mb-6 p-4 bg-green-50 border border-green-200 text-green-700 rounded-2xl flex items-center gap-3 text-sm">
        <span class="material-symbols-outlined">check_circle</span>
        <p>{{ session('success_periodo') ?? session('success_ponderacion') ?? session('success_asignacion') ?? session('success_import') ?? session('success_firma') ?? session('success_traslado') }}</p>
    </div>
@endif

@if($errors->any())
    <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-700 rounded-2xl flex items-start gap-3 text-sm">
        <span class="material-symbols-outlined mt-0.5">error</span>
        <div class="space-y-1">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    </div>
@endif
