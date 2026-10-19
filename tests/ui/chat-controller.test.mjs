import test from 'node:test';
import assert from 'node:assert/strict';
import { ChatApi, ChatApiError } from '../../public/ui/chat/chat-api.js';
import { ChatController, handleComposerKey } from '../../public/ui/chat/chat-controller.js';

import { chatFixture } from './chat-fixture.mjs';

const chat = id => ({ id, title: 'Rozmowa testowa', model: 'fixture-model', messages: [] });
const pair = content => [
    { id: 1, role: 'user', content },
    { id: 2, role: 'assistant', content: 'Odpowiedź testowa' },
];
function fixture(overrides = {}) {
    return chatFixture({
        getChat: async id => chat(id),
        createChat: async () => chat(5),
        sendMessage: async (_id, content) => pair(content),
        ...overrides,
    });
}
function deferred() {
    let resolve;
    let reject;
    const promise = new Promise((yes, no) => { resolve = yes; reject = no; });
    return { promise, resolve, reject };
}

test('a new conversation sends only its own draft with the chosen model', async () => {
    const calls = [];
    const controller = new ChatController(fixture({
        createChat: async payload => { calls.push(payload); return chat(5); },
        sendMessage: async (id, content) => { calls.push({ id, content }); return pair(content); },
    }));
    await controller.load();
    controller.selectModel('fixture-model');
    controller.setDraft('  Pierwsza linia\nDruga linia  ');
    assert.equal(await controller.send(), true);
    assert.deepEqual(calls, [
        { title: 'Pierwsza linia', model: 'fixture-model' },
        { id: 5, content: 'Pierwsza linia\nDruga linia' },
    ]);
    assert.equal(controller.state.draft, '');
    assert.equal(controller.state.messages.length, 2);
});

test('Gemini is the default while an explicit saved provider choice is preserved', async () => {
    const connections = [{ id: 'ovh', name: 'OVH' }, { id: 'google', name: 'Gemini' }];
    for (const remembered of ['', 'missing', 'ovh']) {
        const controller = new ChatController(fixture({ listConnections: async () => connections }), () => {},
            { read: () => ({ connection: remembered, models: {} }), save: () => {} });
        await controller.load();
        assert.equal(controller.state.selectedConnection, remembered === 'ovh' ? 'ovh' : 'google');
        controller.newChat();
        assert.equal(controller.state.selectedConnection, remembered === 'ovh' ? 'ovh' : 'google');
    }
});

test('blank messages do not create a conversation', async () => {
    let calls = 0;
    const controller = new ChatController(fixture({
        createChat: async () => { calls++; return chat(5); },
    }));
    await controller.load();
    controller.setDraft(' \n ');
    assert.equal(await controller.send(), false);
    assert.equal(calls, 0);
});

test('repeated send actions while AI is working produce one request', async () => {
    const response = deferred();
    let calls = 0;
    const controller = new ChatController(fixture({
        sendMessage: async () => { calls++; return response.promise; },
    }));
    await controller.load();
    controller.setDraft('Cześć');
    const first = controller.send();
    assert.equal(await controller.send(), false);
    assert.equal(controller.newChat(), false);
    assert.equal(await controller.openChat(7), false);
    response.resolve(pair('Cześć'));
    assert.equal(await first, true);
    assert.equal(calls, 1);
    assert.equal(controller.state.busy, false);
});

test('a failed creation keeps the draft and never sends a message', async () => {
    let sends = 0;
    const controller = new ChatController(fixture({
        createChat: async () => { throw new ChatApiError('Failure'); },
        sendMessage: async () => { sends++; return []; },
    }));
    await controller.load();
    controller.setDraft('Zachowaj tę treść');
    assert.equal(await controller.send(), false);
    assert.equal(controller.state.draft, 'Zachowaj tę treść');
    assert.equal(controller.state.activeChat, null);
    assert.equal(controller.state.busy, false);
    assert.equal(sends, 0);
});

test('an unreachable application and an unexpected reply are reported differently', async () => {
    const unreachable = new ChatApi({ fetcher: async () => { throw new TypeError('network'); } });
    await assert.rejects(unreachable.sendMessage(5, 'Cześć'), { status: 0, reason: 'app_unreachable' });
    const malformed = new ChatApi({ fetcher: async () => ({ ok: true, status: 201, json: async () => ({}) }) });
    await assert.rejects(malformed.sendMessage(5, 'Cześć'), { status: 0, reason: 'invalid_app_response' });

    for (const [reason, detail] of [['app_unreachable', 'Błąd połączenia z aplikacją.'], ['invalid_app_response', 'nieoczekiwanym formacie.']]) {
        const controller = new ChatController(fixture({
            sendMessage: async () => { throw new ChatApiError('Failure', 0, reason); },
        }));
        await controller.load();
        controller.setDraft('Cześć');
        assert.equal(await controller.send(), false);
        assert.match(controller.state.error, new RegExp(detail.replace('.', '\\.')));
    }
});

test('a saved user message after an AI failure is recovered without an automatic retry', async () => {
    let sends = 0;
    const controller = new ChatController(fixture({
        sendMessage: async () => { sends++; throw new ChatApiError('Failure', 502); },
        getChat: async id => ({ ...chat(id), messages: [pair('Cześć')[0]] }),
    }));
    await controller.load();
    controller.setDraft('Cześć');
    assert.equal(await controller.send(), false);
    assert.equal(controller.state.messages.length, 1);
    assert.equal(controller.state.draft, '');
    assert.equal(controller.state.deliveryUncertain, false);
    assert.equal(await controller.send(), false);
    assert.equal(sends, 1);
});

test('an answer already saved after a network failure is recovered', async () => {
    const controller = new ChatController(fixture({
        sendMessage: async () => { throw new ChatApiError('Failure'); },
        getChat: async id => ({ ...chat(id), messages: pair('Cześć') }),
    }));
    await controller.load();
    controller.setDraft('Cześć');
    await controller.send();
    assert.equal(controller.state.messages.length, 2);
    assert.equal(controller.state.error, '');
    assert.equal(controller.state.draft, '');
});

test('uncertain delivery blocks another POST until the conversation is checked', async () => {
    let canRead = false;
    let sends = 0;
    const controller = new ChatController(fixture({
        sendMessage: async () => { sends++; throw new ChatApiError('Failure'); },
        getChat: async id => {
            if (!canRead) throw new ChatApiError('Offline');
            return chat(id);
        },
    }));
    await controller.load();
    controller.setDraft('Cześć');
    await controller.send();
    assert.equal(controller.state.deliveryUncertain, true);
    assert.equal(controller.state.draft, 'Cześć');
    assert.equal(await controller.send(), false);
    assert.equal(sends, 1);
    canRead = true;
    assert.equal(await controller.checkDelivery(), true);
    assert.equal(controller.state.deliveryUncertain, false);
    assert.equal(controller.state.draft, 'Cześć');
});

test('a slow open request cannot replace a newer selected conversation', async () => {
    const old = deferred();
    const controller = new ChatController(fixture({
        getChat: async id => id === 1 ? old.promise : chat(id),
    }));
    await controller.load();
    const opening = controller.openChat(1);
    assert.equal(await controller.openChat(2), true);
    old.resolve(chat(1));
    assert.equal(await opening, false);
    assert.equal(controller.state.activeChat.id, 2);
});

test('starting a new conversation clears the model history', async () => {
    const controller = new ChatController(fixture({
        getChat: async id => ({ ...chat(id), messages: pair('Poprzednia rozmowa') }),
    }));
    await controller.load();
    await controller.openChat(1);
    controller.selectModel('another-model');
    assert.equal(controller.state.selectedModel, 'fixture-model');
    assert.equal(controller.newChat(), true);
    await controller.selectConnection('ovh');
    controller.selectModel('another-model');
    assert.equal(controller.state.selectedModel, 'another-model');
    assert.equal(controller.state.activeChat, null);
    assert.deepEqual(controller.state.messages, []);
});

test('authentication failure prevents sending', async () => {
    let sends = 0;
    const controller = new ChatController(fixture({
        listChats: async () => { throw new ChatApiError('Login required', 401); },
        sendMessage: async () => { sends++; return []; },
    }));
    await controller.load();
    controller.setDraft('Cześć');
    assert.equal(controller.state.authenticated, false);
    assert.equal(await controller.send(), false);
    assert.equal(sends, 0);
});

for (const provider of ['openai', 'anthropic', 'google']) test(`opening ${provider} history selects its provider and prevents changing the binding`, async () => {
    const requested = [];
    const controller = new ChatController(fixture({
        listConnections: async () => [{ id: 'ovh', name: 'OVH' }, { id: provider, name: provider }],
        listModels: async connection => { requested.push(connection); return [{ id: connection === provider ? `${provider}:fixture-model` : 'fixture-model' }]; },
        getChat: async id => ({ ...chat(id), model: `${provider}:fixture-model` }),
    }));
    await controller.load();
    await controller.openChat(1);
    assert.equal(controller.state.selectedConnection, provider);
    assert.equal(requested.at(-1), provider);
    assert.equal(await controller.selectConnection('ovh'), false);
    assert.equal(controller.state.activeChat.model, `${provider}:fixture-model`);
});

test('missing model list prevents sending until a catalogue is available', async () => {
    const controller = new ChatController(fixture({
        listModels: async () => { throw new ChatApiError('Offline'); },
    }));
    await controller.load();
    assert.equal(controller.state.authenticated, true);
    assert.equal(controller.state.selectedModel, '');
    assert.ok(controller.state.notice);
    controller.setDraft('Cześć');
    assert.equal(await controller.send(), false);
});

test('Enter sends, while Shift+Enter and composition preserve native editing', () => {
    let sends = 0;
    let prevented = 0;
    const event = { key: 'Enter', preventDefault: () => { prevented++; } };
    const send = () => { sends++; };
    handleComposerKey(event, send);
    handleComposerKey({ ...event, shiftKey: true }, send);
    handleComposerKey({ ...event, isComposing: true }, send);
    handleComposerKey({ ...event, keyCode: 229 }, send);
    handleComposerKey({ ...event, repeat: true }, send);
    assert.equal(sends, 1);
    assert.equal(prevented, 2);
});

test('the transport uses the current endpoint payload and same-origin credentials', async () => {
    let request;
    const api = new ChatApi({
        fetcher: async (url, options) => {
            request = { url, options };
            return { ok: true, json: async () => pair('Cześć') };
        },
    });
    await api.sendMessage(3, 'Cześć');
    assert.equal(request.url, '/api/chats/3/messages');
    assert.equal(request.options.method, 'POST');
    assert.equal(request.options.credentials, 'same-origin');
    assert.deepEqual(JSON.parse(request.options.body), { content: 'Cześć' });
});

test('upstream error bodies are never parsed or exposed', async () => {
    let bodyReads = 0;
    const api = new ChatApi({
        fetcher: async () => ({
            ok: false,
            status: 500,
            json: async () => { bodyReads++; return { trace: 'Internal diagnostics' }; },
        }),
    });
    await assert.rejects(api.listChats(), error =>
        error instanceof ChatApiError && error.status === 500 && !error.message.includes('Internal diagnostics'));
    assert.equal(bodyReads, 0);
});

test('invalid chat identifiers cannot become endpoint paths', async () => {
    let calls = 0;
    const api = new ChatApi({ fetcher: async () => { calls++; } });
    for (const id of ['1/messages', -1, 0, 1.5, NaN]) {
        await assert.rejects(api.getChat(id), ChatApiError);
    }
    assert.equal(calls, 0);
});

test('malformed success responses do not clear a pending draft', async () => {
    const api = new ChatApi({
        fetcher: async () => ({ ok: true, json: async () => ({ role: 'assistant', content: 'Unexpected' }) }),
    });
    await assert.rejects(api.sendMessage(1, 'Cześć'), ChatApiError);
});


test('failed saved reply exposes manual retry, preserves draft and never duplicates the user message', async () => {
    let saved = [{ id: 1, role: 'user', content: 'Cześć', reply_status: 'failed' }];
    let retries = 0;
    const response = deferred();
    const controller = new ChatController(fixture({
        sendMessage: async () => { throw new ChatApiError('Timeout', 504); },
        getChat: async id => ({ ...chat(id), messages: saved }),
        retryMessage: async (chatId, messageId) => { retries++; assert.equal(messageId, 1); return response.promise; },
    }));
    await controller.load(); controller.setDraft('Cześć'); await controller.send(null, true);
    assert.equal(controller.state.retryMessageId, 1);
    controller.setDraft('Nowa niezależna wiadomość');
    const retry = controller.retryReply();
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(await controller.retryReply(), false);
    response.resolve(pair('Cześć'));
    assert.equal(await retry, true);
    assert.equal(retries, 1);
    assert.equal(controller.state.messages.filter(message => message.role === 'user').length, 1);
    assert.equal(controller.state.draft, 'Nowa niezależna wiadomość');
});

test('retry checks server state first and does not call AI if an answer arrived meanwhile', async () => {
    let saved = [{ id: 1, role: 'user', content: 'Cześć', reply_status: 'failed' }];
    let retries = 0;
    const controller = new ChatController(fixture({
        getChat: async id => ({ ...chat(id), messages: saved }),
        retryMessage: async () => { retries++; },
    }));
    await controller.load(); await controller.openChat(5);
    assert.equal(controller.state.retryMessageId, 1);
    saved = pair('Cześć');
    assert.equal(await controller.retryReply(), false);
    assert.equal(retries, 0);
    assert.equal(controller.state.messages.length, 2);
});

test('gateway timeout with server still generating offers state checks instead of a duplicate request', async () => {
    let status = 'running';
    const controller = new ChatController(fixture({
        sendMessage: async () => { throw new ChatApiError('Timeout', 504); },
        getChat: async id => ({ ...chat(id), messages: [{ id: 1, role: 'user', content: 'Cześć', reply_status: status }] }),
    }));
    await controller.load(); controller.setDraft('Cześć'); await controller.send();
    assert.equal(controller.state.deliveryUncertain, true);
    assert.equal(controller.state.retryMessageId, null);
    assert.equal(await controller.retryReply(), false);
    status = 'failed'; await controller.checkDelivery();
    assert.equal(controller.state.deliveryUncertain, false);
    assert.equal(controller.state.retryMessageId, 1);
});

test('retry transport carries only identifiers and an empty body, never search consent', async () => {
    const calls = [];
    const api = new ChatApi({ fetcher: async (url, options) => { calls.push([url, JSON.parse(options.body)]); return { ok: true, json: async () => pair('Cześć') }; } });
    await api.retryMessage(5, 1);
    assert.deepEqual(calls, [['/api/chats/5/messages/1/retry', {}]]);
    await assert.rejects(api.retryMessage(5, '../1'), ChatApiError);
});


test('opening a running reply after reload keeps the state check available', async () => {
    let saved = [{ id: 1, role: 'user', content: 'Cześć', reply_status: 'running' }];
    const controller = new ChatController(fixture({ getChat: async id => ({ ...chat(id), messages: saved }) }));
    await controller.load(); await controller.openChat(5);
    assert.equal(controller.state.deliveryUncertain, true);
    saved = pair('Cześć');
    assert.equal(await controller.checkDelivery(), true);
    assert.equal(controller.state.deliveryUncertain, false);
    assert.equal(controller.state.messages.length, 2);
});
