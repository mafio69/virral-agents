import test from 'node:test';
import assert from 'node:assert/strict';
import { approveSearch } from '../../public/ui/chat/chat-search.js';
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
