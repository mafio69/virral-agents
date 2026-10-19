import test from 'node:test';
import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import { mkdtempSync, writeFileSync, readFileSync, rmSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { join } from 'node:path';

const root = fileURLToPath(new URL('../../', import.meta.url));

for (const failure of [false, true]) {
    test(`app entrypoint ${failure ? 'stops on migration failure' : 'migrates before starting FPM'}`, () => {
        const directory = mkdtempSync(join(root, 'tests', 'entrypoint-fixture-'));
        const log = join(directory, 'calls');
        try {
            writeFileSync(join(directory, 'php'), '#!/bin/sh\nprintf "migrate:%s\\n" "$*" >> "$START_LOG"\nexit ' + (failure ? '17' : '0') + '\n', { mode: 0o755 });
            writeFileSync(join(directory, 'php-fpm'), '#!/bin/sh\nprintf "fpm\\n" >> "$START_LOG"\n', { mode: 0o755 });
            const result = spawnSync('sh', ['docker/start-app.sh'], {
                cwd: root, encoding: 'utf8', env: { PATH: directory + ':/usr/bin:/bin', START_LOG: log },
            });
            assert.equal(result.status, failure ? 17 : 0, result.stderr);
            assert.equal(readFileSync(log, 'utf8'), 'migrate:bin/migrate.php\n' + (failure ? '' : 'fpm\n'));
        } finally {
            rmSync(directory, { recursive: true, force: true });
        }
    });
}
