import test from 'node:test';
import assert from 'node:assert/strict';
import { approveSearch, canSearch, bindSearchControls } from '../../public/ui/chat/chat-search.js';
import { ChatApi } from '../../public/ui/chat/chat-api.js';
import { ChatController } from '../../public/ui/chat/chat-controller.js';
import { chatFixture } from './chat-fixture.mjs';

test('ASK shows exact query and destinations every time; refusal grants nothing', () => {
    const prompts = [];
    const confirm = text => { prompts.push(text); return prompts.length === 1; };
    assert.deepEqual(approveSearch(' public query ', confirm), { engine: 'mcp', query: 'public query', approved: true, max_calls: 1 });
    assert.equal(approveSearch('public query', confirm), null);
    assert.equal(prompts.length, 2);
    assert.match(prompts[0], /public query/);
    assert.match(prompts[0], /SearXNG.*Perplexity/);
    assert.match(prompts[0], /dostawcy AI/);
    assert.equal(approveSearch('  ', () => { throw Error('Must not ask'); }), null);
});

test('approval belongs to one message and the next send has no search permission', async () => {
    const permissions = [];
    let next = 1;
    const controller = new ChatController(chatFixture({
        createChat: async () => ({ id: 1, model: 'fixture-model' }),
        sendMessage: async (_id, content, _images, search) => {
            permissions.push(search);
            return [{ id: next++, role: 'user', content }, { id: next++, role: 'assistant', content: 'Answer' }];
        },
    }));
    await controller.load();
    controller.setDraft('First');
    const approval = approveSearch('public query', () => true);
    assert.equal(await controller.send(approval), true);
    controller.setDraft('Second');
    assert.equal(await controller.send(), true);
    assert.deepEqual(permissions, [approval, undefined]);
});

test('transport sends only explicitly supplied search permission', async () => {
    const bodies = [];
    const api = new ChatApi({ fetcher: async (_url, options) => {
        bodies.push(JSON.parse(options.body));
        return { ok: true, json: async () => [{ id: 1, role: 'user', content: 'Question' }, { id: 2, role: 'assistant', content: 'Answer' }] };
    } });
    await api.sendMessage(1, 'Question');
    const approval = approveSearch('public query', () => true);
    await api.sendMessage(1, 'Question', [], approval);
    assert.equal(Object.hasOwn(bodies[0], 'search'), false);
    assert.deepEqual(bodies[1].search, approval);
});

test('query alone enables search; authentication, model, busy state and uncertainty still block it', () => {
    const state = { authenticated: true, selectedModel: 'fixture', draft: '', images: [] };
    assert.equal(canSearch(state, 'Llama ai rozwój'), true);
    for (const changes of [{ authenticated: false }, { selectedModel: '' }, { busy: true }, { loading: true }, { modelsLoading: true }, { deliveryUncertain: true }]) {
        assert.equal(canSearch({ ...state, ...changes }, 'Llama ai rozwój'), false);
    }
    assert.equal(canSearch(state, '  '), false);
    assert.equal(canSearch(state, 'ą'.repeat(501)), false);
    assert.equal(canSearch(state, 'query', true), false);
});

test('actual search handlers accept a query-only draft and denial sends nothing', async () => {
    const requests = [];
    const api = new ChatApi({ fetcher: async (url, options) => {
        requests.push({ url, body: options.body ? JSON.parse(options.body) : null });
        const data = url.endsWith('/ai-connections') ? [{ id: 'ovh', name: 'OVH' }]
            : url.includes('/ai-models') ? [{ id: 'fixture-model' }]
            : url.endsWith('/messages') ? [{ id: 1, role: 'user', content: 'Llama ai rozwój' }, { id: 2, role: 'assistant', content: 'Fixture answer' }]
            : options.method === 'POST' ? { id: 1, model: 'fixture-model' } : [];
        return { ok: true, json: async () => data };
    } });
    const controller = new ChatController(api);
    await controller.load();
    const node = () => ({ value: '', disabled: true, handlers: {}, addEventListener(type, handler) { this.handlers[type] = handler; } });
    const query = node(), button = node();
    let allowed = false;
    const controls = bindSearchControls({ query, button, controller, approve: text => approveSearch(text, () => allowed) });
    controller.onChange = state => controls.update(state);
    assert.equal(button.disabled, true);
    query.value = 'Llama ai rozwój';
    query.handlers.input();
    assert.equal(button.disabled, false);
    assert.equal(controller.state.draft, '');
    const before = requests.length;
    await button.handlers.click();
    assert.equal(requests.length, before);
    assert.equal(query.value, 'Llama ai rozwój');
    allowed = true;
    await button.handlers.click();
    const posts = requests.slice(before);
    assert.equal(posts.length, 2);
    assert.equal(posts[1].body.content, 'Llama ai rozwój');
    assert.equal(posts[1].body.search.query, 'Llama ai rozwój');
    assert.equal(posts[1].body.search.approved, true);
    assert.equal(query.value, '');
    assert.equal(button.disabled, true);
});

test('a separate question remains the model message when a query is supplied', async () => {
    let sent;
    const controller = new ChatController(chatFixture({
        createChat: async () => ({ id: 1, model: 'fixture-model' }),
        sendMessage: async (_id, content) => { sent = content; return [{ id: 1, role: 'user', content }, { id: 2, role: 'assistant', content: 'Answer' }]; },
    }));
    await controller.load();
    controller.setDraft('Porównaj wyniki.');
    await controller.send(approveSearch('Public query', () => true));
    assert.equal(sent, 'Porównaj wyniki.');
});
