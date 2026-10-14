import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const html = readFileSync(new URL('../../public/ui/landing.html', import.meta.url), 'utf8');
const script = html.match(/<script>([\s\S]*?)<\/script>/)[1];

async function fixture({ blockedStorage = false, restoreCookie = false, response = { ok: true, status: 201, data: { confirmation_sent: false } } } = {}) {
    const elements = new Map([...html.matchAll(/id="([^"]+)"/g)].map(([, id]) => {
        const classes = new Set(['hidden']);
        return [id, { value: '', textContent: '', disabled: false, checked: false, className: '',
            classList: { add: (...names) => names.forEach(name => classes.add(name)),
                remove: (...names) => names.forEach(name => classes.delete(name)), contains: name => classes.has(name) } }];
    }));
    const buttons = new Map(['register', 'login'].map(name => [name, { disabled: false }]));
    const requests = [];
    const context = vm.createContext({
        document: { cookie: '', getElementById: id => elements.get(id) || null,
            querySelector: selector => buttons.get(selector.includes('register') ? 'register' : 'login') },
        localStorage: { getItem: () => { if (blockedStorage) throw new Error('Storage disabled'); return null; },
            removeItem: () => { if (blockedStorage) throw new Error('Storage disabled'); },
            setItem: () => { if (blockedStorage) throw new Error('Storage disabled'); } },
        location: { hash: '' }, window: { addEventListener() {} },
        fetch: async (url, options) => {
            requests.push({ url, options });
            const result = url.endsWith('/me')
                ? requests.length === 1 && !restoreCookie
                    ? { ok: false, status: 401, data: { error: 'No session' } }
                    : { ok: true, status: 200, data: { email: 'fixture@example.invalid' } }
                : response;
            return { ...result, text: async () => JSON.stringify(result.data) };
        },
    });
    vm.runInContext(script, context);
    await new Promise(resolve => setImmediate(resolve));
    requests.length = 0;
    return { context, elements, buttons, requests };
}

test('registration and login views open without missing DOM elements', async () => {
    const { context, elements } = await fixture();
    vm.runInContext("showForm('register')", context);
    assert.equal(elements.get('register-form').classList.contains('hidden'), false);
    vm.runInContext("showForm('login')", context);
    assert.equal(elements.get('login-form').classList.contains('hidden'), false);
    assert.equal(elements.get('register-form').classList.contains('hidden'), true);
});

test('blocked storage does not prevent registration or misreport mail failure', async () => {
    const { context, elements, buttons, requests } = await fixture({ blockedStorage: true });
    elements.get('reg-email').value = ' fixture@example.invalid ';
    elements.get('reg-password').value = 'fixture-password';
    await vm.runInContext('register()', context);
    assert.equal(requests.length, 1);
    assert.equal(JSON.parse(requests[0].options.body).email, 'fixture@example.invalid');
    assert.match(elements.get('reg-result').textContent, /Email potwierdzający nie został wysłany/);
    assert.equal(buttons.get('register').disabled, false);
});

test('login succeeds with storage disabled and loads authenticated dashboard', async () => {
    const { context, elements, buttons, requests } = await fixture({ blockedStorage: true,
        response: { ok: true, status: 200, data: { token: 'fixture-session' } } });
    elements.get('login-email').value = 'fixture@example.invalid';
    elements.get('login-password').value = 'fixture-password';
    elements.get('login-remember').checked = true;
    await vm.runInContext('login()', context);
    assert.equal(requests.length, 2);
    assert.equal(JSON.parse(requests[0].options.body).remember_me, true);
    assert.equal(elements.get('dashboard-view').classList.contains('hidden'), false);
    assert.equal(elements.get('user-email').textContent, 'fixture@example.invalid');
    assert.equal(elements.get('login-password').value, '');
    assert.equal(buttons.get('login').disabled, false);
});

test('invalid login stays on form and shows credentials error', async () => {
    const { context, elements } = await fixture({ response: { ok: false, status: 401, data: { error: 'Invalid credentials' } } });
    vm.runInContext("showForm('login')", context);
    await vm.runInContext('login()', context);
    assert.equal(elements.get('login-form').classList.contains('hidden'), false);
    assert.match(elements.get('login-result').textContent, /Nieprawidłowy email lub hasło/);
});


test('cookie session restores dashboard even when storage is blocked', async () => {
    const { elements } = await fixture({ blockedStorage: true, restoreCookie: true });
    assert.equal(elements.get('dashboard-view').classList.contains('hidden'), false);
    assert.equal(elements.get('user-email').textContent, 'fixture@example.invalid');
});

test('logout calls the server and hides dashboard even with blocked storage', async () => {
    const { context, elements, requests } = await fixture({ blockedStorage: true, restoreCookie: true,
        response: { ok: true, status: 204, data: null } });
    await vm.runInContext('logout()', context);
    assert.equal(requests[0].url, '/api/auth/logout');
    assert.equal(requests[0].options.method, 'POST');
    assert.equal(elements.get('dashboard-view').classList.contains('hidden'), true);
    assert.equal(elements.get('landing-view').classList.contains('hidden'), false);
});

test('failed logout keeps dashboard visible and reports failure', async () => {
    const { context, elements } = await fixture({ restoreCookie: true,
        response: { ok: false, status: 500, data: null } });
    await vm.runInContext('logout()', context);
    assert.equal(elements.get('dashboard-view').classList.contains('hidden'), false);
    assert.match(elements.get('auth-indicator').textContent, /Wylogowanie nie powiodło się/);
});
