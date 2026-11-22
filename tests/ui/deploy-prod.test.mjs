import test from 'node:test';
import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import { mkdtempSync, mkdirSync, writeFileSync, readFileSync, rmSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { join } from 'node:path';

const root = fileURLToPath(new URL('../../', import.meta.url));
const script = join(root, 'bin', 'deploy-prod.sh');

function withFakeEnv(body, {
    branch = 'master',
    dirty = false,
    oldLock = 'aaa',
    newLock = 'aaa',
    appRunning = true,
    healthOk = true,
    rootless = true,
    hasVendor = true,
} = {}) {
    const dir = mkdtempSync(join(root, 'tests', 'deploy-fixture-'));
    const bin = join(dir, 'bin-mock');
    const repo = join(dir, 'repo');
    const log = join(dir, 'calls.log');
    mkdirSync(bin);
    mkdirSync(join(repo, 'bin'), { recursive: true });
    writeFileSync(join(repo, 'docker-compose.yml'), 'services: {}\n');
    writeFileSync(join(repo, 'docker-compose.prod.yml'), 'services: {}\n');
    writeFileSync(join(repo, 'bin', 'deploy-prod.sh'), readFileSync(script), { mode: 0o755 });
    if (hasVendor) {
        mkdirSync(join(repo, 'vendor'));
        writeFileSync(join(repo, 'vendor', 'autoload.php'), '<?php\n');
    }

    const gitState = join(dir, 'git-pulled');
    writeFileSync(join(bin, 'git'), `#!/bin/bash
printf "git|%s\\n" "$*" >> "${log}"
case "$*" in
  "rev-parse --abbrev-ref HEAD")
    printf "%s\\n" "${branch}"
    ;;
  "diff --quiet"|"diff --cached --quiet")
    exit ${dirty ? 1 : 0}
    ;;
  "pull --ff-only origin master")
    touch "${gitState}"
    ;;
  "rev-parse HEAD:composer.lock")
    if [[ -f "${gitState}" || "${oldLock}" != "${newLock}" ]]; then
      printf "%s\\n" "${newLock}"
    else
      printf "%s\\n" "${oldLock}"
    fi
    ;;
  "rev-parse oldsha:composer.lock")
    printf "%s\\n" "${oldLock}"
    ;;
esac
exit 0
`, { mode: 0o755 });

    writeFileSync(join(bin, 'docker'), `#!/bin/bash
printf "docker|%s|%s|%s|%s\\n" "\${APP_ENV:-}" "\${APP_UID:-}" "\${NGINX_WORKER_USER:-}" "$*" >> "${log}"
if [[ "$*" == info* ]]; then
  ${rootless ? 'printf "[name=rootless]\\n"' : 'printf "[]\\n"'}
  exit 0
fi
if [[ " $* " == *" ps "* ]]; then
  ${appRunning ? 'printf "app\\n"' : 'true'}
  exit 0
fi
if [[ " $* " == *" port nginx 80"* ]]; then
  printf "0.0.0.0:8585\\n"
  exit 0
fi
exit 0
`, { mode: 0o755 });

    writeFileSync(join(bin, 'curl'), `#!/bin/bash
printf "curl|%s\\n" "$*" >> "${log}"
exit ${healthOk ? 0 : 22}
`, { mode: 0o755 });

    try {
        return body({
            repo,
            log,
            run: (args = []) => spawnSync('bash', [join(repo, 'bin', 'deploy-prod.sh'), ...args], {
                cwd: repo,
                encoding: 'utf8',
                env: {
                    PATH: `${bin}:/usr/bin:/bin`,
                    DEPLOY_APP_WAIT: '0',
                    DEPLOY_HEALTH_ATTEMPTS: '2',
                    DEPLOY_HEALTH_WAIT: '0',
                },
            }),
        });
    } finally {
        rmSync(dir, { recursive: true, force: true });
    }
}

test('deploy-prod refuses to run outside master or with dirty tracked files', () => {
    withFakeEnv(({ run }) => {
        const result = run();
        assert.equal(result.status, 1);
        assert.match(result.stderr, /wyłącznie na gałęzi master/);
    }, { branch: 'develop' });

    withFakeEnv(({ run }) => {
        const result = run();
        assert.equal(result.status, 1);
        assert.match(result.stderr, /niezacommitowane zmiany/);
    }, { dirty: true });
});

test('deploy-prod pulls master, force-recreates app and nginx in prod mode, and checks health', () => {
    withFakeEnv(({ run, log }) => {
        const result = run();
        assert.equal(result.status, 0, result.stderr);
        const calls = readFileSync(log, 'utf8').trim().split('\n');
        assert.ok(calls.includes('git|fetch --prune origin master'));
        assert.ok(calls.includes('git|pull --ff-only origin master'));
        assert.ok(!calls.some(line => line.includes('composer install')));
        assert.ok(calls.includes('docker|prod|0|root|compose -f docker-compose.yml -f docker-compose.prod.yml up -d --force-recreate app'));
        assert.ok(calls.includes('docker|prod|0|root|compose -f docker-compose.yml -f docker-compose.prod.yml up -d --no-deps --force-recreate nginx'));
        assert.ok(calls.includes('curl|-fsS --max-time 5 http://127.0.0.1:8585/api/ai-connections'));
        assert.match(result.stdout, /http:\/\/127\.0\.0\.1:8585/);
    });
});

test('deploy-prod runs composer install inside app image when composer.lock changed', () => {
    withFakeEnv(({ run, log }) => {
        const result = run(['--from-commit', 'oldsha']);
        assert.equal(result.status, 0, result.stderr);
        const calls = readFileSync(log, 'utf8').trim().split('\n');
        assert.ok(!calls.some(line => line.startsWith('git|pull ')));
        assert.ok(calls.some(line => line.includes('run --rm --no-deps --entrypoint  app composer install --no-dev')));
    }, { oldLock: 'old111', newLock: 'new222', hasVendor: true });
});

test('deploy-prod stops before recreating nginx if app fails to stay running', () => {
    withFakeEnv(({ run, log }) => {
        const result = run();
        assert.equal(result.status, 1);
        assert.match(result.stderr, /Kontener app nie wystartował/);
        const calls = readFileSync(log, 'utf8').trim().split('\n');
        assert.ok(!calls.some(line => line.includes('nginx')));
    }, { appRunning: false });
});

test('deploy-prod fails if HTTP healthcheck does not return 200', () => {
    withFakeEnv(({ run }) => {
        const result = run();
        assert.equal(result.status, 1);
        assert.match(result.stderr, /Healthcheck nie powiódł się/);
    }, { healthOk: false });
});

test('deploy-prod dry-run prints planned commands without mutating state', () => {
    withFakeEnv(({ run, log }) => {
        const result = run(['--dry-run']);
        assert.equal(result.status, 0, result.stderr);
        assert.match(result.stdout, /git pull --ff-only origin master/);
        assert.match(result.stdout, /up -d --force-recreate app/);
        assert.match(result.stdout, /up -d --no-deps --force-recreate nginx/);
        assert.match(result.stdout, /curl -fsS --max-time 5/);
        const calls = readFileSync(log, 'utf8').trim().split('\n');
        assert.ok(!calls.some(line => line.includes('up -d')));
        assert.ok(!calls.some(line => line.startsWith('curl|')));
    });
});
