# SERAG — Cambios de la sesión de correcciones (plan de pruebas 29-10-2026) y guía de pruebas de endpoints

Base: documento *"29-10-2026 Plan Pruebas definitivas SERAG - Amarillo.docx"* (correcciones de Talento Humano).
Rama: `develop`. Suite automatizada al cierre: **207 pruebas, 1025 aserciones, todas en verde**.

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
- **VII** (matriz de calificaciones) **pendiente**: no se pudo reproducir sin datos de producción.

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

Solo aplica a traslados de evaluador (`tipo=CAMBIO`, sin `id_delegacion`). Fecha límite = `fechaLimiteRecursos()`: 5 días hábiles (lunes–viernes, sin festivos) desde `firma.NOTIFICACION_EVALUADO` o, si no hay firma, desde el evento `EVALUACION_CALIFICADA`.

### 2.4 Impedimentos / roles
| Método y ruta | Esperado |
|---|---|
| `POST /admin/impedimentos/{id}/resolver` (`estado=APROBADO`, `respuesta`, `id_vinc_nuevo_evaluador`) | ✅ la evaluación pasa al nuevo evaluador; si este no tenía marca de evaluador, en su siguiente inicio de sesión (o al recalcular roles) recibe el rol `evaluador` |
| `POST /evaluador/asignaciones` (abrir evaluación) por el evaluador que cedió | ❌ redirección con error `asignaciones`: "La evaluación de este funcionario fue reasignada a otro evaluador por un impedimento o recusación aprobados; ya no la puedes abrir." |
| Inicio de sesión del Rector | roles = `["evaluador"]` únicamente |

### 2.5 Periodos y extratiempo
| Método y ruta | Cuerpo | Esperado |
|---|---|---|
| `POST /admin/periodos` | `sistema, anio, semestre, fecha_inicio, fecha_fin`, `fecha_fin_concertacion?`, `fecha_fin_calificacion?` | ✅ guarda las dos fechas límite. ❌ error `periodo` si concertación > fin del periodo ("El cierre de concertación no puede ser posterior al fin del periodo.") o calificación < concertación ("El cierre de calificación no puede ser anterior al cierre de concertación.") |
| `POST /admin/periodos/{id}` | `fecha_inicio, fecha_fin, estado, descripcion?, fecha_fin_concertacion?, fecha_fin_calificacion?` | ✅ igual validación |
| `POST /evaluaciones/{id}/calificar-compromisos`, `.../calificar-competencias`, `.../calificar-ejes`, `.../calcular-final` | evaluador | ❌ 422 `{"message":"La fecha límite de calificación de este periodo finalizó (dd/mm/aaaa). Solicita a Talento Humano la habilitación de calificación extratiempo."}` si `fecha_fin_calificacion` ya pasó y no hay extratiempo `CALIFICACION` vigente para esa evaluación. El admin no se bloquea. Sin fecha en el periodo → no hay bloqueo |
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

---

## 4. Pendientes y notas

- **VII Matriz de calificaciones** (no actualiza la nota semestral de Gledy): pendiente de las consultas SQL en producción (`evaluacion`, `vinculacion`, `traslado` de la cédula 1118547271).
- **"Rotación"** (ajustes adicionales): sin definir qué significa.
- **Equipo de SST con delegación > 30 días:** decisión de negocio pendiente sobre cómo calificar el tramo si el titular no alcanza (hoy debe dejarlo calificado antes de ausentarse).
- El campo "Inicio Concertación" del formulario de periodos sigue sin guardarse ni usarse.
- Los festivos no se descuentan en el plazo de 5 días hábiles de recursos.
- Los datos de la base local son de pruebas (Talento Humano crea periodos, traslados y calificaciones ficticias); no hay que migrar datos al desplegar.
- Para el despliegue: `php artisan migrate`, `npm run build`, revisar `.env` (`APP_TIMEZONE=America/Bogota`) y, si se quiere otro plazo de recursos, `SERAG_DIAS_HABILES_RECURSOS`.

## 5. Archivos principales tocados
`routes/web.php`, `app/Http/Controllers/DashboardController.php`, `app/Http/Controllers/UsuarioController.php`,
`resources/js/dashboards/{admin,common,evaluado,evaluador}.js`, `resources/views/dashboards/{admin,evaluado,evaluador}.blade.php`,
`resources/views/{login,select-role}.blade.php`, `resources/views/partials/{periodo-modal,profile-menu,sidebar}.blade.php`,
`config/serag.php`, 5 migraciones nuevas y pruebas nuevas/ajustadas en `tests/Feature/`
(`LoginCedulaTest`, `PlanMejoramientoPendienteTest`, `PlazosYExtratiempoTest`, `RolEvaluadoTest`, y ampliaciones de
`DelegacionTramosTest`, `ImpedimentoRecusacionTest`, `NotificacionCalificacionTest`, `SincronizadorPlazasTest`, `TrasladoDosPasosTest`).
