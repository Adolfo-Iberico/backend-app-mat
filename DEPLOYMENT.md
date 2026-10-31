# Despliegue de Aulamate

## Esquema de produccion elegido

La fuente y el destino confirmados son `mentematica_v2` y el esquema
consolidado `../schema.sql`. El proyecto conserva `roles`, `courses` y
`role_id`; no se deben fusionar automaticamente datos de la otra base local
`mentematica`.

Hay dos rutas de esquema incompatibles:

- `../schema.sql`: esquema v2 elegido para produccion.
- `database/migrations`: esquema historico de Laravel para pruebas y una
  instalacion independiente. Las 73 migraciones crean 47 tablas vacias en
  MariaDB 10.4; no son una actualizacion valida del SQL v2.

No ejecutar `php artisan migrate` sobre una base v2. La primera migracion
detecta el marcador `2026_10_22_000000_baseline_schema_v2` y aborta antes de
cambiar el esquema. Las actualizaciones de v2 se aplican mediante archivos
versionados en `database/schema-v2-upgrades`, despues de un respaldo y un ensayo
en staging. No ejecutar `migrate:fresh` en una base que contenga datos.

## Instalacion nueva con schema v2

1. Crear una base MariaDB compatible y un usuario de aplicacion.
2. Crear un respaldo verificable antes de cualquier importacion.
3. Importar `../schema.sql` en la base vacia. No ejecutar `php artisan migrate`
   despues de la importacion.
4. Crear `.env` desde `.env.example`. Configurar dominios HTTPS reales,
   credenciales, correo y los mismos origenes en `FRONTEND_URL`,
   `CORS_ALLOWED_ORIGINS` y `SANCTUM_STATEFUL_DOMAINS`.
5. Generar `APP_KEY` una sola vez. No copiar `.env`, claves ni respaldos al
   repositorio.
6. Desplegar el backend e instalar dependencias de produccion:

   ```sh
   composer install --no-dev --prefer-dist --no-progress --optimize-autoloader
   php artisan storage:link
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```

7. Configurar el scheduler segun el proveedor. La cola `sync` no requiere
   worker; si se cambia a un driver asincrono, provisionar primero su backend.
8. En E-LeanStudio, definir `API_BASE_URL` (URL HTTPS del API con `/api/v1`)
   y `NEXT_PUBLIC_SITE_URL`, ejecutar `npm ci`, `npm run typecheck` y
   `npm run build`, y arrancar con `npm start`.

## Actualizacion de una base v2 existente

La base seleccionada se clono en staging desde el respaldo privado. El ensayo
detecto siete columnas de perfil, `threads.author_id` y las tablas
`lesson_segments`, `submitted_works` y `announcements` ausentes respecto al SQL
v2 actual. Se agrego el parche idempotente
`database/schema-v2-upgrades/2026_10_31_000001_align_schema_v2.sql`: agrega las
columnas anulables, indices/claves foraneas y las tres tablas; no borra ni
transforma filas existentes.

El parche se ejecuto dos veces en la copia staging. Despues, las columnas,
indices y claves foraneas coincidieron con el SQL v2 actual; los conteos de las
tablas existentes se mantuvieron iguales (incluidos 17 usuarios, 181
evaluaciones, 182 lecciones y 905 preguntas). La copia paso de 27 a 30 tablas
base; `lesson_segments`, `submitted_works` y `announcements` quedaron vacias.
La base fuente no se toco.

Para el corte de produccion:

1. Crear y verificar un respaldo reciente de la base destino.
2. Restaurar ese respaldo en staging y comprobar que contiene el marcador
   `2026_10_22_000000_baseline_schema_v2`.
3. Ejecutar el parche sobre staging con la base seleccionada:

   ```sh
   mysql --defaults-extra-file=RUTA_PRIVADA.cnf --database=mentematica_v2 < database/schema-v2-upgrades/2026_10_31_000001_align_schema_v2.sql
   ```

4. Verificar columnas, tablas, indices, claves foraneas y conteos de datos;
   ejecutar smoke tests de la API. Repetir el parche debe terminar sin cambios.
5. Solo despues de aprobar staging, respaldar y aplicar el mismo parche a
   produccion dentro de una ventana de mantenimiento con rollback probado.

La base antigua `mentematica` contiene registros auxiliares que no estan en la
fuente elegida `mentematica_v2`. Esos registros no se trasladaran ni eliminaran;
permanecen en su base original y requeririan un proyecto separado de fusion.

El workflow de CI prueba PHPUnit/Pint y las migraciones Laravel sobre una base
vacia; eso no sustituye el ensayo de schema v2 ni los smoke tests de staging.
El despliegue real sigue pendiente de dominios, secretos de produccion y
ejecucion de CI remoto.
