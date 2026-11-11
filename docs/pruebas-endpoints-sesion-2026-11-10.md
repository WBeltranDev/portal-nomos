# SERAG — Cambios de la sesión de correcciones (plan de pruebas 29-10-2026) y guía de pruebas de endpoints

Base: documento *"29-10-2026 Plan Pruebas definitivas SERAG - Amarillo.docx"* (correcciones de Talento Humano).
Rama: `develop`. Suite automatizada al cierre (11-11-2026): **248 pruebas, 1247 aserciones, todas en verde**.
Incluye también las correcciones del informe de Antigravity (`docs/informe-inconsistencias-pruebas-2026-11-10.md`) y los traslados en bloque.

> Este archivo es la guía para probar a mano o con herramienta los endpoints que cambiaron. Las pruebas automáticas
> ya cubren la lógica; aquí se describe qué esperar al llamar a cada endpoint contra un servidor real.

---

## 0. Preparación del entorno

```bash
docker compose up -d                       # app en http://localhost:8082, MySQL talentoh-mysql
docker compose exec -T app php artisan migrate --force
npm run build                              # los JS de resources/js/dashboards se compilan con Vite
docker compose restart app                 # OPcache de producción: sin reinicio se ve código viejo
```

Pruebas automáticas (SIEMPRE con la base de pruebas; sin `-e` corren contra la base real):

```bash
docker compose exec -T -e DB_DATABASE=sistemas_evaluacion_test app php artisan test
```

### Migraciones nuevas (aplicar en el orden de su nombre)
| Migración | Qué agrega |
|---|---|
| `2026_10_29_000001_recurso_oficio_y_soportes_decision` | `recurso.url_oficio`, `recurso_evidencia.origen` (`RADICACION`/`DECISION`) |
| `2026_10_29_000002_delegacion_cambia_evaluador` | columna `delegacion.cambia_evaluador` (la reemplaza la `2026_11_10_000001`) |
| `2026_10_29_000003_plazos_periodo_y_extratiempo_calificacion` | `periodo.fecha_fin_concertacion`, `periodo.fecha_fin_calificacion`, `concertacion_extratiempo.tipo` |
| `2026_11_10_000001_delegacion_delegado_cambia_evaluador` | quita `cambia_evaluador`; agrega `delegacion.delegado_cambia_evaluador` (default 0) |
| `2026_11_10_000002_delegacion_sin_titular` | `delegacion.sin_titular` (default 0) |
| `2026_11_11_000001_traslado_lote` | tabla `traslado_lote` y `traslado.id_lote` (traslados en bloque) |

Configuración nueva: `config/serag.php` → `dias_habiles_recursos` (env `SERAG_DIAS_HABILES_RECURSOS`, por defecto 5).

### Autenticación para probar endpoints
- Todas las rutas usan **sesión de Laravel + CSRF** (cookie de sesión y encabezado `X-CSRF-TOKEN` / campo `_token`).
- `POST /login` con `usuario` y `password` (ya no `correo`):
  - Funcionarios: `usuario` = **cédula** (se aceptan puntos y espacios). La contraseña inicial de un usuario nuevo es su cédula.
  - Administradores de TH (sin cédula): `usuario` = su usuario, p. ej. `administradorth1@unitropico.edu.co`.
  - El correo de un funcionario **ya no sirve** para entrar.
- Si la cuenta tiene más de un rol: `POST /seleccionar-rol` con `rol` (`admin`, `evaluador`, `evaluado`).
- Roles: `admin` (Talento Humano), `evaluador`, `evaluado`. Los endpoints `/admin/*` exigen `admin`.
- Las respuestas JSON de error de validación son 422 con `errors`; los `abort_if(..., 422, msg)` devuelven `{"message": msg}`.

---

## 1. Resumen funcional por bloque

### Bloque 1 — Errores directos
- **VIII** El aviso "planes de mejoramiento pendientes" ya no lista evaluaciones Buenas/Sobresalientes (se quitó el filtro viejo `<= 80` de `DashboardController`).
- **V** Ortografía: "Este perido ya existe" → "Este periodo ya existe". Los demás textos de las capturas venían de un build anterior.
- **VII** Matriz de calificaciones: ahora muestra las calificaciones semestrales ya realizadas aunque la evaluación tenga el otro número de semestre (`SEMESTRE_1` dentro de un periodo de semestre 2), esté en otra vinculación de la misma persona, o el semestre tenga tramos sin calificar (nota **provisional** "Parcial n/m"). Ver 2.10.

### Bloque 2 — Recursos
- La decisión de un recurso (reposición/apelación) exige **enlace del oficio** (obligatorio al aprobar y al negar) y admite **soportes** adicionales (acta del CEDP, otros). Modal reemplaza al `prompt()`.
- Al calcularse la nota, el evaluado recibe la notificación `NOTA_CALIFICADA` ("Revisa y descarga el documento de calificación…") y el formulario de recurso muestra el mismo aviso.

### Bloque 3 — Impedimentos y traslados
- El rol **evaluador** se otorga también a quien es evaluador de alguna evaluación (superior que recibe un impedimento).
- El **Rector** (sin evaluador ni evaluaciones) entra solo como evaluador, sin vista de evaluado.
- Quien cedió una evaluación por impedimento aprobado no puede abrirla de nuevo (`evaluadosCedidosPorImpedimento`).
- **Traslado de evaluador (CAMBIO):** el tramo nuevo nace con los compromisos del tramo anterior (copiados, se firman de nuevo; sin firmas ni evidencias).
- **Plazo de recursos:** un traslado de evaluador no se confirma si el tramo de origen tiene un recurso pendiente o está dentro de 5 días hábiles desde la firma de la notificación (o desde la calificación).

### Bloque 4 — Delegaciones
- **IX** Si el semestre se parte por traslado/delegación larga, cada evaluación muestra **"Parcial N de M"** y las **fechas/días del tramo**.
- **Delegación por cargo (plaza):** el formulario elige el cargo (con su ocupante); la delegación guarda `id_puesto`.
- **¿El delegado cambia de evaluador?** Regla general **No** (no se programa cambio ni tramo para él). Con **Sí** (excepcional) se exige superior y se aplica el tramo como antes. En toda delegación el delegado asume las personas a cargo del titular.
- **Cargo vacante** (con último ocupante y personas a cargo): se delega **sin tramos** (`sin_titular=1`, activa de inmediato). Si el delegado cambia de evaluador, el cambio es directo.
- **Ajuste manual** del evaluador dentro de una delegación vigente (botón *Cambiar quién califica*).
- **Buscador** en los selectores de titular, delegado, dependencia y superior.
- Regla general vigente: en delegaciones de **más de 30 días** (cargo con titular) el titular deja calificado el tramo antes de ausentarse.

### Bloque 6 — Informe de pruebas 10-11-2026, traslados en bloque y reactivación
- **Informe de Antigravity (10 inconsistencias):** se quita el campo ficticio "Inicio Concertación"; la descripción se guarda al crear un periodo; la tabla de periodos muestra los dos cierres; meta `csrf-token` en el layout; la pestaña activa y la evaluación abierta sobreviven a la recarga; `confirm()`/`alert()` nativos reemplazados por modal y toasts propios; el error de calificación incompleta nombra lo que falta y la pantalla lo resalta en rojo; traslados en plazo de recursos con botón deshabilitado e insignia; reasignación por impedimento sin evaluadores incompatibles.
- **Traslados en bloque (rotación):** formato Excel descargable, revisión fila por fila (con la validación completa del traslado real), lote que se programa y se confirma todo junto. Ver 2.8.
- **Reactivar funcionario:** caso Natalia Pulido. Ver 2.9.

### Bloque 5 — Acceso y plazos
- **XI** Ingreso por **cédula**; el correo desaparece de pantallas y del formulario de creación de funcionarios (se conserva en la base).
- **X** Plazos reales por periodo (cierre de concertación y de calificación) y **extratiempo de calificación**.

---

## 2. Endpoints a probar

Convención: ✅ = respuesta esperada. Todos los `/admin/*` requieren rol `admin` (si no, 403).

### 2.1 Acceso
| Método y ruta | Cuerpo | Esperado |
|---|---|---|
| `POST /login` | `usuario=<cédula>`, `password` | ✅ 302 a `/dashboard` (un rol) o `/seleccionar-rol`. Sesión con `usuario_autenticado.identificacion` = cédula |
| `POST /login` | `usuario=<cédula con puntos/espacios>` | ✅ igual que arriba |
| `POST /login` | `usuario=<usuario admin>` | ✅ rol `admin` |
| `POST /login` | `usuario=<correo de un funcionario>` | ❌ 302 de vuelta con error `login`: "Cédula, usuario o contraseña incorrectos." |
| `POST /login` | clave errónea / cédula inexistente / cuenta inactiva | ❌ mismo error |
| `GET /` | — | La pantalla dice "Cédula o usuario" (no "Correo institucional") |

### 2.2 Recursos
| Método y ruta | Rol | Cuerpo | Esperado |
|---|---|---|---|
| `POST /evaluaciones/{id}/recursos` | evaluado | `tipo_recurso`, `numero_folios`, `motivacion`, `evidencias[]{url,descripcion}` | ✅ 200 (sin cambios de contrato; evaluación `CALIFICADA` y notificación firmada) |
| `POST /recursos/{id}/decision` | evaluador receptor o admin | `decision` (`APROBADO`/`NEGADO`), `motivacion`, **`url_oficio`** (obligatorio, URL), `soportes[]{url,descripcion}` (opcional, máx. 10) | ✅ 200. ❌ 422 con `errors.url_oficio` si falta o no es URL; 422 si algún soporte no es URL |
| `GET /evaluaciones/{id}/recursos`, `GET /recursos`, `GET /recursos/mios` | evaluado/admin/evaluador | — | ✅ cada recurso trae `url_oficio` y `evidencias[]` con `origen` (`RADICACION` o `DECISION`) |
| `POST /evaluaciones/{id}/calcular-final` | evaluador/admin | — | ✅ además crea la notificación `NOTA_CALIFICADA` para el evaluado |

Verificar en BD: `recurso.url_oficio`; `recurso_evidencia` con `origen='DECISION'` para los soportes; bitácora `RECURSO_DECIDIDO` con `soportes`.

### 2.3 Traslados
| Método y ruta | Rol | Esperado |
|---|---|---|
| `POST /admin/traslados/{id}/confirmar` | admin | ❌ 422 "El tramo de origen tiene un recurso pendiente de decisión. Debe resolverse antes de confirmar el traslado." si hay recurso `PENDIENTE` en la evaluación de origen |
| idem | admin | ❌ 422 "El tramo de origen aún está dentro del plazo para recursos del evaluado (hasta el dd/mm/aaaa). Confirma el traslado cuando venza o se resuelvan." mientras no pasen 5 días hábiles |
| idem | admin | ✅ 200 vencido el plazo y sin recursos pendientes; la evaluación destino nace con los compromisos copiados (`es_copia=1`, `fase_actual=1`, sin firmas ni evidencias) y evento `COMPROMISOS_COPIADOS` |

**`GET /admin/traslados`** (admin) agrega por traslado: `id_lote` (null si es individual), `bloqueo_recursos` (texto del motivo o null) y `plazo_recursos_hasta` (`dd/mm/aaaa` o null). La tarjeta del traslado programado muestra la insignia ámbar "En plazo de recursos hasta dd/mm/aaaa" y el botón "Confirmar traslado" queda `disabled` mientras `bloqueo_recursos` no sea null.

Solo aplica a traslados de evaluador (`tipo=CAMBIO`, sin `id_delegacion`). Fecha límite = `fechaLimiteRecursos()`: 5 días hábiles (lunes–viernes, sin festivos) desde `firma.NOTIFICACION_EVALUADO` o, si no hay firma, desde el evento `EVALUACION_CALIFICADA`.

### 2.4 Impedimentos / roles
| Método y ruta | Esperado |
|---|---|
| `POST /admin/impedimentos/{id}/resolver` (`estado=APROBADO`, `respuesta`, `id_vinc_nuevo_evaluador`) | ✅ la evaluación pasa al nuevo evaluador; si este no tenía marca de evaluador, en su siguiente inicio de sesión (o al recalcular roles) recibe el rol `evaluador` |
| `POST /evaluador/asignaciones` (abrir evaluación) por el evaluador que cedió | ❌ redirección con error `asignaciones`: "La evaluación de este funcionario fue reasignada a otro evaluador por un impedimento o recusación aprobados; ya no la puedes abrir." |
| `POST /admin/impedimentos/{id}/resolver` con `id_vinc_nuevo_evaluador` = el evaluador que tiene hoy la evaluación | ❌ 422 `{"message":"El nuevo evaluador no puede ser quien tiene hoy la evaluación (el evaluador impedido o recusado)."}` |
| idem con `id_vinc_nuevo_evaluador` = el evaluado | ❌ 422 "El evaluado no puede ser su propio evaluador." |
| idem con una persona a cargo del evaluado | ❌ 422 "El nuevo evaluador es una persona a cargo del evaluado: se evaluarían entre sí." (la solicitud sigue `PENDIENTE`) |
| `GET /dashboard` (admin) | cada elemento de `impedimentos` trae `evaluadores_excluidos` (ids de vinculación: evaluador actual, solicitante, evaluado y sus personas a cargo); el selector de reasignación no los ofrece |
| Inicio de sesión del Rector | roles = `["evaluador"]` únicamente |

### 2.5 Periodos y extratiempo
| Método y ruta | Cuerpo | Esperado |
|---|---|---|
| `POST /admin/periodos` | `sistema, anio, semestre, fecha_inicio, fecha_fin`, `fecha_fin_concertacion?`, `fecha_fin_calificacion?`, `descripcion?` (≤200; ahora se guarda al crear). Ya no existe `fecha_inicio_concertacion` | ✅ guarda las dos fechas límite. ❌ error `periodo` si concertación > fin del periodo ("El cierre de concertación no puede ser posterior al fin del periodo.") o calificación < concertación ("El cierre de calificación no puede ser anterior al cierre de concertación.") |
| `POST /admin/periodos/{id}` | `fecha_inicio, fecha_fin, estado, descripcion?, fecha_fin_concertacion?, fecha_fin_calificacion?` | ✅ igual validación |
| `POST /evaluaciones/{id}/calificar-compromisos`, `.../calificar-competencias`, `.../calificar-ejes`, `.../calcular-final` | evaluador | ❌ 422 `{"message":"La fecha límite de calificación de este periodo finalizó (dd/mm/aaaa). Solicita a Talento Humano la habilitación de calificación extratiempo."}` si `fecha_fin_calificacion` ya pasó y no hay extratiempo `CALIFICACION` vigente para esa evaluación. El admin no se bloquea. Sin fecha en el periodo → no hay bloqueo |
| `POST /evaluaciones/{id}/calcular-final` (evaluador, calificaciones incompletas) | evaluador | ❌ 422 `{"error":"Faltan por calificar antes de calcular la nota final: 2 compromiso(s) sin calificar (pestaña Compromisos); competencias comunes: Trabajo en equipo; competencias del nivel jerárquico: …; ejes misionales: Docencia.","pendientes":{compromisos_sin_calificar, competencias_comunes_faltantes[], competencias_nivel_faltantes[], ejes_faltantes[]}}` (solo aparecen los grupos que faltan). La pantalla marca en rojo lo pendiente y pone insignias de conteo en las pestañas |
| `POST /evaluaciones/{id}/compromisos` | evaluador | ❌ 422 `{"error":"La fecha límite de concertación para este periodo ha finalizado (dd/mm/aaaa)…"}` análogo, con extratiempo `CONCERTACION` |
| `POST /admin/evaluaciones/{id}/extratiempo` | admin: `justificacion`, `fecha_limite` (≥ hoy), `tipo` (`CONCERTACION` por defecto o `CALIFICACION`) | ✅ mensaje flash "Calificación en extratiempo habilitada correctamente." / "Concertación en extratiempo habilitada correctamente."; fila en `concertacion_extratiempo` con `tipo`; evento `EXTRATIEMPO_HABILITADO` con `tipo`. El extratiempo es **por evaluación y por tipo**; uno de concertación no habilita calificar y uno vencido tampoco |

### 2.6 Delegaciones
**`POST /admin/delegaciones`** (admin). Cuerpo:

| Campo | Notas |
|---|---|
| `id_puesto_delegante` **o** `id_vinc_delegante` | cargo (plaza) o persona titular. Con cargo ocupado delega su ocupante; con cargo vacante ver abajo |
| `id_vinc_delegado` | obligatorio |
| `fecha_inicio`, `fecha_fin?` | sin `fecha_fin` = vigencia abierta |
| `delegado_cambia_evaluador` | `0` (por defecto, regla general) o `1` (excepcional) |
| `id_vinc_superior_delegado` | solo con `delegado_cambia_evaluador=1` |
| `motivo, area, acto_administrativo*` | opcionales |

Comportamientos esperados:

| Caso | Esperado |
|---|---|
| ≤ 30 días | ✅ `ACTIVA` al instante; el delegado atiende las evaluaciones abiertas del titular (`id_vinc_suplente` = titular); sin tramos |
| > 30 días, `delegado_cambia_evaluador=0` | ✅ `PROGRAMADA` con traslados solo para las personas a cargo (sin traslado del delegado). Requiere que el titular califique cada tramo antes de `POST /admin/delegaciones/{id}/confirmar` |
| > 30 días, `delegado_cambia_evaluador=1` sin superior | ❌ error `delegaciones`: "Indicaste que el delegado cambia de evaluador: selecciona el superior que lo evaluará mientras ejerce el cargo." |
| superior = delegado / titular / persona a cargo del titular | ❌ errores `delegaciones` específicos |
| delegado es evaluado por el titular y `delegado_cambia_evaluador=0` (larga) | ❌ "El delegado es evaluado por el titular que se ausenta: necesita un nuevo evaluador. Indica que el delegado cambia de evaluador y elige su superior." |
| Misma persona delegada de dos titulares distintos, sin cambiar de evaluador | ✅ ambas delegaciones se registran |
| `id_puesto_delegante` de cargo **vacante con último ocupante y personas a cargo** | ✅ `ACTIVA` inmediata, `sin_titular=1`, `es_larga=0`, **sin traslados**, sin importar duración; las personas a cargo y sus evaluaciones `EN_PROCESO` pasan al delegado |
| cargo vacante, delegado dependía del cargo vacante y `delegado_cambia_evaluador=0` | ❌ "El delegado era evaluado por el cargo vacante y quedó sin evaluador: indica que el delegado cambia de evaluador y elige su superior." |
| cargo vacante con `delegado_cambia_evaluador=1` | ✅ el delegado pasa directo al superior (sin tramo) |
| cargo que **nunca** tuvo ocupante | ❌ "El cargo está vacante y nunca tuvo un ocupante: no tiene personas a cargo ni trabajo que delegar." |
| traslape de fechas con otra delegación del mismo cargo | ❌ "El delegante ya tiene una delegación activa que se traslapa con estas fechas." |

**`POST /admin/delegaciones/{id}/finalizar`** (JSON; `id_vinc_sucesor?`, `fecha_regreso?`, `id_vinc_evaluador_regreso?`)
- Corta, o de cargo vacante: revierte de inmediato (evaluaciones abiertas al sucesor). Cargo vacante: el sucesor es obligatorio (422 si no se indica); con `id_vinc_evaluador_regreso` el delegado vuelve a ese evaluador.
- Larga: programa los cambios de regreso (solo del delegado si `delegado_cambia_evaluador=1`); luego `POST /admin/delegaciones/{id}/confirmar`.

**`POST /admin/delegaciones/{id}/ajustar-evaluador`** (admin, JSON) — *ajuste manual*:

| Campo | Notas |
|---|---|
| `id_vinc_evaluado` | el delegado o una persona a cargo que la delegación le dejó |
| `id_vinc_evaluador_nuevo` | vinculación activa |
| `motivo` | obligatorio, máx. 500 |

Esperado: ✅ 200 `{"success":true,"message":"<nuevo> es ahora quien califica a <persona>."}`; cambio inmediato y sin tramos; evaluaciones `EN_PROCESO` pasan al nuevo evaluador (`id_vinc_suplente` = null); se conserva la asignación del titular; evento `CAMBIO_EVALUADOR` con `origen=AJUSTE_DELEGACION` y `motivo`; entrada en `delegacion.detalle_transferencia.ajustes[]`; avisos `CAMBIO_EVALUADOR` (a la persona) y `EVALUACION_REASIGNADA` (al nuevo evaluador).
Errores 422 (`message`): delegación no activa ("Solo se ajusta el evaluador de una delegación vigente (activa)."), persona fuera de la delegación ("Esa persona no está a cargo del delegado dentro de esta delegación."), evaluador = la propia persona, evaluador ya vigente, evaluador inactivo, evaluador del delegado que es persona a cargo del delegado; `errors.motivo` si falta el motivo.

**`GET /admin/delegaciones`** — cada fila trae además `personas_ajustables[]` (`id_vinculacion`, `nombre`, `es_delegado`, `evaluador_actual`) cuando `estado=ACTIVA`, `sin_titular` y `delegado_cambia_evaluador`.

### 2.7 Tableros (HTML)
- `GET /dashboard` (admin): el formulario de delegaciones trae `data-buscable` en `id_puesto_delegante` (o `id_vinc_delegante` si no hay plazas), `id_vinc_delegado`, `area` e `id_vinc_superior_delegado`; los cargos vacantes delegables aparecen como `VACANTE, último ocupante: NOMBRE`.
- `GET /dashboard` (evaluado/evaluador/admin): las tarjetas de evaluaciones de semestres partidos muestran `Parcial N de M` y `Tramo: dd/mm/aaaa al dd/mm/aaaa · X días`.
- `GET /dashboard` (cualquier rol): "Sesión activa" y el menú de perfil muestran `C.C. <cédula>` (o el usuario del admin), no el correo.
- `GET /dashboard` (admin): la tabla de Periodos tiene las columnas "Cierre concertación" y "Cierre calificación" (rojo si ya vencieron; "Sin límite" si no hay fecha); el formulario y el modal de periodos ya no traen "Inicio Concertación".
- Todas las páginas: `<meta name="csrf-token">` en el `<head>`.

### 2.8 Traslados en bloque (rotación) — admin

Flujo: **plantilla → analizar (revisión, no escribe) → programar → confirmar** (o cancelar). Cada persona es un traslado normal (`traslado.tipo=CAMBIO`) ligado al lote por `traslado.id_lote`.

| Método y ruta | Cuerpo | Esperado |
|---|---|---|
| `GET /admin/traslados/lote/plantilla` | query `cargo?` (nombre exacto del cargo) | ✅ descarga `Formato_traslados_en_bloque_AAAA-MM-DD.xlsx` con hojas `INSTRUCCIONES`, `ROTACION` y `PLAZAS`. Con `cargo`, `ROTACION` trae precargadas las personas activas de ese cargo (columnas grises) y las columnas a diligenciar en amarillo. Sin `cargo`, filas vacías |
| `POST /admin/traslados/lote/analizar` | multipart: `archivo` (.xlsx ≤10 MB), `fecha_traslado?` | ✅ 200 `{token, archivo, filas[], resumen{total, con_error, listas, sin_fecha_propia, simulado}}`. **No escribe nada**. `simulado=true` cuando se pudo ejecutar la validación completa del traslado real (hay `fecha_traslado` o todas las filas traen fecha). ❌ 422 `{message}` si no hay hoja `ROTACION` con los encabezados o ninguna fila con plaza destino |
| `POST /admin/traslados/lote/programar` | JSON: `token` (el de analizar), `archivo?`, `fecha_traslado?` (≥ hoy), `descripcion?` (≤300) | ✅ 200 `{success, id_lote, message}`: crea el lote `PROGRAMADO` y un traslado `PROGRAMADO` por fila, **sin mover a nadie**. ❌ 422 si el archivo tiene errores ("El archivo tiene filas con errores: corrígelas y vuelve a subirlo.", con `filas`); 422 "Indica la fecha del traslado: hay filas sin fecha propia en el archivo."; 422 "No se programó el lote: N fila(s) no pasaron la validación del traslado." (con `errores{fila: mensaje}`; todo o nada); 404 si el archivo de la revisión ya no está |
| `GET /admin/traslados/lotes` | — | ✅ lotes (más recientes primero) con `traslados[]` (`funcionario, plaza_origen, plaza_destino, area_origen, area_nuevo, evaluador_nuevo, fecha_traslado, estado, bloqueo`), `listos` y `pendientes`. `bloqueo` = "Falta calificar el tramo de origen." o el motivo del plazo/recurso; null si está listo |
| `POST /admin/traslados/lote/{id}/confirmar` | — | ✅ 200 "Lote #N confirmado…": mueve a **todos** en una transacción (libera primero las plazas de origen y ocupa después las de destino; cada traslado se confirma con el mismo procedimiento del individual: nuevo evaluador, dependencia/cargo, ocupación de plaza, evaluación en el cargo nuevo, bitácora, avisos). ❌ 422 "El lote aún no se puede confirmar: NOMBRE (motivo); …" si algún tramo de origen no está calificado o está en plazo de recursos: **no se mueve nadie** |
| `POST /admin/traslados/lote/{id}/cancelar` | — | ✅ 200 "Lote #N cancelado. Nadie se movió." y todos sus traslados `CANCELADO`. ❌ 422 si algún tramo de origen ya tiene nota (igual que el traslado individual) |
| `POST /admin/traslados/{id}/confirmar` o `/cancelar` sobre un traslado de lote | — | ❌ 422 "Este traslado hace parte del lote #N; confírmalo (cancélalo) desde Traslados en bloque." |

**Formato `ROTACION`** (encabezados exactos; el orden no importa): `No IDENTIFICACION` · `NOMBRE FUNCIONARIO` (informativa) · `DEPENDENCIA ACTUAL` (informativa) · `PLAZA ACTUAL` (informativa) · **`PLAZA DESTINO (CODIGO)`** (obligatoria; las filas sin plaza destino se ignoran) · `CEDULA NUEVO EVALUADOR (OPCIONAL)` · `FECHA TRASLADO (OPCIONAL)` (AAAA-MM-DD o DD/MM/AAAA, ≥ hoy; sin ella se usa `fecha_traslado` del formulario) · `OBSERVACION`. Hoja `PLAZAS`: `CODIGO PLAZA, CARGO, DEPENDENCIA, OCUPANTE ACTUAL, PLAZA SUPERIOR, EVALUADOR POR DEFECTO`.

**Evaluador nuevo:** el de `CEDULA NUEVO EVALUADOR` (debe estar activo y marcado como evaluador) o, por defecto, el ocupante de la plaza superior de la plaza destino (`origen_evaluador` = `indicado` / `plaza superior`).

**Errores por fila** (`filas[].errores`, entre otros): "No hay un funcionario activo con la cédula X.", "La persona está repetida en el archivo.", "Ya tiene un traslado programado: confírmalo o cancélalo antes.", "No tiene un evaluador asignado.", "La plaza destino «X» no existe en el catálogo.", "La plaza X recibe a más de una persona.", "La plaza destino es la que ya ocupa.", "La plaza X la ocupa NOMBRE, que no sale en este lote: elige una plaza vacante o incluye a esa persona.", "La plaza superior de la plaza destino no tiene un ocupante evaluador: indica la cédula del evaluador.", "La fecha de traslado «X» no es válida…", "La fecha de traslado no puede ser anterior a hoy." Con fecha, además los del traslado real (p. ej. "…no puede ser anterior a su último traslado en este periodo").

**Reglas:** una plaza destino puede estar ocupada si su ocupante también sale en el mismo lote (rotación); el lote solo se confirma con **todos** los tramos de origen calificados y vencido el plazo de recursos (5 días hábiles); una persona sin evaluación en el periodo no tiene tramo que calificar; el evaluador nuevo puede ser el mismo (cambia de área, no de jefe).

**Pantalla (Administración → Traslados):** orden = formulario *Traslados de Funcionarios* → panel *Traslados en bloque (rotación)* (debajo del formulario, columna izquierda) y, a la derecha, *Histórico de traslados* (`#traslados-admin-lista`, 10 más recientes, botón "Mostrar más (N restantes)", barra de desplazamiento propia). IDs útiles: `#lote-cargo`, `#lote-archivo`, `#lote-fecha`, `#lote-descripcion`, `#lote-revision` (tabla de revisión), `#lotes-admin-lista` (5 más recientes + "Mostrar más"). Botones: "Descargar formato (Excel)", "Revisar archivo", "Programar lote", "Confirmar lote", "Cancelar lote".

### 2.9 Funcionarios: crear, inhabilitar y reactivar — admin

| Método y ruta | Cuerpo | Esperado |
|---|---|---|
| `POST /admin/usuarios` ("Crear usuario y cargo") | `nombres, apellidos, tipo_documento=CEDULA_CIUDADANIA, numero_doc, cargo, area, rol (EVALUADOR/EVALUADO/ADMINISTRADOR), sistema_evaluacion?, …` (**sin correo**) | ✅ persona nueva: crea cuenta con `username` = cédula y contraseña inicial = cédula (flash `temp_password`). Persona existente: **conserva su propia cuenta** (no toma la de otro ni crea una duplicada). Si tenía inactiva una vinculación del **mismo cargo y dependencia**, la reactiva (conserva evaluador, jefe y plaza) y el mensaje dice "Funcionario reactivado: se recuperó su vinculación anterior con su evaluador. Volvió a su plaza X." (o que debe asignar la plaza). Cargo distinto → vinculación nueva. ❌ "Esta persona ya tiene una vinculación activa en ese cargo y dependencia." |
| `POST /admin/funcionarios/{id}/disable` | — | ✅ inactiva cuenta y vinculaciones y libera las plazas (ya existía) |
| `POST /admin/funcionarios/{id}/enable` | `id_vinculacion?` | ✅ reactiva la cuenta y la vinculación indicada (por defecto la más reciente que no sea vacante); vuelve a su plaza si sigue vacante: "Funcionario reactivado en CARGO · AREA. Volvió a su plaza X." ❌ "Este funcionario ya tiene una vinculación activa." / "No hay una vinculación para reactivar: …". 403 si no es admin |

En Empleados, el detalle de un funcionario inactivo (no vacante) muestra **"Reactivar Funcionario"** (`#form-habilitar-empleado`) en lugar de "Inhabilitar Funcionario".

### 2.10 Matriz de calificaciones — `GET /dashboard` (admin)
- La vista recibe `matrizCalificaciones[]` con `nota_semestre_1`, `nota_semestre_2`, `sem1_provisional`, `sem2_provisional`, `sem1_tramos`, `sem2_tramos` (p. ej. `"1/2"`) y `nota_anual`.
- Nota definitiva = evaluación semestral calificada, o promedio ponderado por días de **todos** los tramos calificados. Si algún tramo falta, se muestra la nota de los tramos calificados en ámbar con la etiqueta **"Parcial 1/2"** (`provisional=true`).
- La evaluación se asocia por el **semestre de su periodo**, aunque su `tipo_evaluacion` sea el otro número, y por **cualquier vinculación** de la persona.
- `nota_anual` solo promedia semestres completos (no provisionales).
- Exportar a Excel (CSV) incluye "4.6 | PARCIAL 1/2" en la celda.

### 2.11 Comportamiento de interfaz (para pruebas E2E con navegador)
- **Confirmaciones y avisos:** ya no hay diálogos nativos (`confirm`/`alert`). Las confirmaciones usan el modal `#modal-confirmar-accion` (botones `data-accion="cancelar"` / `data-accion="confirmar"`; Enter confirma, Esc cancela) y los avisos son toasts dentro de `#avisos-serag`. En las **firmas de concertación** (formularios `#form-firmar-evaluacion` y `#form-firmar-evaluado`) el formulario se envía solo después de confirmar en el modal.
- **Persistencia tras recarga o acción:** la pestaña activa y la evaluación abierta se recuerdan en `sessionStorage` (`serag_seccion_activa`, `serag_evaluacion_activa`, por rol) y se restauran si la página se recarga o vuelve de una acción hecha en `/dashboard`. Un ingreso nuevo desde `/login` abre la pantalla inicial.
- **Buscadores:** los `<select data-buscable>` (titular/cargo, delegado, dependencia, superior, reasignación de impedimentos, cargo del lote y los del panel "Cambiar quién califica") llevan un campo de búsqueda encima; filtra sin distinguir tildes ni mayúsculas, Enter elige el primer resultado.
- **Calificación incompleta:** tras un 422 de `calcular-final`, las filas pendientes llevan `data-pendiente="1"` con borde rojo y las pestañas muestran la insignia roja (`.badge-pendiente`) con la cuenta; escribir una nota quita el rojo de ese campo.

---

## 3. Casos de prueba sugeridos (de extremo a extremo)

1. **Recurso con oficio:** evaluado firma notificación y radica reposición → evaluador decide `NEGADO` sin `url_oficio` (422) y luego con oficio y un soporte (200); revisar que el evaluado ve ambos enlaces.
2. **Traslado con plazo:** programar un traslado, calificar el origen, firmar la notificación hoy → confirmar (422 por plazo); mover `firma.fecha_firma` 10 días atrás → confirmar (200) y verificar compromisos copiados.
3. **Impedimento al superior:** aprobar impedimento con un superior sin marca `es_evaluador`; iniciar sesión con él y verificar el rol `evaluador`; el evaluador original no puede reabrir la evaluación.
4. **Extratiempo de calificación:** poner `fecha_fin_calificacion` en el pasado a un periodo; calificar (422); autorizar extratiempo `CALIFICACION`; calificar (pasa la validación de plazo).
5. **Delegación corta y ajuste manual:** delegar 15 días; ajustar una persona a otro evaluador; finalizar y comprobar que esa persona conserva su nuevo evaluador y las demás vuelven al sucesor.
6. **Delegación larga con delegado que conserva evaluador:** 60 días, `delegado_cambia_evaluador=0`; el delegado no tiene traslado; confirmar solo exige calificar los tramos de las personas del titular.
7. **Cargo vacante:** retirar a un jefe con personas a cargo, delegar su cargo vacante sin fecha fin → activa, sin traslados; finalizar exigiendo sucesor.
8. **Login por cédula:** entrar con cédula (con y sin puntos); con correo debe fallar.
9. **Rotación en ciclo (traslados en bloque):** tomar 3 auxiliares de dependencias distintas; descargar el formato con `cargo=AUXILIAR ADMINISTRATIVO`; escribir en `PLAZA DESTINO` la plaza del siguiente (A→B, B→C, C→A) y subir el archivo con `fecha_traslado` = hoy. Esperado: revisión sin errores, evaluador por defecto = ocupante de la plaza superior; programar (no mueve a nadie); confirmar → las tres plazas, dependencias y evaluadores quedan intercambiados y existen 3 traslados `CONFIRMADO` con el mismo `id_lote`.
10. **Lote bloqueado:** repetir el caso 9 con una persona que tenga una evaluación `EN_PROCESO` en el periodo: programar funciona, confirmar da 422 "Falta calificar el tramo de origen." y **nadie** cambia de plaza; calificar su evaluación, firmar la notificación con fecha de hace 10 días y confirmar → 200.
11. **Errores de revisión:** subir un Excel con una cédula inexistente, una plaza ocupada por alguien que no sale, una plaza repetida y una fecha pasada: cada fila muestra su error y "Programar lote" queda deshabilitado.
12. **Fecha por persona:** en el caso 9 poner `FECHA TRASLADO` a una fila (hoy+5, formato DD/MM/AAAA) y dejar las otras vacías; programar y verificar las fechas de cada `traslado`.
13. **Cancelar lote:** programar un lote y cancelarlo → todos sus traslados `CANCELADO` y nadie cambió de plaza; confirmar/cancelar un traslado suelto del lote da 422.
14. **Reactivar funcionario:** inhabilitar a una persona con evaluador y plaza; en Empleados usar "Reactivar Funcionario" → vuelve a su plaza y a la lista de evaluados de su jefe. Repetir inhabilitando y usando "Crear usuario y cargo" con la misma cédula, cargo y dependencia (y con un correo de otra persona en el cuerpo): no hay error, no se duplica la vinculación ni la cuenta.
15. **Matriz con tramos:** persona con un tramo calificado y otro en proceso → la matriz muestra la nota con "Parcial 1/2" y el anual vacío; calificar el segundo tramo → nota definitiva sin etiqueta.
16. **Reasignación de impedimento:** al aprobar un impedimento, el selector no ofrece al evaluador impedido, al evaluado ni a sus personas a cargo; forzar uno por API → 422.
17. **Pestaña y evaluación tras recargar:** en Admin ir a Traslados, provocar un error de validación en el formulario (fecha pasada) → sigue en Traslados; como evaluador abrir la evaluación #N, firmar la concertación (confirmando en el modal) → vuelve a la misma evaluación.

---

## 4. Pendientes y notas

- **Matriz de Gledy en producción:** si tras desplegar sigue sin aparecer su nota semestral, hacen falta las consultas SQL de `evaluacion`, `vinculacion` y `traslado` de la cédula 1118547271.
- **Rotación:** se resuelve con los traslados en bloque (2.8) y, para correcciones directas de planta, con la carga de planta por Excel (no cambió).
- Los festivos no se descuentan en el plazo de 5 días hábiles de recursos.
- Los datos de la base local son de pruebas (Talento Humano crea periodos, traslados y calificaciones ficticias); no hay que migrar datos al desplegar.
- Casos de prueba de Antigravity ya incorporados en la base local: evaluaciones #20 a #37, traslados y delegaciones de prueba (varios traslados quedan `PROGRAMADO` por delegaciones de pruebas; la revisión de un lote los marca como "Ya tiene un traslado programado").
- Para el despliegue: `php artisan migrate`, `npm run build`, reiniciar el contenedor (OPcache), revisar `.env` (`APP_TIMEZONE=America/Bogota`) y, si se quiere otro plazo de recursos, `SERAG_DIAS_HABILES_RECURSOS`. Avisar a los usuarios que ahora ingresan con la **cédula**.
- Las pruebas automáticas no cubren JavaScript: lo de 2.11 requiere navegador.

## 5. Archivos principales tocados
`routes/web.php`, `app/Http/Controllers/{DashboardController,UsuarioController,TrasladoLoteController}.php`, `app/Services/TrasladoLote.php`,
`resources/js/dashboards/{admin,common,evaluado,evaluador}.js`, `resources/views/dashboards/{admin,evaluado,evaluador}.blade.php`,
`resources/views/{login,select-role}.blade.php`, `resources/views/layouts/app.blade.php`, `resources/views/partials/{periodo-modal,profile-menu,sidebar}.blade.php`,
`config/serag.php`, 6 migraciones nuevas y pruebas nuevas/ajustadas en `tests/Feature/`
(`LoginCedulaTest`, `PlanMejoramientoPendienteTest`, `PlazosYExtratiempoTest`, `RolEvaluadoTest`, `MatrizCalificacionesTest`, `TrasladoLoteTest`,
`ReactivacionFuncionarioTest`, y ampliaciones de `DelegacionTramosTest`, `ImpedimentoRecusacionTest`, `NotificacionCalificacionTest`, `SincronizadorPlazasTest`, `TrasladoDosPasosTest`).
