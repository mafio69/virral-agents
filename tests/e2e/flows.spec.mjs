import { test, expect } from '@playwright/test';
import { fileURLToPath } from 'node:url';

test.describe.configure({ mode: 'serial' });
const email = `e2e-${Date.now()}@example.invalid`;
const password = 'Synthetic-E2E-password-42';
const mailbox = process.env.E2E_MAILBOX_URL || 'http://mail-trap.dom';
const dependencies = 'http://127.0.0.1:18182';
const observations = new WeakMap();

test.beforeEach(async ({ page }) => {
    const state = { external: 0, pageErrors: 0 };
    observations.set(page, state);
    page.on('pageerror', () => state.pageErrors++);
    await page.route('**/*', route => {
        const url = new URL(route.request().url());
        if (!['127.0.0.1', 'localhost'].includes(url.hostname)) {
            state.external++;
            return route.abort();
        }
        return route.continue();
    });
});

test.afterEach(async ({ page }) => {
    const state = observations.get(page);
    expect(state.pageErrors, 'No uncaught browser JavaScript errors').toBe(0);
    expect(state.external, 'No external browser requests').toBe(0);
});

async function login(page) {
    await page.goto('/#login');
    await page.locator('#login-email').fill(email);
    await page.locator('#login-password').fill(password);
    await page.locator('#login-password').press('Enter');
    await expect(page.locator('#dashboard-view')).toBeVisible();
}

async function openChat(page) {
    await login(page);
    await page.goto('/chat');
    await expect(page.locator('#chat-input')).toBeEnabled();
    await expect(page.locator('#chat-connection')).toHaveValue('google');
}

async function counters(request) {
    return (await request.get(`${dependencies}/counts`)).json();
}

async function search(page, query, allow = true) {
    await page.locator('#search-query').fill(query);
    await page.locator('#search-send').click();
    await expect(page.locator('#research-dialog')).toBeVisible();
    const decision = page.getByRole('button', { name: allow ? 'Allow — zezwól' : 'Deny — odmów', exact: true });
    await expect(decision).toBeVisible();
    await decision.click();
    await expect(decision).toHaveCount(0);
}

test('account: registration, local SMTP, confirmation, login, reload and logout', async ({ page, request }) => {
    await page.goto('/');
    await page.getByRole('button', { name: 'Rejestracja', exact: true }).click();
    await page.locator('#reg-email').fill(email);
    await page.locator('#reg-password').fill('123456789');
    await page.locator('#register-form button[type=submit]').click();
    // Native validation prevents sending the short password.
    expect(await page.locator('#reg-password').evaluate(input => input.checkValidity())).toBe(false);
    await page.locator('#reg-password').fill(password);
    await page.locator('#reg-password').press('Enter');
    await expect(page.locator('#reg-result')).toContainText('Sprawdź email');

    await page.locator('#register-form').getByRole('button', { name: 'Zaloguj się', exact: true }).click();
    await page.locator('#login-password').fill(password);
    await page.locator('#login-password').press('Enter');
    await expect(page.locator('#login-result')).toContainText('Potwierdź adres email');
    expect((await page.request.get('/api/auth/me')).status()).toBe(401);

    let message;
    await expect.poll(async () => {
        const list = await (await request.get(`${mailbox}/api/v1/messages`)).json();
        message = list.messages?.find(item => item.To?.some(recipient => recipient.Address === email));
        return Boolean(message);
    }).toBe(true);
    const detail = await (await request.get(`${mailbox}/api/v1/message/${message.ID}`)).json();
    const link = detail.HTML.match(/href="([^"]+)"/)[1];
    const parsed = new URL(link);
    expect(parsed.origin).toBe('http://127.0.0.1:18181');
    const confirmation = await page.goto(link);
    expect(confirmation.status()).toBe(200);
    await login(page);
    await expect(page.locator('#user-email')).toHaveText(email);
    await page.reload();
    await expect(page.locator('#dashboard-view')).toBeVisible();
    // Clear only browser localStorage; the real HttpOnly session must still work.
    await page.evaluate(() => localStorage.removeItem('token'));
    await page.reload();
    await expect(page.locator('#dashboard-view')).toBeVisible();
    await page.getByRole('link', { name: 'Wyloguj się' }).click();
    await expect(page.locator('#auth-indicator')).toHaveText('Niezalogowany');
    expect((await page.request.get('/api/auth/me')).status()).toBe(401);
    expect((await page.request.get('/api/chats')).status()).toBe(401);
});

test('chat: native provider transport, one submission and saved history', async ({ page }) => {
    await openChat(page);
    await page.locator('#research-mode').uncheck();
    let sends = 0;
    page.on('request', request => { if (request.method() === 'POST' && /\/messages$/.test(new URL(request.url()).pathname)) sends++; });
    await page.locator('#chat-input').fill('Wiadomość E2E');
    await page.locator('#chat-input').press('Enter');
    await expect(page.locator('#thread')).toContainText('Odpowiedź E2E.');
    expect(sends).toBe(1);
    await page.reload();
    await page.locator('#chat-list .chat-item').first().click();
    await expect(page.locator('#thread')).toContainText('Wiadomość E2E');
    await expect(page.locator('#thread')).toContainText('Odpowiedź E2E.');
});

test('research: ASK denial, selection, partial fetch failure, synthesis and reload', async ({ page, request }) => {
    await openChat(page);
    const before = await counters(request);
    await search(page, 'publiczne pierwsze E2E', false);
    expect((await counters(request)).search).toBe(before.search);
    await page.locator('#research-close').click();
    await search(page, 'publiczne pierwsze E2E');
    await expect(page.locator('.research-result input')).toHaveCount(4);
    await page.getByRole('checkbox', { name: 'Dodaj źródło: Źródło a2', exact: true }).uncheck();
    await expect(page.locator('#research-summary')).toContainText('(3/20)');
    await page.locator('#research-continue').click();
    await expect(page.locator('#research-dialog')).not.toBeVisible();
    await expect(page.locator('#thread')).toContainText('Opracowanie E2E: Treść E2E a1, Treść E2E a3, Niedostępne źródło');
    await expect(page.locator('#thread')).not.toContainText('Treść E2E a2');
    const after = await counters(request);
    expect(after.fetch.slice(before.fetch.length)).toEqual(['https://example.org/a1', 'https://example.org/a3', 'https://example.org/a4']);
    await page.reload();
    await page.locator('#chat-list .chat-item').first().click();
    await page.locator('#research-open-side').click();
    await expect(page.locator('#research-summary')).toContainText('(3/20)');
    await expect(page.locator('#research-body')).toContainText('Treść pobrana i zachowana');
    await page.keyboard.press('Escape');
    await search(page, 'drugie E2E');
    await expect(page.locator('#research-summary')).toContainText('(5/20)');
    await page.screenshot({ path: fileURLToPath(new URL('./runtime/research-e2e.png', import.meta.url)), fullPage: true });
});

test('MCP: empty results and provider error remain distinct in the browser', async ({ page }) => {
    await openChat(page);
    await search(page, 'empty E2E');
    await expect(page.locator('.research-result')).toHaveCount(0);
    await expect(page.locator('#chat-status')).toContainText('Brak użytecznych wyników');
    await page.locator('#research-close').click();
    await search(page, 'error E2E');
    await expect(page.locator('#research-body')).toContainText('Nie udało się wykonać operacji');
    await expect(page.locator('#research-body')).toContainText('provider_response');
});
