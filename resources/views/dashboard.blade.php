@if ($rolActivo === 'admin')
    @include('dashboards.admin')
@elseif (in_array($rolActivo, ['evaluador', 'instancia_externa'], true))
    @include('dashboards.evaluador')
@else
    @include('dashboards.evaluado')
@endif
