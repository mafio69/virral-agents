#!/usr/bin/env bash
set -euo pipefail

usage() {
    printf '%s\n' 'Użycie: ./dev-start.sh [--dry-run]   (development)' \
        '        ./prod-start.sh [--dry-run]  (produkcja)' \
        'Uruchamiaj z głównego katalogu repozytorium. Skrypt nie odczytuje .env.'
}

mode=''
dry_run=false
for argument in "$@"; do
    case "$argument" in
        dev|prod)
            if [[ -n "$mode" ]]; then usage >&2; exit 2; fi
            mode="$argument"
            ;;
        --dry-run) dry_run=true ;;
        --help|-h) usage; exit 0 ;;
        *) usage >&2; exit 2 ;;
    esac
done
# Development by default, regardless of APP_ENV in the calling shell; prod-start.sh passes prod.
if [[ -z "$mode" ]]; then
    mode=dev
fi
for file in docker-compose.yml docker-compose.prod.yml; do
    if [[ ! -f "$file" ]]; then
        printf 'Brak pliku %s. Uruchom skrypt z głównego katalogu repozytorium.\n' "$file" >&2
        exit 1
    fi
done

# The selected mode wins over the calling shell and the environment file read by Compose.
# Mail always uses MAIL_DSN from the environment (an external SMTP, also in dev).
export APP_ENV="$mode"
# Rootless Docker maps container UID 0 to the host user; on rootful Docker UID 0 would be real root.
default_app_id=1000
default_nginx_worker=nginx
security_options="$(docker info --format '{{.SecurityOptions}}' 2>/dev/null || true)"
if [[ "$security_options" == *rootless* ]]; then
    default_app_id=0
    default_nginx_worker=root
fi
export APP_UID="${APP_UID:-$default_app_id}"
export APP_GID="${APP_GID:-$default_app_id}"
export NGINX_WORKER_USER="${NGINX_WORKER_USER:-$default_nginx_worker}"
# Rootless Docker: no sudo needed — the user daemon socket is used directly (DOCKER_HOST or rootless context).
compose=(docker compose -f docker-compose.yml)
if [[ "$mode" == prod ]]; then compose+=(-f docker-compose.prod.yml); fi
run() {
    if "$dry_run"; then
        printf '%q ' "$@"
        printf '\n'
    else
        "$@"
    fi
}

printf 'Tryb: %s\n' "$mode"
run "${compose[@]}" up -d app
if ! "$dry_run"; then
    # The app exits early when configuration or migrations fail; nginx would then retry a missing host.
    sleep "${DEV_START_APP_WAIT:-2}"
    if ! "${compose[@]}" ps --status running --services | grep -qx app; then
        printf '%s\n' 'Kontener app nie działa (najczęściej błąd składni pliku środowiska lub migracji).' \
            'Kod przyczyny: docker compose logs --tail=20 app' >&2
        exit 1
    fi
fi
# Do not recreate nginx or change its ports/volumes. Refresh the upstream after app starts.
run "${compose[@]}" up -d --no-deps --no-recreate nginx
if "$dry_run"; then
    run "${compose[@]}" exec -T nginx nginx -s reload
else
    reloaded=false
    for attempt in {1..10}; do
        if "${compose[@]}" exec -T nginx nginx -s reload; then
            reloaded=true
            break
        fi
        sleep 1
    done
    if ! "$reloaded"; then
        printf '%s\n' 'Nie udało się przeładować Nginxa. Kontenery nie zostały usunięte.' >&2
        exit 1
    fi
fi
app_port="${APP_HOST_PORT:-8181}"
if ! "$dry_run"; then
    # Compose may take the port from the environment file, so ask for the published one.
    published_address="$("${compose[@]}" port nginx 80 2>/dev/null || true)"
    if [[ -n "$published_address" ]]; then
        app_port="${published_address##*:}"
    fi
fi
printf 'Aplikacja: http://localhost:%s\n' "$app_port"
