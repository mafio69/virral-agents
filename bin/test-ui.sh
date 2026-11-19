#!/bin/sh
# Testy UI bez izolacji procesów (w ograniczonym środowisku agenta izolacja zawodzi).
# Node >= 23.6 (CI: 24) zna flagę --test-isolation; Node 22 tylko --experimental-test-isolation.
set -eu

if node -e 'const [major, minor] = process.versions.node.split(".").map(Number); process.exit(major > 23 || (major === 23 && minor >= 6) ? 0 : 1)'; then
    flag=--test-isolation=none
else
    flag=--experimental-test-isolation=none
fi

exec node --test "$flag" tests/ui/*.test.mjs
