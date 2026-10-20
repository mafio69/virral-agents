import test from 'node:test';
import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import { mkdtempSync, writeFileSync, readFileSync, rmSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { join } from 'node:path';

const root = fileURLToPath(new URL('../../', import.meta.url));
const script = join(root, 'dev-start.sh');

function preview(args = [], environment = {}) {
    return spawnSync('bash', [script, ...args, '--dry-run'], {
        cwd: root, encoding: 'utf8', env: { PATH: '/usr/bin:/bin', ...environment },
    });
}

function withFakeSudo(body, exitCode = 0, appRunning = true) {
    const directory = mkdtempSync(join(root, 'tests', 'start-fixture-'));
    const log = join(directory, 'calls');
    try {
        // Records arguments and the APP_ENV that sudo would preserve; `ps` reports the running services.
        const running = appRunning ? 'printf "app\\n"' : 'true';
        writeFileSync(join(directory, 'sudo'), `#!/bin/bash\nprintf "%s|%s\\n" "$APP_ENV" "$*" >> "$START_SCRIPT_LOG"\n[[ " $* " == *" ps "* ]] && ${running}\nexit ${exitCode}\n`, { mode: 0o755 });
        return body(directory, log);
    } finally {
        rmSync(directory, { recursive: true, force: true });
    }
}

test('exported APP_ENV dev starts only the application stack and every Docker call uses sudo', () => {
    const result = preview([], { APP_ENV: 'dev' });
    assert.equal(result.status, 0, result.stderr);
    assert.match(result.stdout, /Tryb: dev/);
    assert.match(result.stdout, /sudo --preserve-env=APP_ENV docker compose -f docker-compose.yml up -d app/);
    const commands = result.stdout.split('\n').filter(line => line.includes('docker compose'));
    assert.ok(commands.length > 0);
    assert.ok(commands.every(line => line.startsWith('sudo --preserve-env=APP_ENV docker compose -f docker-compose.yml ')));
    assert.doesNotMatch(result.stdout, /mailpit|docker-compose\.dev\.yml|--profile|--force-recreate| down | rm /);
});

test('missing or non-dev APP_ENV selects prod with the production override', () => {
    for (const environment of [{}, { APP_ENV: 'prod' }, { APP_ENV: 'staging' }]) {
        const result = preview([], environment);
        assert.equal(result.status, 0, result.stderr);
        assert.match(result.stdout, /Tryb: prod/);
        assert.match(result.stdout, /-f docker-compose.yml -f docker-compose.prod.yml up -d app/);
        assert.doesNotMatch(result.stdout, /mailpit|--profile/);
    }
});

test('explicit mode overrides the calling shell and invalid arguments cannot invoke Docker', () => {
    assert.match(preview(['prod'], { APP_ENV: 'dev' }).stdout, /Tryb: prod/);
    assert.match(preview(['dev'], { APP_ENV: 'prod' }).stdout, /Tryb: dev/);
    for (const args of [['deev'], ['dev', 'prod'], ['--unknown']]) {
        const result = preview(args);
        assert.equal(result.status, 2);
        assert.doesNotMatch(result.stdout, /sudo/);
    }
});

test('selected mode is exported to Compose even when the shell says otherwise', () => {
    withFakeSudo((directory, log) => {
        const result = spawnSync('bash', [script, 'dev'], {
            cwd: root, encoding: 'utf8',
            env: { PATH: directory + ':/usr/bin:/bin', START_SCRIPT_LOG: log, APP_ENV: 'prod', DEV_START_APP_WAIT: '0' },
        });
        assert.equal(result.status, 0, result.stderr);
        const calls = readFileSync(log, 'utf8').trim().split('\n');
        assert.ok(calls.every(line => line.startsWith('dev|--preserve-env=APP_ENV docker compose')));
    });
});

test('startup failure stops the script before touching nginx', () => {
    withFakeSudo((directory, log) => {
        const result = spawnSync('bash', [script, 'dev'], {
            cwd: root, encoding: 'utf8',
            env: { PATH: directory + ':/usr/bin:/bin', START_SCRIPT_LOG: log, DEV_START_APP_WAIT: '0' },
        });
        assert.equal(result.status, 17);
        const calls = readFileSync(log, 'utf8').trim().split('\n');
        assert.equal(calls.length, 1);
        assert.match(calls[0], /up -d app$/);
        assert.doesNotMatch(calls[0], /nginx/);
    }, 17);
});

test('successful production start preserves nginx and reloads it', () => {
    withFakeSudo((directory, log) => {
        const result = spawnSync('bash', [script, 'prod'], {
            cwd: root, encoding: 'utf8',
            env: { PATH: directory + ':/usr/bin:/bin', START_SCRIPT_LOG: log, DEV_START_APP_WAIT: '0' },
        });
        assert.equal(result.status, 0, result.stderr);
        const calls = readFileSync(log, 'utf8').trim().split('\n');
        assert.equal(calls.length, 4);
        assert.match(calls[0], /^prod\|.*-f docker-compose.prod.yml up -d app$/);
        assert.match(calls[1], /ps --status running --services$/);
        assert.match(calls[2], /up -d --no-deps --no-recreate nginx$/);
        assert.match(calls[3], /exec -T nginx nginx -s reload$/);
        assert.doesNotMatch(result.stdout, /Mailpit/);
    });
});

test('an app that exits after start stops the script before nginx with a clear hint', () => {
    withFakeSudo((directory, log) => {
        const result = spawnSync('bash', [script, 'dev'], {
            cwd: root, encoding: 'utf8',
            env: { PATH: directory + ':/usr/bin:/bin', START_SCRIPT_LOG: log, DEV_START_APP_WAIT: '0' },
        });
        assert.equal(result.status, 1);
        assert.match(result.stderr, /Kontener app nie działa/);
        const calls = readFileSync(log, 'utf8').trim().split('\n');
        assert.equal(calls.length, 2);
        assert.doesNotMatch(calls.join('\n'), /nginx/);
    }, 0, false);
});
