@php
    $porDependencia = $info['plazas']->groupBy('dependencia');
    $todoValidado = $info['total'] > 0 && $info['validadas'] === $info['total'];
    $sistema = fn ($s) => match ($s) { 'RENDIMIENTO_LABORAL' => 'RL', 'ACUERDO_GESTION' => 'AG', default => '' };
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
    * { font-family: "DejaVu Sans", sans-serif; }
    body { font-size: 8px; color: #1e293b; }
    .header { width: 100%; border-bottom: 3px solid #00594E; padding-bottom: 6px; margin-bottom: 8px; }
    .header-titulo { font-size: 12px; font-weight: bold; color: #00594E; text-transform: uppercase; }
    .header-sub { font-size: 8.5px; color: #475569; }
    h2.titulo { font-size: 12px; text-align: center; margin: 6px 0; color: #00594E; text-transform: uppercase; }
    .subinfo { font-size: 8.5px; text-align: center; margin-bottom: 8px; color: #334155; }
    .borrador { text-align: center; font-size: 10px; font-weight: bold; color: #b45309; border: 1px solid #f59e0b; background: #fffbeb; padding: 4px; margin-bottom: 8px; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
    th, td { border: 1px solid #94a3b8; padding: 3px 4px; vertical-align: top; }
    th { background: #EAF2EF; color: #00594E; font-weight: bold; text-align: center; font-size: 7.5px; text-transform: uppercase; }
    .dependencia { background: #00594E; color: #fff; font-weight: bold; font-size: 8.5px; }
    .vacante { color: #b45309; font-weight: bold; }
    .centro { text-align: center; }
    .resumen td { border: none; font-size: 9px; }
    .firma-box { width: 40%; border-top: 1px solid #334155; text-align: center; padding-top: 4px; font-size: 8.5px; }
</style>
</head>
<body>
<table class="header" style="border:none;">
    <tr>
        <td style="border:none; width:60px;"><img src="{{ $info['escudo'] }}" style="height: 42px;" alt="Escudo"></td>
        <td style="border:none;">
            <div class="header-titulo">SERAG — Sistema de Evaluación del Desempeño Laboral</div>
            <div class="header-sub">Universidad Internacional del Trópico Americano — Oficina de Talento Humano</div>
            <div class="header-sub">CATÁLOGO OFICIAL DE PLAZAS DE LA PLANTA ADMINISTRATIVA</div>
        </td>
        <td style="border:none; text-align:right;"><img src="{{ $info['logo'] }}" style="height: 34px;" alt="Logo"></td>
    </tr>
</table>

<h2 class="titulo">Catálogo oficial de plazas</h2>
<div class="subinfo">
    Fuente: {{ $info['fuente'] }} · Generado el {{ $generadoEn->format('d/m/Y H:i') }}
</div>

@unless($todoValidado)
    <div class="borrador">BORRADOR — {{ $info['total'] - $info['validadas'] }} de {{ $info['total'] }} plazas aún sin validar por Talento Humano</div>
@endunless

<table class="resumen">
    <tr>
        <td><b>Plazas:</b> {{ $info['total'] }}</td>
        <td><b>Ocupadas:</b> {{ $info['ocupadas'] }}</td>
        <td><b>Vacantes:</b> {{ $info['total'] - $info['ocupadas'] }}</td>
        <td><b>Validadas:</b> {{ $info['validadas'] }}</td>
        <td><b>Dependencias:</b> {{ $porDependencia->count() }}</td>
    </tr>
</table>

<table>
    <thead>
        <tr>
            <th style="width: 11%;">Código de plaza</th>
            <th style="width: 20%;">Cargo</th>
            <th style="width: 6%;">Código-grado</th>
            <th style="width: 8%;">Nivel</th>
            <th style="width: 4%;">Sistema</th>
            <th style="width: 20%;">Ocupante</th>
            <th style="width: 9%;">Vinculación</th>
            <th style="width: 7%;">Desde</th>
            <th style="width: 11%;">Plaza superior</th>
            <th style="width: 4%;">Estado</th>
        </tr>
    </thead>
    <tbody>
        @foreach($porDependencia as $dependencia => $plazas)
            <tr><td colspan="10" class="dependencia">{{ $dependencia }} ({{ $plazas->count() }})</td></tr>
            @foreach($plazas as $p)
                <tr>
                    <td>{{ $p->codigo_plaza }}</td>
                    <td>{{ $p->cargo }}</td>
                    <td class="centro">{{ $p->codigo_cargo }}-{{ $p->grado }}</td>
                    <td>{{ $p->nivel_jerarquico }}</td>
                    <td class="centro">{{ $sistema($p->sistema_evaluacion) }}</td>
                    <td class="{{ $p->ocupante === '' ? 'vacante' : '' }}">{{ $p->ocupante !== '' ? $p->ocupante : 'VACANTE' }}</td>
                    <td>{{ $p->tipo_ocupacion }}</td>
                    <td class="centro">{{ $p->vigente_desde ? \Carbon\Carbon::parse($p->vigente_desde)->format('d/m/Y') : '' }}</td>
                    <td>{{ $p->superior }}</td>
                    <td class="centro">{{ $p->estado === 'VALIDADA' ? 'Validada' : 'Borrador' }}</td>
                </tr>
            @endforeach
        @endforeach
    </tbody>
</table>

<table style="border:none; margin-top: 30px;">
    <tr>
        <td style="border:none;"><div class="firma-box">Jefe(a) Oficina de Talento Humano</div></td>
        <td style="border:none;"></td>
        <td style="border:none;"><div class="firma-box" style="margin-left:auto;">Revisó</div></td>
    </tr>
</table>
</body>
</html>
