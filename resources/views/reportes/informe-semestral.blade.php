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
            <td>{{ $comp->descripcion }}</td>
            <td class="centro">{{ is_null($comp->calificacion_definitiva) ? '' : $comp->calificacion_definitiva }}</td>
            <td style="font-size:7.5px;">
                @foreach($comp->metas as $meta)
                    <div>- {{ $meta }}</div>
                @endforeach
            </td>
            <td style="font-size:7.5px;">{{ $comp->observacion }}</td>
            <td style="font-size:7.5px; word-break:break-all;">
                @foreach($comp->links as $link)
                    <div>{{ $link }}</div>
                @endforeach
            </td>
        </tr>
    @empty
        <tr><td colspan="6" class="centro">Sin compromisos registrados.</td></tr>
    @endforelse
    <tr>
        <td class="negrita centro" colspan="2">Total</td>
        <td class="negrita centro">Promedio</td>
        <td class="resaltado centro" colspan="3">{{ $promCompromisos }}</td>
    </tr>
</table>

{{-- ============ RESULTADO DE LA EVALUACIÓN ============ --}}
<table>
    <tr><th colspan="2" class="cab">Resultado de la Evaluación</th></tr>
    <tr>
        <td class="info-cell" style="width:50%;"><b>Compromisos ({{ $calculo['pesos']['compromisos'] }}%)</b></td>
        <td class="info-cell resaltado centro">{{ $calculo['subtotal_compromisos'] }}</td>
    </tr>
    <tr>
        <td class="info-cell"><b>Competencias comunes ({{ $calculo['pesos']['comun'] }}%)</b></td>
        <td class="info-cell resaltado centro">{{ $calculo['subtotal_comun'] }}</td>
    </tr>
    <tr>
        <td class="info-cell"><b>Competencias nivel jerárquico ({{ $calculo['pesos']['nivel_jerarquico'] }}%)</b></td>
        <td class="info-cell resaltado centro">{{ $calculo['subtotal_nivel'] }}</td>
    </tr>
    @foreach ($calculo['subtotales_ejes'] ?? [] as $tipoEje => $subtotal)
        @php
            $nombreEje = [
                'DOCENCIA' => 'Docencia',
                'INVESTIGACION' => 'Investigación',
                'PROYECCION_SOCIAL' => 'Proyección social',
            ][$tipoEje] ?? $tipoEje;
            $pesoEje = $calculo['pesos']['ejes'][$tipoEje] ?? 0;
        @endphp
        <tr>
            <td class="info-cell"><b>{{ $nombreEje }} ({{ $pesoEje }}%)</b></td>
            <td class="info-cell resaltado centro">{{ $subtotal }}</td>
        </tr>
    @endforeach
    <tr>
        <td class="info-cell"><b>Categoría</b></td>
        <td class="info-cell resaltado centro">{{ $catLabel }}</td>
    </tr>
    <tr>
        <td class="info-cell" style="background:#EAF2EF;"><b>NOTA DEFINITIVA</b></td>
        <td class="info-cell resaltado centro" style="background:#EAF2EF; font-size:11px;">{{ $calculo['nota_definitiva'] }}</td>
    </tr>
</table>

<div style="margin:6px 0;"><b>Capacitaciones sugeridas:</b> {{ $info['capacitaciones'] }}</div>

{{-- ============ PLAN DE MEJORAMIENTO ============ --}}
<table>
    <tr>
        <th colspan="5" class="cab">Plan de Mejoramiento</th>
    </tr>
    <tr>
        <th style="width:10%;">Aplica</th>
        <th style="width:26%;">Aspectos susceptibles de mejorar o potenciar</th>
        <th style="width:26%;">Descripción del hecho a mejorar o potenciar</th>
        <th style="width:19%;">Evidencias — soportes del cumplimiento</th>
        <th style="width:19%;">Cumplimiento / Fecha de seguimiento o cierre</th>
    </tr>
    @if ($info['plan'])
        <tr>
            <td class="centro negrita">{{ $info['requiere_plan'] ? 'SI' : 'NO' }}</td>
            <td colspan="4" style="font-size:8px;">{{ $info['plan']->descripcion_temas }}</td>
        </tr>
        <tr>
            <td class="centro negrita">Estado</td>
            <td colspan="4" style="font-size:8px;">{{ $info['plan']->estado }}
                @if ($info['plan']->firmado_evaluado) · Firmado por el evaluado {{ \Carbon\Carbon::parse($info['plan']->fecha_firma_evaluado)->format('d/m/Y H:i') }}@endif
                @if ($info['plan']->firmado_evaluador) · Firmado por el evaluador {{ \Carbon\Carbon::parse($info['plan']->fecha_firma_evaluador)->format('d/m/Y H:i') }}@endif
            </td>
        </tr>
    @else
        <tr>
            <td class="centro negrita">{{ $info['requiere_plan'] ? 'SI' : 'NO' }}</td>
            <td colspan="4"></td>
        </tr>
    @endif
</table>

{{-- ============ RECURSOS ============ --}}
<table>
    <tr><th colspan="7" class="cab">Recursos</th></tr>
    <tr>
        <th style="width:10%;">Tipo de recurso</th>
        <th style="width:18%;">Cargo de quien recibe el recurso</th>
        <th style="width:7%;">No. folios</th>
        <th style="width:13%;">Fecha de interposición</th>
        <th style="width:10%;">Decisión</th>
        <th style="width:27%;">Motivación de la decisión</th>
        <th style="width:15%;">Evidencias (links)</th>
    </tr>
    @forelse($info['recursos'] as $rec)
        <tr>
            <td class="centro">{{ ucfirst(strtolower($rec->tipo_recurso)) }}</td>
            <td>{{ $rec->cargo_receptor }}</td>
            <td class="centro">{{ $rec->numero_folios }}</td>
            <td class="centro">{{ \Carbon\Carbon::parse($rec->fecha_recurso)->format('d/m/Y') }}</td>
            <td class="centro">{{ $rec->decision }}</td>
            <td style="font-size:7.5px;">{{ $rec->motivacion }}</td>
            <td style="font-size:7.5px;">
                @if(($rec->evidencias ?? null) && $rec->evidencias->isNotEmpty())
                    @foreach($rec->evidencias as $ev)
                        {{ $ev->descripcion ? $ev->descripcion . ': ' : '' }}{{ $ev->url }}<br>
                    @endforeach
                @else
                    —
                @endif
            </td>
        </tr>
    @empty
        <tr><td colspan="7" class="centro">Sin recursos radicados.</td></tr>
    @endforelse
</table>

{{-- ============ FIRMAS ============ --}}
<table class="tabla-firmas" style="margin-top:16px;">
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

{{-- ============ RENUENCIA ============ --}}
@if ($info['renuencias']->isNotEmpty())
<table style="margin-top:14px;">
    <tr><th colspan="3" class="cab">Renuencia del evaluado a la notificación de la calificación</th></tr>
    <tr>
        <th style="width:15%;">Fecha</th>
        <th style="width:60%;">Datos del testigo</th>
        <th style="width:25%;">Firma del testigo</th>
    </tr>
    @foreach($info['renuencias'] as $ren)
        <tr>
            <td class="centro">{{ \Carbon\Carbon::parse($ren->fecha_firma)->format('d/m/Y') }}</td>
            <td>
                @foreach($ren->testigos as $t)
                    <div><b>{{ $t->nombre_testigo }}</b> — {{ $t->cargo_testigo }}</div>
                @endforeach
            </td>
            <td></td>
        </tr>
    @endforeach
</table>
@endif

</body>
</html>
