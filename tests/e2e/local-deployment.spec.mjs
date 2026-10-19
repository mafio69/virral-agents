import { test, expect } from '@playwright/test';

test('running production installation: public page, private APIs and dev panel boundaries', async ({ request }) => {
    expect((await request.get('/')).status()).toBe(200);
    for (const path of ['/api/auth/me', '/api/chats', '/api/tasks', '/api/research', '/api/search/status']) {
        expect((await request.get(path)).status(), path).toBe(401);
    }
    expect((await request.get('/test-api')).status()).toBe(404);
    expect((await request.get('/ui/index.html')).status()).toBe(404);
});
