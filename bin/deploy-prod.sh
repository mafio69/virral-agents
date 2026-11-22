#!/usr/bin/env bash
set -euo pipefail

usage() {
    printf '%s\n' \
        'Użycie: ./bin/deploy-prod.sh [--dry-run] [--from-commit <sha>]' \
        'Bezpieczny deploy gałęzi master: git pull --ff-only, opcjonalny composer install,' \
        'docker compose up -d --force-recreate (app i nginx) oraz healthcheck HTTP.' \
        'Skrypt nigdy nie odczytuje ani nie modyfikuje .env.'
}

dry_run=false
from_commit=''
while [[ $# -gt 0 ]]; do
    case "$1" in
        --dry-run)
            dry_run=true
            shift
            ;;
        --from-commit)
            if [[ $# -lt 2 || -z "$2" ]]; then
                usage >&2
                exit 2
            fi
            from_commit="$2"
            shift 2
            ;;
        --help|-h)
            usage
            exit 0
            ;;
        *)
            usage >&2
            exit 2
            ;;
    esac
done

repo_root="$(cd "$(dirname "$0")/.." && pwd)"
cd "$repo_root"

for file in docker-compose.yml docker-compose.prod.yml; do
    if [[ ! -f "$file" ]]; then
        printf 'Brak pliku %s. Uruchom skrypt z repozytorium aplikacji.\n' "$file" >&2
        exit 1
    fi
done

run() {
    if "$dry_run"; then
        printf '%q ' "$@"
        printf '\n'
    else
        "$@"
    fi
}

current_branch="$(git rev-parse --abbrev-ref HEAD)"
if [[ "$current_branch" != 'master' ]]; then
    printf 'Deploy dozwolony wyłącznie na gałęzi master (obecna: %s).\n' "$current_branch" >&2
    exit 1
fi

if ! git diff --quiet || ! git diff --cached --quiet; then
    printf 'Wykryto niezacommitowane zmiany w plikach śledzonych. Przerywam deploy.\n' >&2
    exit 1
fi

previous_lock=''
if [[ -n "$from_commit" ]]; then
    previous_lock="$(git rev-parse "${from_commit}:composer.lock" 2>/dev/null || true)"
else
    previous_lock="$(git rev-parse HEAD:composer.lock 2>/dev/null || true)"
    run git fetch --prune origin master
    run git pull --ff-only origin master
fi

current_lock="$(git rev-parse HEAD:composer.lock 2>/dev/null || true)"

# Non-interactive SSH shells may not inherit rootless DOCKER_HOST automatically.
if [[ -z "${DOCKER_HOST:-}" ]]; then
    runtime_dir="${XDG_RUNTIME_DIR:-/run/user/$(id -u)}"
    if [[ -S "${runtime_dir}/docker.sock" ]]; then
        export DOCKER_HOST="unix://${runtime_dir}/docker.sock"
    fi
fi

export APP_ENV=prod
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

compose=(docker compose -f docker-compose.yml -f docker-compose.prod.yml)

if [[ ! -f vendor/autoload.php || "$previous_lock" != "$current_lock" ]]; then
    run "${compose[@]}" run --rm --no-deps --entrypoint '' app \
        composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
fi

run "${compose[@]}" up -d --force-recreate app

if ! "$dry_run"; then
    sleep "${DEPLOY_APP_WAIT:-2}"
    if ! "${compose[@]}" ps --status running --services | grep -qx app; then
        printf '%s\n' \
            'Kontener app nie wystartował (sprawdź logi migracji lub konfiguracji).' \
            'Diagnostyka: docker compose logs --tail=30 app' >&2
        exit 1
    fi
fi

run "${compose[@]}" up -d --no-deps --force-recreate nginx

app_port="${APP_PORT:-8181}"
if ! "$dry_run"; then
    published_address="$("${compose[@]}" port nginx 80 2>/dev/null || true)"
    if [[ -n "$published_address" ]]; then
        app_port="${published_address##*:}"
    fi
fi

health_url="http://127.0.0.1:${app_port}/api/ai-connections"
if "$dry_run"; then
    run curl -fsS --max-time 5 "$health_url"
else
    healthy=false
    attempts="${DEPLOY_HEALTH_ATTEMPTS:-10}"
    wait_sec="${DEPLOY_HEALTH_WAIT:-1}"
    for ((attempt = 1; attempt <= attempts; attempt++)); do
        if curl -fsS --max-time 5 "$health_url" >/dev/null 2>&1; then
            healthy=true
            break
        fi
        sleep "$wait_sec"
    done
    if ! "$healthy"; then
        printf 'Healthcheck nie powiódł się pod adresem %s.\n' "$health_url" >&2
        exit 1
    fi
fi

printf 'Deploy produkcji zakończony powodzeniem: http://127.0.0.1:%s\n' "$app_port"
