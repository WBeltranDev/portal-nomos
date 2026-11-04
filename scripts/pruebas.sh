#!/usr/bin/env bash
# Ejecuta las pruebas automatizadas de SERAG sin tocar la base de trabajo.
#
# - Base de pruebas: contenedor MariaDB 10.11 aparte (misma familia que
#   producción), con los datos en memoria: se borra al detenerlo.
# - PHP: imagen de desarrollo construida con la etapa php-base del Dockerfile.
#
# Uso:   scripts/pruebas.sh                  # toda la suite
#        scripts/pruebas.sh --filter=Escala  # argumentos extra para `php artisan test`
set -euo pipefail

RAIZ="$(cd "$(dirname "$0")/.." && pwd)"
RED="${SERAG_RED:-portal-nomos_default}"
IMAGEN_DEV="serag-dev:local"
CONTENEDOR_BD="serag-test-db"
BASE_PRUEBAS="sistemas_evaluacion_test"

leer_env() {
    grep -E "^$1=" "$RAIZ/.env" | tail -1 | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//' -e "s/^'//" -e "s/'$//"
}

DB_USUARIO="$(leer_env DB_USERNAME)"
DB_CLAVE="$(leer_env DB_PASSWORD)"

case "$BASE_PRUEBAS" in
    *_test) ;;
    *) echo "La base de pruebas debe terminar en _test." >&2; exit 1 ;;
esac

if ! docker image inspect "$IMAGEN_DEV" >/dev/null 2>&1; then
    echo "> Construyendo la imagen de desarrollo ($IMAGEN_DEV)..."
    docker build --target php-base -t "$IMAGEN_DEV" "$RAIZ"
fi

if ! docker ps --format '{{.Names}}' | grep -qx "$CONTENEDOR_BD"; then
    docker rm -f "$CONTENEDOR_BD" >/dev/null 2>&1 || true
    echo "> Levantando la base de pruebas MariaDB 10.11 ($CONTENEDOR_BD)..."
    docker run -d --name "$CONTENEDOR_BD" --network "$RED" \
        --tmpfs /var/lib/mysql:rw \
        -e MARIADB_RANDOM_ROOT_PASSWORD=1 \
        -e MARIADB_DATABASE="$BASE_PRUEBAS" \
        -e MARIADB_USER="$DB_USUARIO" \
        -e MARIADB_PASSWORD="$DB_CLAVE" \
        mariadb:10.11 \
        --character-set-server=utf8mb4 --collation-server=utf8mb4_unicode_ci >/dev/null

    for _ in $(seq 1 60); do
        if docker exec "$CONTENEDOR_BD" healthcheck.sh --connect --innodb_initialized >/dev/null 2>&1; then
            break
        fi
        sleep 1
    done
fi

# El código se monta desde el repositorio; DB_HOST y DB_DATABASE se fuerzan
# hacia la base de pruebas (las variables de entorno ganan sobre el .env).
ejecutar() {
    # label=disable: en sistemas con SELinux (Fedora) permite leer el
    # repositorio montado sin cambiar las etiquetas de seguridad de los archivos.
    docker run --rm -t --network "$RED" \
        --security-opt label=disable \
        -v "$RAIZ":/var/www/html -w /var/www/html \
        -v "$RAIZ/docker/php/conf.d/uploads.ini":/usr/local/etc/php/conf.d/uploads.ini:ro \
        --user "$(id -u):$(id -g)" \
        -e HOME=/tmp -e COMPOSER_HOME=/tmp/composer \
        -e APP_ENV=testing \
        -e DB_CONNECTION=mysql -e DB_HOST="$CONTENEDOR_BD" -e DB_PORT=3306 \
        -e DB_DATABASE="$BASE_PRUEBAS" \
        "$IMAGEN_DEV" "$@"
}

if [ ! -f "$RAIZ/vendor/bin/phpunit" ]; then
    echo "> Instalando dependencias de desarrollo (composer)..."
    ejecutar composer install --no-interaction --prefer-dist --no-progress
fi

echo "> Creando el esquema en $BASE_PRUEBAS..."
ejecutar php artisan migrate:fresh --force

echo "> Ejecutando las pruebas..."
ejecutar php artisan test "$@"
