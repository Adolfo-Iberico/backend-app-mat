# Aulamate Backend

API backend de Aulamate, una plataforma educativa de matemáticas con gestión de contenidos, evaluaciones, seguimiento académico, gamificación y comunicación entre estudiantes, docentes, familias y administración.

## Stack

- PHP 8.2+
- Laravel 12
- Laravel Sanctum para autenticación de la API
- MySQL como base de datos principal
- Laravel Queue y Cache con soporte para base de datos
- Google OAuth mediante Laravel Socialite y Google API Client
- Groq para funciones de IA a través de un proxy del backend
- Dompdf y Laravel Excel para exportación de reportes
- Web Push y Firebase Cloud Messaging para notificaciones
- PHPUnit para pruebas

## Funcionalidades

- Registro, inicio de sesión, recuperación de contraseña y verificación de correo.
- Inicio de sesión y vinculación de cuentas con Google OAuth.
- Perfiles, cambio de contraseña, sesiones, dispositivos y cierre de sesión individual o global.
- Dashboards para estudiantes, docentes y administradores.
- Lecciones, unidades, recursos, publicación, duplicación y seguimiento del progreso.
- Evaluaciones adaptativas, preguntas, entregas, resultados y estadísticas.
- Exámenes con intentos, activación, resultados y detección/reportes de comportamiento no permitido.
- Entrega y calificación de trabajos de estudiantes.
- Cursos, salones, periodos académicos y calendario.
- Reportes de rendimiento, calificaciones, participación y detalle por estudiante o curso.
- Exportación de reportes en PDF, Excel y CSV.
- Rankings, niveles, insignias, logros y actividades de gamificación.
- Juegos educativos y simulaciones integrables con el aprendizaje.
- Mensajería, foros, notificaciones internas y notificaciones push.
- Panel de administración para usuarios, configuración, traducciones, respaldos y logros.
- Funciones de IA para chat educativo y generación de lecciones para roles autorizados.
- Soporte de traducciones del sistema y configuración regional en español.

## Roles

La API aplica autorización por rol en las rutas protegidas. Los roles utilizados por la aplicación incluyen:

- `student`: lecciones, evaluaciones, exámenes, progreso, juegos y gamificación.
- `teacher`: creación y gestión de contenidos, evaluaciones, exámenes, calificaciones y reportes.
- `parent`: consulta del progreso y reportes de estudiantes vinculados.
- `coordinador` y `director`: gestión de salones y funciones de coordinación disponibles.
- `admin`: administración global, configuración, usuarios, traducciones y respaldos.

## API

La API está versionada bajo `/api/v1`.

Áreas principales:

- `/auth`: autenticación local, Google OAuth y recuperación de contraseña.
- `/user`, `/devices`, `/notifications`: cuenta, sesiones, dispositivos y notificaciones.
- `/dashboard`, `/progress`, `/lessons`: paneles, estadísticas, contenidos y progreso.
- `/evaluations`, `/exams`, `/submitted-works`: evaluación, exámenes y trabajos entregados.
- `/rankings`, `/gamification`, `/games`: clasificación y mecánicas de aprendizaje.
- `/reports`: reportes y exportaciones para docentes y administradores.
- `/parent`, `/admin`, `/calendar`, `/salons`: familias, administración, calendario y salones.
- `/messages`, `/forum`: comunicación y participación.
- `/ai`: chat educativo y generación de lecciones mediante el backend.

Las rutas protegidas requieren una sesión válida de Sanctum, usuario activo y los permisos del rol correspondiente. También se aplican límites de frecuencia, auditoría y caché donde corresponde.

El login ejecuta la verificación de contraseña también cuando el correo no
existe, y los intentos de registro duplicado devuelven un error genérico sin
confirmar si la cuenta está registrada. Mantén esos mensajes genéricos en
cualquier cliente de la API.

### Consulta de progreso para apoderados

`POST /api/v1/guest/student-lookup` y
`POST /api/v1/guest/student-lookup/export` requieren una cuenta activa con rol
`parent`, autenticada con Sanctum. Cada consulta queda restringida a los
estudiantes vinculados a esa cuenta en `parent_student`; conocer el DNI no
concede acceso. Los estudiantes no vinculados y los DNI inexistentes reciben
la misma respuesta. El CAPTCHA sigue siendo una defensa adicional y cada
token es de un solo uso, también en los intentos fallidos.

Para habilitar el acceso de una familia, crea/verifica su cuenta `parent` y
registra la relación con el estudiante en `parent_student`. No publiques esos
datos ni expongas los reportes por rutas anónimas.

### Integración con E-Lean Studio

El frontend `E-LeanStudio-app-mat` consume esta API desde acciones de servidor de Next.js. La URL base debe apuntar al prefijo versionado:

```text
http://127.0.0.1:8000/api/v1
```

En el frontend se configura como `API_BASE_URL` en `.env.local`. Para producción usa la URL HTTPS pública del backend terminada en `/api/v1`; no uses `localhost` ni `127.0.0.1` en Vercel. El token de Sanctum lo conserva el frontend en una cookie `httpOnly` y lo envía como Bearer en las solicitudes autenticadas.

Los flujos del frontend utilizan, entre otros, estos grupos de rutas:

- `/auth`: registro, acceso y recuperación de contraseña.
- `/user`: perfil, cambio de contraseña y cierre de sesión.
- `/lessons`, `/evaluations`: catálogo, contenidos, progreso y evaluaciones.
- `/dashboard`, `/notifications`: paneles y notificaciones.
- `/forum`, `/announcements`: foro y novedades.
- `/admin`: operaciones de administración autorizadas por rol.

Para desarrollo local, inicia Laravel en el puerto 8000 y el frontend en el puerto 9002. El frontend realiza las llamadas al backend desde el servidor de Next.js, no directamente desde el navegador; por ello CORS no interviene en ese patrón. Si se habilitan llamadas desde el navegador en el futuro, agrega el origen exacto del frontend a `CORS_ALLOWED_ORIGINS` y verifica la configuración de Sanctum correspondiente. No guardes secretos del backend en el frontend.

## Requisitos

- PHP 8.2 o superior.
- Composer.
- Node.js y npm.
- MySQL 8+ o una base de datos compatible con la configuración de Laravel.
- Servicios opcionales según el despliegue: Google OAuth, proveedor de correo, Groq, FCM y Web Push.

## Instalación local

```bash
composer install
copy .env.example .env
php artisan key:generate
```

Configura la base de datos en `.env` usando valores locales o de tu entorno. Para desarrollo con el esquema histórico de Laravel, ejecuta:

```bash
php artisan migrate --seed
php artisan storage:link
npm install
```

En PowerShell, el equivalente de `copy` es:

```powershell
Copy-Item .env.example .env
```

El esquema v2 de producción es una ruta distinta: importa `../schema.sql` en
una base vacía y no ejecutes `php artisan migrate` sobre ella. Para actualizar
una instalación v2 existente, respáldala y sigue los pasos de
[`DEPLOYMENT.md`](DEPLOYMENT.md), incluido el parche
`database/schema-v2-upgrades/2026_11_09_000001_add_shared_cache_and_sessions.sql`.

## Desarrollo

Para iniciar el servidor de Laravel:

```bash
php artisan serve
```

Para procesar la cola en otra terminal:

```bash
php artisan queue:listen --tries=1
```

Para iniciar Vite si se usan recursos del backend:

```bash
npm run dev
```

El script Composer `dev` inicia servidor, cola, logs y Vite de forma concurrente:

```bash
composer run dev
```

## Pruebas y calidad

```bash
php artisan test
vendor/bin/phpunit
vendor/bin/pint --test
```

Antes de probar integraciones externas, usa credenciales de desarrollo y una base de datos aislada.

La suite de backend se ejecuta en GitHub Actions con SQLite y también prueba
una migración limpia de MariaDB. El CI separado de `frontend-app-mat` valida
`npm ci`, TypeScript y el build de producción.

## Variables de entorno

Usa `.env.example` como plantilla. Entre las variables esperadas están:

- Aplicación: `APP_NAME`, `APP_ENV`, `APP_KEY`, `APP_URL`, `APP_DEBUG`.
- Base de datos: `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`.
- Sesión, caché y cola: `SESSION_*`, `CACHE_STORE`, `QUEUE_CONNECTION`, `REDIS_*`.
- Correo: `MAIL_*`.
- Google OAuth: `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI`.
- Frontend web: `FRONTEND_URL` (URL base usada para volver al inicio de sesión tras Google OAuth).
- Sanctum y CORS: `SANCTUM_*`, `SESSION_DOMAIN`, `CORS_ALLOWED_ORIGINS`.
- IA: `GROQ_API_KEY`.
- Push: `FCM_*` y las variables requeridas por Web Push.

No coloques valores reales en este README, `.env.example`, commits, logs, capturas ni issues. El archivo `.env` debe permanecer fuera del control de versiones. Las claves secretas de proveedores deben existir únicamente en el entorno del backend y nunca en variables `VITE_*`.

Las versiones de `minishlink/web-push` y `sentry/sentry-laravel` usan rangos
de versión explícitos en `composer.json`; instala con `composer install` para
respetar las versiones fijadas en `composer.lock`.

El `.env.example` usa `CACHE_STORE=database` y `SESSION_DRIVER=database` para
que CAPTCHA, rate limits y sesiones se compartan entre instancias. El esquema
v2 incluye las tablas `cache`, `cache_locks` y `sessions`. Si cambias a Redis,
configura y provisiona Redis antes de seleccionar ese driver. La cola de
ejemplo sigue siendo `sync`; para procesamiento asíncrono configura un driver
y worker adecuados a tu infraestructura.

Para el entorno local, registra `http://localhost:8000/api/v1/auth/google/callback` como URI de retorno autorizado en Google y configura `FRONTEND_URL=http://localhost:9002`. En producción, usa el URI HTTPS público de la API y el origen HTTPS del frontend.

## Estructura

```text
app/
  Http/Controllers/Api/   Controladores de la API
  Models/                 Modelos Eloquent
  Services/               Servicios de dominio
  Policies/               Autorización
  Imports/ Exports/       Importación y exportación de datos
config/                   Configuración de Laravel
 database/                Migraciones, factories y seeders
routes/api.php            Rutas versionadas de la API
resources/                Recursos frontend del backend
storage/                  Logs, caché y archivos generados
 tests/                   Pruebas unitarias y funcionales
```

## Despliegue

El despliegue debe configurar el entorno de producción fuera del repositorio y servir `public/` como raíz web. Este proyecto se despliega en cPanel: el document root debe apuntar a `public/`, el dominio es `api.aulamate.com` y el certificado se emite con AutoSSL. Antes de actualizar, identifica el esquema de base de datos: sigue [`DEPLOYMENT.md`](DEPLOYMENT.md) para el esquema v2 y no ejecutes migraciones Laravel sobre esa base.

Nunca habilites `APP_DEBUG=true` en producción ni publiques archivos `.env`, tokens, claves privadas, dumps de base de datos o credenciales de servicios.
