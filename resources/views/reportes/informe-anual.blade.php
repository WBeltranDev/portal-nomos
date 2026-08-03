@php
    $sistema = $info['sistema'] === 'RENDIMIENTO_LABORAL' ? 'Rendimiento Laboral' : 'Acuerdos de Gestión';
    $catLabel = [
        'SOBRESALIENTE' => 'Sobresaliente (91-100)',
        'BUENO' => 'Bueno (81-90)',
        'APROBADO_MEJORA' => 'Susceptible de mejora (Aprobado) (71-80)',
        'NO_SATISFACTORIO' => 'No satisfactorio (0-70)',
    ][$info['categoria']] ?? $info['categoria'];
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
    * { font-family: "DejaVu Sans", sans-serif; }
    body { font-size: 9px; color: #1e293b; }
    .header { width: 100%; border-bottom: 3px solid #00594E; padding-bottom: 6px; margin-bottom: 10px; }
    .header img { vertical-align: middle; }
    .header-titulo { font-size: 13px; font-weight: bold; color: #00594E; text-transform: uppercase; }
    .header-sub { font-size: 9px; color: #475569; }
    h2.titulo { font-size: 12px; text-align: center; margin: 10px 0 8px; color: #00594E; text-transform: uppercase; }
    .subinfo { font-size: 9px; text-align: center; margin-bottom: 10px; color: #334155; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
    th, td { border: 1px solid #64748b; padding: 4px 5px; vertical-align: top; }
    th { background: #EAF2EF; color: #00594E; font-weight: bold; text-align: center; font-size: 8.5px; text-transform: uppercase; }
    .cab { background: #00594E; color: #fff; }
    .acento { background: #B5A160; color: #fff; }
    .centro { text-align: center; }
    .negrita { font-weight: bold; }
    .resaltado { background: #FEF9C3; font-weight: bold; }
    .firma-box { width: 45%; border-top: 1px solid #334155; text-align: center; padding-top: 4px; font-size: 9px; }
    .tabla-firmas td { border: none; }
    .info-cell { font-size: 9.5px; }
    .nota { font-size: 8px; color: #64748b; font-style: italic; }
</style>
</head>
<body>

{{-- ============ CABECERA INSTITUCIONAL ============ --}}
<table class="header" style="border:none; margin-bottom:10px;">
    <tr style="border:none;">
        <td style="border:none; width:16%; text-align:left;">
            <img src="{{ $info['escudo'] }}" style="height: 46px;" alt="Escudo">
        </td>
        <td style="border:none; text-align:center;">
            <div class="header-titulo">SERAG — Sistema de Evaluación del Desempeño Laboral</div>
            <div class="header-sub">Universidad Internacional del Trópico Americano — Oficina de Talento Humano</div>
            <div class="header-sub">INFORME ANUAL DE EVALUACIÓN DEL DESEMPEÑO LABORAL</div>
        </td>
        <td style="border:none; width:16%; text-align:right;">
            <img src="{{ $info['logo'] }}" style="height: 38px;" alt="Logo">
        </td>
    </tr>
</table>

<h2 class="titulo">Evaluación anual {{ $sistema }}</h2>
<div class="subinfo">Periodo: {{ \Carbon\Carbon::parse($info['periodo']->fecha_inicio)->format('d/m/Y') }} al {{ \Carbon\Carbon::parse($info['periodo']->fecha_fin)->format('d/m/Y') }}</div>
<div class="subinfo" style="color:#64748b; font-style:italic;">Documento generado el {{ $generadoEn->format('d/m/Y') }} a las {{ $generadoEn->format('H:i') }}</div>

{{-- ============ INFORMACIÓN EVALUADOR ============ --}}
<table>
    <tr><th colspan="2" class="cab">Información Evaluador</th></tr>
    <tr>
        <td class="info-cell" style="width:22%;"><b>Nombre de evaluador</b></td>
        <td class="info-cell">{{ $info['evaluador']->nombres }} {{ $info['evaluador']->apellidos }}</td>
    </tr>
    <tr>
        <td class="info-cell"><b>Cargo de evaluador</b></td>
        <td class="info-cell">{{ $info['evaluador']->cargo }}</td>
    </tr>
    <tr>
        <td class="info-cell"><b>Área</b></td>
        <td class="info-cell">{{ $info['evaluador']->area }}</td>
    </tr>
    <tr>
        <td class="info-cell"><b>Nivel</b></td>
        <td class="info-cell">{{ $info['evaluador']->nivel_jerarquico }}</td>
    </tr>
    <tr>
        <td class="info-cell"><b>Código</b></td>
        <td class="info-cell">{{ $info['evaluador']->codigo_cargo }}</td>
    </tr>
    <tr>
        <td class="info-cell"><b>Grado</b></td>
        <td class="info-cell">{{ $info['evaluador']->grado_cargo }}</td>
    </tr>
</table>

{{-- ============ RESULTADO DE LA EVALUACIÓN ============ --}}
<table>
    <tr><th colspan="2" class="cab">Resultado de la Evaluación</th></tr>
    <tr>
        <td class="info-cell" style="width:50%;"><b>Semestre A</b></td>
        <td class="info-cell resaltado centro">{{ $info['nota_semestre_a'] }}</td>
    </tr>
    @if (!$info['tiene_semestre_a'])
        <tr><td colspan="2" class="nota">Si no tiene semestral A, no promediar; no le aplica la generación de este informe.</td></tr>
    @endif
    <tr>
        <td class="info-cell"><b>Semestre B</b></td>
        <td class="info-cell resaltado centro">{{ $info['nota_semestre_b'] }}</td>
    </tr>
    <tr>
        <td class="info-cell"><b>Categoría</b></td>
        <td class="info-cell resaltado centro">{{ $catLabel }}</td>
    </tr>
    <tr>
        <td class="info-cell" style="background:#EAF2EF;"><b>NOTA ANUAL</b></td>
        <td class="info-cell resaltado centro" style="background:#EAF2EF; font-size:12px;">{{ $info['nota_anual'] }}</td>
    </tr>
</table>

<div style="margin:8px 0;"><b>Capacitaciones sugeridas:</b> {{ $info['capacitaciones'] }}</div>

{{-- ============ FIRMAS ============ --}}
<table class="tabla-firmas" style="margin-top:18px;">
    <tr>
        <td style="width:50%; text-align:left;">
            <div class="firma-box">FIRMA EVALUADO</div>
            <div style="font-size:9px; text-align:center; margin-top:2px;">{{ $info['evaluado']->nombres }} {{ $info['evaluado']->apellidos }}</div>
        </td>
        <td style="width:50%; text-align:right;">
            <div class="firma-box">FIRMA EVALUADOR</div>
            <div style="font-size:9px; text-align:center; margin-top:2px;">{{ $info['evaluador']->nombres }} {{ $info['evaluador']->apellidos }}</div>
        </td>
    </tr>
</table>

</body>
</html>
