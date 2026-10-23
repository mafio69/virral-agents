#!/usr/bin/env bash
# Development only: restores admin@mafio.pl with the starter password (see docs/DEV-ADMIN.md).
# Runs inside the app container, so it uses the same APP_ENV and keys as the running application.
set -euo pipefail

cd "$(dirname "$0")/.."

if [[ "${APP_ENV:-dev}" == prod ]]; then
    printf '%s\n' 'prod operacja nie dozwolona' >&2
    exit 1
fi

exec sudo docker compose -f docker-compose.yml exec -T app php bin/restore-dev-user.php
