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
    installedLock = 'aaa',
    composerOk = true,
    appRunning = true,
    healthExit = 0,
    healthStatus = '200',
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
        if (installedLock !== null) {
            writeFileSync(join(repo, 'vendor', '.deploy-composer-lock'), `${installedLock}\n`);
        }
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
if [[ " $* " == *" composer install "* ]]; then
  exit ${composerOk ? 0 : 1}
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
printf "%s" "${healthStatus}"
exit ${healthExit}
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

test('deploy-prod pulls master, rebuilds app, force-recreates services in prod mode, and checks HTTP 200', () => {
    withFakeEnv(({ run, log }) => {
        const result = run();
        assert.equal(result.status, 0, result.stderr);
        const calls = readFileSync(log, 'utf8').trim().split('\n');
        assert.ok(calls.includes('git|fetch --prune origin master'));
        assert.ok(calls.includes('git|pull --ff-only origin master'));
        const buildIdx = calls.indexOf('docker|prod|0|root|compose -f docker-compose.yml -f docker-compose.prod.yml build app');
        const upAppIdx = calls.indexOf('docker|prod|0|root|compose -f docker-compose.yml -f docker-compose.prod.yml up -d --force-recreate app');
        assert.ok(buildIdx >= 0 && upAppIdx > buildIdx);
        assert.ok(!calls.some(line => line.includes('composer install')));
        assert.ok(calls.includes('docker|prod|0|root|compose -f docker-compose.yml -f docker-compose.prod.yml up -d --no-deps --force-recreate nginx'));
        assert.ok(calls.includes('curl|-sS --max-time 5 -o /dev/null -w %{http_code} http://127.0.0.1:8585/api/ai-connections'));
        assert.match(result.stdout, /http:\/\/127\.0\.0\.1:8585/);
    });
});

test('deploy-prod rebuilds app before composer install and updates installed-lock marker only on success', () => {
    withFakeEnv(({ repo, run, log }) => {
        const result = run(['--from-commit', 'oldsha']);
        assert.equal(result.status, 0, result.stderr);
        const calls = readFileSync(log, 'utf8').trim().split('\n');
        assert.ok(!calls.some(line => line.startsWith('git|pull ')));
        const buildIdx = calls.indexOf('docker|prod|0|root|compose -f docker-compose.yml -f docker-compose.prod.yml build app');
        const composerIdx = calls.findIndex(line => line.includes('run --rm --no-deps --entrypoint  app composer install --no-dev'));
        const upAppIdx = calls.indexOf('docker|prod|0|root|compose -f docker-compose.yml -f docker-compose.prod.yml up -d --force-recreate app');
        assert.ok(buildIdx >= 0 && composerIdx > buildIdx && upAppIdx > composerIdx);
        assert.equal(readFileSync(join(repo, 'vendor', '.deploy-composer-lock'), 'utf8').trim(), 'new222');
    }, { oldLock: 'old111', newLock: 'new222', installedLock: 'old111', hasVendor: true });

    // Retry scenario: checkout already points at new222, previous composer install failed so marker is still old111.
    withFakeEnv(({ repo, run, log }) => {
        const failed = run(['--from-commit', 'new222']);
        assert.equal(failed.status, 1);
        assert.equal(readFileSync(join(repo, 'vendor', '.deploy-composer-lock'), 'utf8').trim(), 'old111');
        const calls = readFileSync(log, 'utf8').trim().split('\n');
        assert.ok(calls.some(line => line.includes('composer install --no-dev')));
        assert.ok(!calls.some(line => line.includes('up -d')));
    }, { oldLock: 'new222', newLock: 'new222', installedLock: 'old111', composerOk: false, hasVendor: true });
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

test('deploy-prod fails if HTTP healthcheck does not return exact 200 with clean transfer', () => {
    withFakeEnv(({ run }) => {
        const result = run();
        assert.equal(result.status, 1);
        assert.match(result.stderr, /Healthcheck nie powiódł się/);
    }, { healthExit: 0, healthStatus: '302' });

    withFakeEnv(({ run }) => {
        const result = run();
        assert.equal(result.status, 1);
        assert.match(result.stderr, /Healthcheck nie powiódł się/);
    }, { healthExit: 18, healthStatus: '200' });

    withFakeEnv(({ run }) => {
        const result = run();
        assert.equal(result.status, 1);
        assert.match(result.stderr, /Healthcheck nie powiódł się/);
    }, { healthExit: 22, healthStatus: '500' });
});

test('deploy-prod dry-run prints planned commands without mutating state', () => {
    withFakeEnv(({ run, log }) => {
        const result = run(['--dry-run']);
        assert.equal(result.status, 0, result.stderr);
        assert.match(result.stdout, /git pull --ff-only origin master/);
        assert.match(result.stdout, /build app/);
        assert.match(result.stdout, /up -d --force-recreate app/);
        assert.match(result.stdout, /up -d --no-deps --force-recreate nginx/);
        assert.match(result.stdout, /curl -sS --max-time 5 -o \/dev\/null -w/);
        const calls = readFileSync(log, 'utf8').trim().split('\n');
        assert.ok(!calls.some(line => line.includes('build app')));
        assert.ok(!calls.some(line => line.includes('up -d')));
        assert.ok(!calls.some(line => line.startsWith('curl|')));
    });
});
