<p align="center">
  <img src="public/escudo-color.png" width="120" alt="Unitrópico Escudo">
</p>

<h1 align="center">SERAG — Portal Nomos</h1>
<h3 align="center">Sistema de Evaluación del Rendimiento Laboral y Acuerdos de Gestión</h3>
<p align="center">
  <strong>Universidad Internacional del Trópico Americano (Unitrópico)</strong><br>
  Dirección de Talento Humano &amp; Oficina de Tecnologías de la Información
</p>

<p align="center">
  <img src="https://img.shields.io/badge/Laravel-13.x-FF2D20?style=for-the-badge&logo=laravel&logoColor=white" alt="Laravel 13">
  <img src="https://img.shields.io/badge/PHP-8.3%20%7C%208.4-777BB4?style=for-the-badge&logo=php&logoColor=white" alt="PHP 8.3/8.4">
  <img src="https://img.shields.io/badge/Tailwind_CSS-v4.0-38B2AC?style=for-the-badge&logo=tailwind-css&logoColor=white" alt="Tailwind CSS 4">
  <img src="https://img.shields.io/badge/MySQL-8.0-4479A1?style=for-the-badge&logo=mysql&logoColor=white" alt="MySQL 8.0">
  <img src="https://img.shields.io/badge/Docker-Ready-2496ED?style=for-the-badge&logo=docker&logoColor=white" alt="Docker">
  <img src="https://img.shields.io/badge/License-MIT-green.svg?style=for-the-badge" alt="License">
</p>

---

## 📋 Tabla de Contenidos

1. [Descripción General](#-descripción-general)
2. [Arquitectura y Stack Tecnológico](#-arquitectura-y-stack-tecnológico)
3. [Módulos y Roles del Sistema](#-módulos-y-roles-del-sistema)
4. [Sistemas de Evaluación y Modelo de Calificación](#-sistemas-de-evaluación-y-modelo-de-calificación)
5. [Flujo Procesal y Jurídico de Evaluación](#-flujo-procesal-y-jurídico-de-evaluación)
6. [Estructura del Proyecto](#-estructura-del-proyecto)
7. [Requisitos del Entorno](#-requisitos-del-entorno)
8. [Instalación y Despliegue](#-instalación-y-despliegue)
   - [Opción 1: Despliegue con Docker (Recomendado)](#opción-1-despliegue-con-docker-recomendado)
   - [Opción 2: Instalación Local Tradicional](#opción-2-instalación-local-tradicional)
9. [Cuentas y Datos Iniciales (Seeders)](#-cuentas-y-datos-iniciales-seeders)
10. [Pruebas Automatizadas y Aseguramiento](#-pruebas-automatizadas-y-aseguramiento)
11. [Identidad Visual y Diseño](#-identidad-visual-y-diseño)
12. [Créditos y Licencia](#-créditos-y-licencia)

---

## 📖 Descripción General

**SERAG / Portal Nomos** es la plataforma institucional integral diseñada para la **gestión, seguimiento, concertación y calificación del desempeño laboral** de los servidores públicos y directivos de **Unitrópico**.

El sistema digitaliza y automatiza los dos marcos normativos institucionales de evaluación:
- **Rendimiento Laboral:** Aplicable a empleados públicos y provisionales (evaluación semestral y anual basada en compromisos funcionales y competencias laborales).
- **Acuerdos de Gestión:** Aplicable al nivel directivo y cargos con responsabilidad misional (integra compromisos gerenciales, competencias y ejes misionales universitarios: docencia, investigación y proyección social).

### Objetivos Clave
- **Trazabilidad Jurídica Total:** Registro de consentimientos, firmas digitales, radicación de desacuerdos, impedimentos, recusaciones y recursos de reposición/apelación.
- **Transparencia y Control:** Notificaciones automáticas de estados, auditoría de períodos, control de traslados y seguimiento de tramos laborales (mínimo de 90 días para evaluaciones parciales).
- **Generación Oficial de Documentos:** Exportación instantánea de actas e informes en PDF conforme a las resoluciones institucionales y exportación de matrices completas a Excel/CSV.

---

## 🛠 Arquitectura y Stack Tecnológico

| Capa | Tecnología / Herramienta | Versión / Detalle |
| :--- | :--- | :--- |
| **Backend Framework** | [Laravel](https://laravel.com/) | 13.x (arquitectura MVC y Query Builder optimizado) |
| **Lenguaje de Programación** | [PHP](https://www.php.net/) | ^8.3 / 8.4-FPM con extensiones `intl`, `pdo_mysql`, `gd`, `bcmath`, `opcache` |
| **Base de Datos** | [MySQL](https://www.mysql.com/) / [SQLite](https://www.sqlite.org/) | MySQL 8.0 en producción/Docker; SQLite para testing |
| **Estilos y UI** | [Tailwind CSS](https://tailwindcss.com/) | v4.0 con integración nativa `@tailwindcss/vite` |
| **Frontend Bundler** | [Vite](https://vitejs.dev/) | 8.x con HMR y refresco de vistas Blade |
| **Generación de Reportes** | [DomPDF](https://github.com/dompdf/dompdf) | ^3.1 (generación de informes oficiales semestrales y anuales) |
| **Contenedores y DevOps** | [Docker](https://www.docker.com/) & Docker Compose | Multi-stage build con Nginx, Supervisor, PHP 8.4-FPM Alpine |
| **Suite de Pruebas** | [PHPUnit](https://phpunit.de/) | ^12.x para pruebas unitarias y de integración de escala/notificaciones |

---

## 👥 Módulos y Roles del Sistema

El sistema implementa un modelo **Multi-Rol con selector de perfil dinámico** (`/seleccionar-rol`), permitiendo a un mismo funcionario actuar en diferentes facetas según sus vinculaciones activas:

```
                     ┌──────────────────────────────────┐
                     │          Portal Nomos            │
                     │  Autenticación Institucional     │
                     └─────────────────┬────────────────┘
                                       │
                    ┌──────────────────┴──────────────────┐
                    ▼                                     ▼
        ┌───────────────────────┐             ┌───────────────────────┐
        │   Rol Único Asignado  │             │   Múltiples Roles     │
        │ Redirección Directa   │             │ /seleccionar-rol      │
        └───────────┬───────────┘             └───────────┬───────────┘
                    │                                     │
                    └──────────────────┬──────────────────┘
                                       │
         ┌─────────────────────────────┼─────────────────────────────┐
         ▼                             ▼                             ▼
┌──────────────────┐          ┌──────────────────┐          ┌──────────────────┐
│  ADMINISTRADOR   │          │    EVALUADOR     │          │     EVALUADO     │
│ (Talento Humano) │          │ (Jefe Inmediato) │          │  (Funcionario)   │
└──────────────────┘          └──────────────────┘          └──────────────────┘
```

### 1. 🛡️ Rol Administrador (Talento Humano)
- **Gestión Institucional:** Registro de funcionarios, cargos, dependencias y vinculaciones laborales.
- **Apertura y Auditoría de Períodos:** Gestión de ciclos anuales, semestrales y períodos parciales con registro de auditoría (`periodo_auditoria`).
- **Ponderaciones del Sistema:** Configuración de pesos para compromisos, competencias y ejes misionales.
- **Matriz Global de Calificaciones:** Tablero centralizado con búsqueda, filtros avanzados por estado/sistema y exportación a **Excel (CSV)**.
- **Novedades Administrativas:**
  - Gestión de **traslados de personal** (congelación de evaluaciones previas y habilitación de evaluaciones parciales).
  - Trámite y resolución de **impedimentos y recusaciones**.
  - Registro de **delegaciones de funciones evaluadoras** mediante acto administrativo.
  - Registro de **vacancias** y vinculación de jefes jerárquicos superiores.
  - Configuración y enlace a la **encuesta institucional de necesidades de capacitación**.

### 2. 📝 Rol Evaluador (Jefe Inmediato / Directivo)
- **Asignación y Apertura:** Creación de evaluaciones semestrales (`SEMESTRE_1`, `SEMESTRE_2`) o parciales (`PARCIAL`) para funcionarios asignados.
- **Fase de Concertación:**
  - Redacción de compromisos laborales con ponderación porcentual.
  - Definición de metas verificables por cada compromiso.
  - Revisión y contestación de observaciones o desacuerdos formulados por el evaluado.
- **Evaluación y Seguimiento:**
  - Calificación de compromisos sobre la escala institucional.
  - Evaluación de competencias laborales (comunes y del nivel jerárquico: directivo, asesor, profesional, técnico, asistencial).
  - Calificación de ejes misionales (Docencia, Investigación, Proyección Social) en acuerdos de gestión.
  - Concertación obligatoria de **Planes de Mejoramiento** para calificaciones que lo requieran.
- **Firma y Notificación:** Notificación oficial del resultado semestral o definitivo al evaluado.

### 3. 👤 Rol Evaluado (Funcionario Público)
- **Concertación y Compromisos:**
  - Consulta detallada de compromisos concertados y metas asignadas.
  - Formulación de **desacuerdos motivados** sobre compromisos específicos antes de firmar.
  - Manifestación de **impedimentos o recusaciones** durante la fase inicial.
  - Firma digital de aceptación de la concertación.
- **Aportes y Evidencias:**
  - Registro y cargue de evidencias (enlaces documentales, repositorios o expedientes).
- **Resultados y Recursos:**
  - Visualización del tablero de resultados y calificaciones definitivas.
  - Firma de la notificación de calificación para dejar trazabilidad digital.
  - Consulta y radicación del documento de notificación firmado físicamente.
  - Interposición de **Recurso de Reposición** (ante el evaluador) y **Recurso de Apelación** (ante el superior jerárquico).
  - Acceso directo a la **Encuesta de Detección de Necesidades de Capacitación**.

---

## 📊 Sistemas de Evaluación y Modelo de Calificación

### 1. Ponderación por Sistema de Evaluación

| Componente Evaluativo | Rendimiento Laboral | Acuerdo de Gestión (con eje misional) | Acuerdo de Gestión (sin eje misional) |
| :--- | :---: | :---: | :---: |
| **Compromisos Laborales / Gerenciales** | **80%** | **50%** | **80%** *(redistribuido)* |
| **Competencias Conductuales / Nivel** | **20%** | **20%** | **20%** |
| **Eje Docencia** | 0% | **10%** | 0% *(se suma a compromisos)* |
| **Eje Investigación** | 0% | **10%** | 0% *(se suma a compromisos)* |
| **Eje Proyección Social** | 0% | **10%** | 0% *(se suma a compromisos)* |
| **TOTAL** | **100%** | **100%** | **100%** |

> **Nota:** Si un funcionario con Acuerdo de Gestión no tiene asignado alguno de los ejes misionales (por ejemplo, investigación o proyección social), el sistema redistribuye dinámicamente ese porcentaje al peso de los compromisos laborales, garantizando siempre el 100%.

### 2. Escala Institucional de Calificación (1.0 a 5.0)

El sistema opera bajo una escala unificada de cuatro bandas de calificación, configurada en `app/Helpers/calificaciones.php`:

```
 [1.0 ────────────── 3.4]  ──>  NO SATISFACTORIO           (Plan de Mejoramiento Obligatorio)
 [3.5 ────────────── 4.0]  ──>  SUSCEPTIBLE A MEJORA       (Plan de Mejoramiento Concertado)
 [4.1 ────────────── 4.5]  ──>  BUENO                      (Aprobatorio / Nivel Satisfactorio)
 [4.6 ────────────── 5.0]  ──>  SOBRESALIENTE              (Nivel de Excelencia)
```

---

## 🔄 Flujo Procesal y Jurídico de Evaluación

```
  ┌────────────────────────────────────────────────────────┐
  │ FASE 1: Concertación de Compromisos y Metas             │
  │ Evaluador formula compromisos, metas y competencias.   │
  │ Evaluado puede registrar desacuerdos o impedimentos.   │
  │ Ambos firman digitalmente la concertación.              │
  └───────────────────────────┬────────────────────────────┘
                              │
  ┌───────────────────────────▼────────────────────────────┐
  │ FASE 2: Seguimiento y Cargue de Evidencias             │
  │ Registro continuo de evidencias, observaciones e       │
  │ instancias externas durante el período evaluativo.     │
  └───────────────────────────┬────────────────────────────┘
                              │
  ┌───────────────────────────▼────────────────────────────┐
  │ FASE 3 / 4: Calificación Semestral y Definitiva        │
  │ Evaluación de compromisos, competencias y ejes.        │
  │ Cálculo automático ponderado en escala 1.0 a 5.0.      │
  │ Si la nota <= 4.0: Concertación de Plan de Mejora.     │
  └───────────────────────────┬────────────────────────────┘
                              │
  ┌───────────────────────────▼────────────────────────────┐
  │ FASE 5: Notificación, Radicación y Recursos Legales    │
  │ Notificación oficial y firma del evaluado.             │
  │ Descarga de informes oficiales en PDF (DomPDF).        │
  │ Trámite de Recursos: Reposición / Apelación.           │
  └────────────────────────────────────────────────────────┘
```

---

## 📁 Estructura del Proyecto

```text
portal-nomos/
├── app/
│   ├── Helpers/
│   │   └── calificaciones.php      # Motor de cálculo, redondeo, bandas y ponderaciones
│   ├── Http/Controllers/
│   │   ├── Controller.php          # Controlador base
│   │   ├── DashboardController.php # Orquestador principal de vistas por rol y consultas
│   │   └── UsuarioController.php   # CRUD de usuarios, cargos, dependencias y vacancias
│   ├── Models/                     # Modelos Eloquent del dominio
│   │   ├── Cargo.php
│   │   ├── Compromiso.php
│   │   ├── CompromisoMeta.php
│   │   ├── Delegacion.php
│   │   ├── Dependencia.php
│   │   ├── Evaluacion.php
│   │   ├── Firma.php
│   │   ├── Funcionario.php
│   │   ├── ImpedimentoRecusacion.php
│   │   ├── Notificacion.php
│   │   ├── Periodo.php
│   │   ├── User.php
│   │   └── Vinculacion.php
│   └── Providers/
├── database/
│   ├── migrations/                 # Migraciones cronológicas de base y sprints (S1..S13)
│   └── seeders/                    # Semilleros de usuarios y escenarios de prueba
├── docker/                         # Configuraciones para contenedorización
│   ├── entrypoint.sh               # Script de inicio del contenedor
│   ├── nginx/default.conf          # Servidor Nginx para PHP-FPM
│   ├── php/conf.d/                 # Directivas OPcache y límites de subida
│   └── supervisord.conf            # Gestor de procesos Nginx + PHP-FPM
├── public/                         # Punto de entrada web y activos públicos
├── resources/
│   ├── css/                        # app.css (Tailwind CSS 4)
│   ├── js/
│   │   └── dashboards/             # Módulos JS organizados por rol
│   │       ├── admin.js
│   │       ├── common.js
│   │       ├── evaluado.js
│   │       └── evaluador.js
│   └── views/                      # Plantillas Blade
│       ├── dashboards/             # admin.blade.php, evaluado.blade.php, evaluador.blade.php
│       ├── layouts/                # app.blade.php
│       ├── partials/               # Modales, header, sidebar, alertas y menú de usuario
│       ├── reportes/               # Plantillas PDF (informe-semestral e informe-anual)
│       ├── login.blade.php         # Pantalla de acceso institucional
│       └── select-role.blade.php   # Selector dinámico de perfil
├── routes/
│   └── web.php                     # Definición de rutas, middleware y acciones del flujo
├── tests/
│   └── Feature/                    # Pruebas funcionales de escala y notificaciones
├── Dockerfile                      # Imagen multi-stage Alpine para producción
├── docker-compose.yml              # Orquestación de App, MySQL 8.0 y phpMyAdmin
├── package.json                    # Dependencias Node.js, Vite 8 y Tailwind 4
└── composer.json                   # Dependencias PHP y scripts de desarrollo
```

---

## 💻 Requisitos del Entorno

### Si se despliega con Docker:
- **Docker Desktop** (en Windows/macOS) o **Docker Engine** v24+ (en Linux).
- **Docker Compose** v2+.

### Si se ejecuta en local (sin Docker):
- **PHP** >= 8.3 con extensiones: `bcmath`, `ctype`, `curl`, `dom`, `fileinfo`, `gd`, `intl`, `mbstring`, `openssl`, `pdo_mysql`, `tokenizer`, `xml`, `zip`.
- **Composer** >= 2.6.
- **Node.js** >= 20.x y **npm** >= 10.x.
- **MySQL Server** >= 8.0.

---

## 🚀 Instalación y Despliegue

### Opción 1: Despliegue con Docker (Recomendado)

1. **Clonar el repositorio:**
   ```bash
   git clone https://github.com/WBeltranDev/portal-nomos.git
   cd portal-nomos
   ```

2. **Configurar las variables de entorno:**
   ```bash
   cp .env.example .env
   ```
   Ajusta en `.env` las variables de base de datos para Docker:
   ```env
   DB_CONNECTION=mysql
   DB_HOST=mysql
   DB_PORT=3306
   DB_DATABASE=portal_nomos
   DB_USERNAME=nomos_user
   DB_PASSWORD=nomos_secret
   MYSQL_ROOT_PASSWORD=root_secret
   ```

3. **Construir y levantar los contenedores:**
   ```bash
   docker compose up -d --build
   ```

4. **Ejecutar migraciones y semilleros dentro del contenedor:**
   ```bash
   docker compose exec app php artisan key:generate
   docker compose exec app php artisan migrate --force
   docker compose exec app php artisan db:seed
   ```

5. **Acceder a la plataforma:**
   - **Aplicación Web:** [http://localhost:8082](http://localhost:8082)
   - **phpMyAdmin:** [http://localhost:8081](http://localhost:8081)

---

### Opción 2: Instalación Local Tradicional

1. **Instalar dependencias de PHP:**
   ```bash
   composer install
   ```

2. **Instalar dependencias de Frontend:**
   ```bash
   npm install
   ```

3. **Configurar el archivo `.env`:**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```
   Configura la conexión a tu servidor MySQL local:
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=portal_nomos
   DB_USERNAME=root
   DB_PASSWORD=tu_password
   ```

4. **Ejecutar migraciones y datos iniciales:**
   ```bash
   php artisan migrate
   php artisan db:seed
   ```

5. **Compilar los recursos de frontend:**
   ```bash
   npm run build
   # O en modo desarrollo con recarga en vivo:
   npm run dev
   ```

6. **Iniciar el servidor local:**
   ```bash
   php artisan serve
   ```
   O utilizar el runner concurrente integrado de Composer:
   ```bash
   composer dev
   ```
   La aplicación estará disponible en [http://localhost:8000](http://localhost:8000).

---

## 🔑 Cuentas y Datos Iniciales (Seeders)

El semillero principal (`database/seeders/DatabaseSeeder.php`) provee cuentas de prueba preconfiguradas con contraseñas de desarrollo:

| Rol | Correo Institucional | Contraseña | Perfil / Cargo |
| :--- | :--- | :--- | :--- |
| **Administrador** | `wiliverbeltran.es@unitropico.edu.co` | `123456789` | Administrador de Talento Humano |
| **Evaluador** | `evaluador@unitropico.edu.co` | `123456789` | Juan Pérez (Director de Sistemas) |
| **Evaluado** | `evaluado@unitropico.edu.co` | `123456789` | María Gómez (Profesional de Soporte) |

> ⚠️ **Advertencia de Seguridad:** En entornos de producción (Staging o Live), cambie de inmediato las credenciales por defecto y desactive `APP_DEBUG=false` en el archivo `.env`.

---

## 🧪 Pruebas Automatizadas y Aseguramiento

El proyecto cuenta con una batería de pruebas de integración y comportamiento (`tests/Feature`):

```bash
# Ejecutar todas las pruebas
php artisan test

# O mediante el script definido en Composer
composer test

# Ejecutar una suite específica (ej. Escala de Calificación)
php artisan test --filter=EscalaCalificacionTest

# Ejecutar pruebas de Notificación y Firmas
php artisan test --filter=NotificacionCalificacionTest
```

### Casos de Prueba Cubiertos
- **Cálculo de Escala Institucional:** Verificación de las cuatro bandas de calificación, redondeo estricto a 1 decimal y validación de que notas aprobatorias (>= 4.1) no exijan plan de mejoramiento.
- **Notificaciones y Documentos Radicados:** Flujos de notificación formal, firma digital del evaluado, registro de anexos PDF escaneados y radicación de recursos.
- **Aislamiento de Períodos y Traslados:** Protección de integridad referencial al producirse traslados de personal y cierre de períodos parciales.

---

## 🎨 Identidad Visual y Diseño

La interfaz gráfica de **SERAG** fue construida siguiendo los estándares institucionales de **Unitrópico**:
- **Paleta de Colores Primaria:** Verde Selva Institucional (`#004037` / `#00594E`) y Oro Sabanero (`#B5A160`).
- **Superficies y Contenedores:** Estilo contemporáneo con efecto *glassmorphism*, bordes suaves redondeados (`rounded-3xl`) y contrastes accesibles conformes a las pautas WCAG.
- **Tipografía:** [Inter](https://fonts.google.com/specimen/Inter) vía Google Fonts para lectura técnica y formal.
- **Iconografía:** [Google Material Symbols Outlined](https://fonts.google.com/icons).
- **Experiencia de Usuario (UX):** Modales globales de procesamiento asíncrono para prevenir envíos duplicados, confirmaciones destructivas y tablas responsivas con paginación optimizada.

---

## 📄 Créditos y Licencia

Desarrollado para la **Universidad Internacional del Trópico Americano — Unitrópico**, Yopal, Casanare, Colombia.

Este proyecto es software de uso institucional y está protegido bajo la licencia **[MIT](LICENSE)**.
