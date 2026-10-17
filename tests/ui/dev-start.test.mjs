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

test('exported APP_ENV dev selects local SMTP stack and every Docker call uses sudo', () => {
    const result = preview([], { APP_ENV: 'dev' });
    assert.equal(result.status, 0, result.stderr);
    assert.match(result.stdout, /docker-compose.dev.yml --profile dev up -d mailpit app/);
    const commands = result.stdout.split('\n').filter(line => line.includes('docker compose'));
    assert.ok(commands.length > 0);
    assert.ok(commands.every(line => line.startsWith('sudo docker compose ')));
    assert.doesNotMatch(result.stdout, /--force-recreate| down | rm /);
});

test('missing or non-dev APP_ENV selects prod and stops only the local mailbox', () => {
    for (const environment of [{}, { APP_ENV: 'prod' }, { APP_ENV: 'staging' }]) {
        const result = preview([], environment);
        assert.equal(result.status, 0, result.stderr);
        assert.match(result.stdout, /docker-compose.prod.yml up -d app/);
        assert.match(result.stdout, /--profile dev stop mailpit/);
        assert.doesNotMatch(result.stdout, /up -d mailpit/);
    }
});

test('explicit mode overrides the calling shell and invalid arguments cannot invoke Docker', () => {
    assert.match(preview(['prod'], { APP_ENV: 'dev' }).stdout, /Tryb: prod/);
    assert.match(preview(['dev'], { APP_ENV: 'prod' }).stdout, /Tryb: dev/);
    for (const args of [['deev'], ['dev', 'prod'], ['--unknown']]) {
        const result = preview(args);
        assert.equal(result.status, 2);
        assert.doesNotMatch(result.stdout, /sudo docker/);
    }
});

test('startup failure stops the script before touching nginx', () => {
    const directory = mkdtempSync(join(root, 'tests', 'start-fixture-'));
    const log = join(directory, 'calls');
    try {
        writeFileSync(join(directory, 'sudo'), '#!/bin/bash\nprintf "%s\n" "$*" >> "$START_SCRIPT_LOG"\nexit 17\n', { mode: 0o755 });
        const result = spawnSync('bash', [script, 'dev'], {
            cwd: root, encoding: 'utf8',
            env: { PATH: directory + ':/usr/bin:/bin', START_SCRIPT_LOG: log },
        });
        assert.equal(result.status, 17);
        const calls = readFileSync(log, 'utf8');
        assert.equal(calls.trim().split('\n').length, 1);
        assert.match(calls, /up -d mailpit app/);
        assert.doesNotMatch(calls, /nginx/);
    } finally {
        rmSync(directory, { recursive: true, force: true });
    }
});

test('successful production start preserves nginx and reloads it before stopping Mailpit', () => {
    const directory = mkdtempSync(join(root, 'tests', 'start-fixture-'));
    const log = join(directory, 'calls');
    try {
        writeFileSync(join(directory, 'sudo'), '#!/bin/bash\nprintf "%s\n" "$*" >> "$START_SCRIPT_LOG"\n', { mode: 0o755 });
        const result = spawnSync('bash', [script, 'prod'], {
            cwd: root, encoding: 'utf8',
            env: { PATH: directory + ':/usr/bin:/bin', START_SCRIPT_LOG: log },
        });
        assert.equal(result.status, 0, result.stderr);
        const calls = readFileSync(log, 'utf8').trim().split('\n');
        assert.equal(calls.length, 4);
        assert.match(calls[0], /up -d app$/);
        assert.match(calls[1], /up -d --no-deps --no-recreate nginx$/);
        assert.match(calls[2], /exec -T nginx nginx -s reload$/);
        assert.match(calls[3], /--profile dev stop mailpit$/);
    } finally {
        rmSync(directory, { recursive: true, force: true });
    }
});
