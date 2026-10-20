#!/usr/bin/env bash
set -euo pipefail

usage() {
    printf '%s\n' 'Użycie: ./dev-start.sh [dev|prod] [--dry-run]' \
        'Bez argumentu: APP_ENV=dev wybiera dev; pozostałe wartości wybierają prod.' \
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
if [[ -z "$mode" ]]; then
    if [[ "${APP_ENV:-}" == dev ]]; then mode=dev; else mode=prod; fi
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
compose=(sudo --preserve-env=APP_ENV docker compose -f docker-compose.yml)
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
printf '%s\n' 'Aplikacja: http://localhost:8181'
