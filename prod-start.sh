#!/usr/bin/env bash
set -euo pipefail

# Production start: the shared start logic lives in dev-start.sh.
exec "$(dirname "$0")/dev-start.sh" prod "$@"
