@php
    $sistema = $info['sistema'] === 'RENDIMIENTO_LABORAL' ? 'Rendimiento Laboral' : 'Acuerdos de Gestión';
    $comunes = $info['competencias_comunes'];
    $nivel   = $info['competencias_nivel'];
    $maxRows = max(count($comunes), count($nivel));
    $promComunes = $comunes->count() ? round($comunes->avg('calificacion_definitiva'), 1) : '-';
    $promNivel   = $nivel->count() ? round($nivel->avg('calificacion_definitiva'), 1) : '-';
    $promCompromisos = $info['compromisos']->whereNotNull('calificacion_definitiva')->count()
        ? round($info['compromisos']->avg('calificacion_definitiva'), 1)
        : '-';
    $calculo = $info['calculo'];
    $pesos = $calculo['pesos'] ?? [];
    $pesoCompromisos = (float) ($pesos['compromisos'] ?? 0);
    $pesoComun = (float) ($pesos['comun'] ?? 0);
    $pesoNivel = (float) ($pesos['nivel_jerarquico'] ?? 0);
    $pesoCompetencias = $pesoComun + $pesoNivel;
    $notaComun = (float) ($calculo['nota_comp_comun_raw'] ?? 0);
    $notaNivel = (float) ($calculo['nota_comp_nivel_raw'] ?? 0);
    $notaCompetencias = $pesoCompetencias > 0
        ? round((($notaComun * $pesoComun) + ($notaNivel * $pesoNivel)) / $pesoCompetencias, 2)
        : 0;
    $ponderadoCompetencias = round((float) ($calculo['subtotal_comun'] ?? 0) + (float) ($calculo['subtotal_nivel'] ?? 0), 2);
    $pesosEjes = $pesos['ejes'] ?? [];
    $notasEjes = $calculo['notas_ejes_raw'] ?? [];
    $ponderadosEjes = $calculo['subtotales_ejes'] ?? [];
    $etiquetasEjes = ['DOCENCIA' => 'Docencia', 'INVESTIGACION' => 'Investigación', 'PROYECCION_SOCIAL' => 'Proyección Social'];
    $catLabel = [
        'SOBRESALIENTE' => 'Sobresaliente (91-100)',
        'BUENO' => 'Bueno (81-90)',
        'APROBADO_MEJORA' => 'Susceptible de mejora (Aprobado) (71-80)',
        'NO_SATISFACTORIO' => 'No satisfactorio (0-70)',
    ][$calculo['categoria']] ?? $calculo['categoria'];
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
    * { font-family: "DejaVu Sans", sans-serif; }
    body { font-size: 8.5px; color: #1e293b; }
    .header { width: 100%; border-bottom: 3px solid #00594E; padding-bottom: 6px; margin-bottom: 8px; }
    .header img { vertical-align: middle; }
    .header-titulo { font-size: 13px; font-weight: bold; color: #00594E; text-transform: uppercase; }
    .header-sub { font-size: 9px; color: #475569; }
    h2.titulo { font-size: 12px; text-align: center; margin: 10px 0 8px; color: #00594E; text-transform: uppercase; }
    .subinfo { font-size: 9px; text-align: center; margin-bottom: 8px; color: #334155; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
    th, td { border: 1px solid #64748b; padding: 3px 4px; vertical-align: top; }
    th { background: #EAF2EF; color: #00594E; font-weight: bold; text-align: center; font-size: 8px; text-transform: uppercase; }
    .cab { background: #00594E; color: #fff; }
    .acento { background: #B5A160; color: #fff; }
    .centro { text-align: center; }
    .negrita { font-weight: bold; }
    .resaltado { background: #FEF9C3; font-weight: bold; }
    .firma-box { width: 45%; border-top: 1px solid #334155; text-align: center; padding-top: 4px; font-size: 9px; }
    .tabla-firmas td { border: none; }
    .info-cell { font-size: 9px; }
</style>
</head>
<body>

{{-- ============ CABECERA INSTITUCIONAL ============ --}}
<table class="header" style="border:none; margin-bottom:8px;">
    <tr style="border:none;">
        <td style="border:none; width:16%; text-align:left;">
            <img src="{{ $info['escudo'] }}" style="height: 46px;" alt="Escudo">
        </td>
        <td style="border:none; text-align:center;">
            <div class="header-titulo">SERAG — Sistema de Evaluación del Desempeño Laboral</div>
            <div class="header-sub">Universidad Internacional del Trópico Americano — Oficina de Talento Humano</div>
            <div class="header-sub">INFORME DE EVALUACIÓN DEL DESEMPEÑO LABORAL</div>
        </td>
        <td style="border:none; width:16%; text-align:right;">
            <img src="{{ $info['logo'] }}" style="height: 38px;" alt="Logo">
        </td>
    </tr>
</table>

<h2 class="titulo">Evaluación {{ $sistema }} — {{ $info['tipo_nombre'] }}</h2>
<div class="subinfo">Periodo: {{ \Carbon\Carbon::parse($info['periodo']->fecha_inicio)->format('d/m/Y') }} al {{ \Carbon\Carbon::parse($info['periodo']->fecha_fin)->format('d/m/Y') }}</div>
<div class="subinfo" style="color:#64748b; font-style:italic;">Documento generado el {{ $generadoEn->format('d/m/Y') }} a las {{ $generadoEn->format('H:i') }}</div>

{{-- ============ INFORMACIÓN GENERAL (EVALUADO Y EVALUADOR) ============ --}}
<table style="margin-bottom: 8px;">
    <tr>
        <th colspan="2" class="cab" style="width:50%;">Información del Evaluado</th>
        <th colspan="2" class="cab" style="width:50%;">Información del Evaluador</th>
    </tr>
    <tr>
        <td class="info-cell" style="width:16%;"><b>Funcionario</b></td>
        <td class="info-cell" style="width:34%;">{{ $info['evaluado']->nombres }} {{ $info['evaluado']->apellidos }}</td>
        <td class="info-cell" style="width:16%;"><b>Evaluador</b></td>
        <td class="info-cell" style="width:34%;">{{ $info['evaluador']->nombres }} {{ $info['evaluador']->apellidos }}</td>
    </tr>
    <tr>
        <td class="info-cell"><b>Documento</b></td>
        <td class="info-cell">{{ $info['evaluado']->numero_doc ?? '-' }}</td>
        <td class="info-cell"><b>Cargo</b></td>
        <td class="info-cell">{{ $info['evaluador']->cargo }}</td>
    </tr>
    <tr>
        <td class="info-cell"><b>Cargo</b></td>
        <td class="info-cell">{{ $info['evaluado']->cargo }}</td>
        <td class="info-cell"><b>Área / Dependencia</b></td>
        <td class="info-cell">{{ $info['evaluador']->area }}</td>
    </tr>
    <tr>
        <td class="info-cell"><b>Área / Dependencia</b></td>
        <td class="info-cell">{{ $info['evaluado']->area }}</td>
        <td class="info-cell"><b>Nivel Jerárquico</b></td>
        <td class="info-cell">{{ $info['evaluador']->nivel_jerarquico }}</td>
    </tr>
    <tr>
        <td class="info-cell"><b>Nivel Jerárquico</b></td>
        <td class="info-cell">{{ $info['evaluado']->nivel_jerarquico }}</td>
        <td class="info-cell"><b>Código / Grado</b></td>
        <td class="info-cell">{{ $info['evaluador']->codigo_cargo }} - Grado {{ $info['evaluador']->grado_cargo }}</td>
    </tr>
</table>

{{-- ============ COMPETENCIAS ============ --}}
<table>
    <tr><th colspan="7" class="cab">Competencias</th></tr>
    <tr>
        <th colspan="3">Competencias Funcionales — Comportamentales Comunes</th>
        <th colspan="4">Competencias Funcionales — Por Nivel Jerárquico</th>
    </tr>
    <tr>
        <th style="width:4%;">N°</th>
        <th style="width:21%;">Competencias</th>
        <th style="width:17%;">Afirmación</th>
        <th style="width:6%;">Nota</th>
        <th style="width:26%;">Competencias</th>
        <th style="width:18%;">Afirmación</th>
        <th style="width:8%;">Nota</th>
    </tr>
    @for ($i = 0; $i < $maxRows; $i++)
        @php $c = $comunes[$i] ?? null; $n = $nivel[$i] ?? null; @endphp
        <tr>
            <td class="centro">{{ $c ? $i + 1 : '' }}</td>
            <td>{{ $c->nombre_competencia ?? '' }}</td>
            <td style="font-size:7.5px;">{{ $c->afirmacion ?? '' }}</td>
            <td class="centro">{{ $c ? (is_null($c->calificacion_definitiva) ? '' : $c->calificacion_definitiva) : '' }}</td>
            <td>{{ $n->nombre_competencia ?? '' }}</td>
            <td style="font-size:7.5px;">{{ $n->afirmacion ?? '' }}</td>
            <td class="centro">{{ $n ? (is_null($n->calificacion_definitiva) ? '' : $n->calificacion_definitiva) : '' }}</td>
        </tr>
    @endfor
    <tr>
        <td colspan="7" style="background:#F1F5F9;">
            <b>Participación en el fortalecimiento institucional:</b>
            Participa de manera activa y oportuna en las actividades de capacitación, formación y demás acciones promovidas por la Oficina de Talento Humano y la División de Seguridad y Salud en el Trabajo (SG-SST).
        </td>
    </tr>
    <tr>
        <td colspan="3" class="negrita centro">Total</td>
        <td class="negrita centro" colspan="4">Nota promediada: <span class="resaltado">{{ $promComunes }}</span></td>
    </tr>
</table>

{{-- ============ COMPROMISOS ============ --}}
<table>
    <tr><th colspan="6" class="cab">Compromisos</th></tr>
    <tr>
        <th style="width:4%;">N°</th>
        <th style="width:26%;">Descripción</th>
        <th style="width:6%;">Nota</th>
        <th style="width:24%;">Metas de contribución</th>
        <th style="width:20%;">Observaciones</th>
        <th style="width:20%;">Link evidencias</th>
    </tr>
    @forelse($info['compromisos'] as $comp)
        <tr>
            <td class="centro">{{ $comp->numero_orden }}</td>
            <td>{{ $comp->descripcion }} (<b>{{ $comp->porcentaje_peso }}%</b>)</td>
            <td class="centro resaltado">{{ is_null($comp->calificacion_definitiva) ? '-' : $comp->calificacion_definitiva }}</td>
            <td style="font-size:7.5px;">
                @if(count($comp->metas))
                    <ul style="margin:0; padding-left:10px;">
                        @foreach($comp->metas as $m)
                            <li>{{ $m }}</li>
                        @endforeach
                    </ul>
                @else
                    -
                @endif
            </td>
            <td style="font-size:7.5px;">{{ $comp->observacion ?: '-' }}</td>
            <td style="font-size:7px; word-break:break-all;">
                @if(count($comp->links))
                    @foreach($comp->links as $link)
                        <div><a href="{{ $link }}" target="_blank">{{ $link }}</a></div>
                    @endforeach
                @else
                    -
                @endif
            </td>
        </tr>
    @empty
        <tr><td colspan="6" class="centro">Sin compromisos registrados.</td></tr>
    @endforelse
    <tr>
        <td colspan="2" class="negrita centro">Promedio compromisos</td>
        <td class="negrita centro"><span class="resaltado">{{ $promCompromisos }}</span></td>
        <td colspan="3"></td>
    </tr>
</table>

{{-- ============ RESULTADOS Y PONDERACIONES ============ --}}
<table>
    <tr><th colspan="4" class="cab">Resultados del Periodo</th></tr>
    <tr>
        <th style="width:40%;">Componente</th>
        <th style="width:20%;">Peso</th>
        <th style="width:20%;">Nota</th>
        <th style="width:20%;">Ponderado</th>
    </tr>
    <tr>
        <td>Compromisos Laborales</td>
        <td class="centro">{{ $pesoCompromisos }}%</td>
        <td class="centro">{{ $calculo['nota_compromisos_raw'] ?? 0 }}</td>
        <td class="centro negrita">{{ $calculo['subtotal_compromisos'] ?? 0 }}</td>
    </tr>
    <tr>
        <td>Competencias Comportamentales</td>
        <td class="centro">{{ $pesoCompetencias }}%</td>
        <td class="centro">{{ $notaCompetencias }}</td>
        <td class="centro negrita">{{ $ponderadoCompetencias }}</td>
    </tr>
    @if ($sistema === 'Acuerdos de Gestión')
        @foreach ($pesosEjes as $eje => $peso)
            @if ($peso > 0)
            <tr>
                <td>{{ $etiquetasEjes[$eje] ?? $eje }}</td>
                <td class="centro">{{ $peso }}%</td>
                <td class="centro">{{ $notasEjes[$eje] ?? 0 }}</td>
                <td class="centro negrita">{{ $ponderadosEjes[$eje] ?? 0 }}</td>
            </tr>
            @endif
        @endforeach
    @endif
    <tr style="background:#EAF2EF; font-size:10px;">
        <td class="negrita" colspan="3">CALIFICACIÓN DEFINITIVA SEMESTRAL</td>
        <td class="centro negrita resaltado" style="font-size:11px;">{{ $calculo['nota_definitiva'] ?? 0 }}</td>
    </tr>
    <tr>
        <td class="negrita" colspan="3">CATEGORÍA FINAL</td>
        <td class="centro negrita">{{ $catLabel }}</td>
    </tr>
</table>

{{-- ============ PLAN DE MEJORAMIENTO ============ --}}
@if ($info['requiere_plan'] && $info['plan'])
<table>
    <tr><th colspan="2" class="cab">Plan de Mejoramiento Concertado</th></tr>
    <tr>
        <td class="info-cell" style="width:25%;"><b>Estado</b></td>
        <td class="info-cell">{{ $info['plan']->estado }}</td>
    </tr>
    <tr>
        <td class="info-cell"><b>Temas y Compromisos</b></td>
        <td class="info-cell">{{ $info['plan']->descripcion_temas }}</td>
    </tr>
</table>
@endif

{{-- ============ FIRMAS ============ --}}
<table class="tabla-firmas" style="margin-top:20px;">
    <tr>
        <td style="width:50%; text-align:left;">
            <div class="firma-box">FIRMA EVALUADO</div>
            <div style="font-size:8.5px; text-align:center; margin-top:2px;">{{ $info['evaluado']->nombres }} {{ $info['evaluado']->apellidos }}</div>
        </td>
        <td style="width:50%; text-align:right;">
            <div class="firma-box">FIRMA EVALUADOR</div>
            <div style="font-size:8.5px; text-align:center; margin-top:2px;">{{ $info['evaluador']->nombres }} {{ $info['evaluador']->apellidos }}</div>
        </td>
    </tr>
</table>

</body>
</html>
