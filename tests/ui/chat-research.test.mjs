import test from 'node:test';
import assert from 'node:assert/strict';
import { ResearchPanel } from '../../public/ui/chat/chat-research.js';

class Element {
    constructor(tag) { this.tag = tag; this.children = []; this.handlers = {}; this.value = ''; }
    addEventListener(name, handler) { this.handlers[name] = handler; }
    append(...children) { this.children.push(...children); }
    replaceChildren(...children) { this.children = children; }
    setAttribute(name, value) { this[name] = value; }
}
const state = (sources = [], actions = []) => ({ sources, actions, results: [] });
function fixture(api, options = {}) {
    globalThis.document = { createElement: tag => new Element(tag) };
    return new ResearchPanel(api, { query: new Element('input'), search: new Element('button'), body: new Element('div'), summary: new Element('summary'), ...options });
}
test('search accepts query without model or message; proposal itself has no approval', async () => {
    const calls = [];
    const panel = fixture({ research: async (...args) => { calls.push(args); return state(); } });
    await panel.setScope(0);
    panel.query.value = 'Public query'; panel.query.handlers.input();
    assert.equal(panel.search.disabled, false);
    panel.search.handlers.click();
    await new Promise(resolve => setImmediate(resolve));
    assert.deepEqual(calls[1], ['propose', 0, { request: { kind: 'search', query: 'Public query' } }]);
    assert.equal(calls.length, 2);
});
test('unread selections defer sending until each separate decision is resolved', async () => {
    const source = { id: 'a', url: 'https://example.org', title: 'Example', page: { status: 'unread' } };
    const action = { id: 'b', request: { kind: 'fetch', url: source.url }, origin: 'user', status: 'pending' };
    let resumed = 0;
    const panel = fixture({ research: async operation => operation === 'state' ? state([source])
        : operation === 'prepare' ? state([source], [action])
        : state([{ ...source, page: { status: 'denied' } }], [{ ...action, status: 'denied' }]) }, { onResume: () => { resumed++; } });
    await panel.setScope(0);
    assert.equal(await panel.beforeSend(), false);
    assert.equal(resumed, 0);
    await panel.decide(action, false);
    assert.equal(resumed, 1);
    assert.equal(panel.state.sources[0].page.status, 'denied');
});
test('refresh requested during scope loading runs again to include newly created AI requests', async () => {
    let resolveFirst, reads = 0;
    const panel = fixture({ research: async () => { reads++; return reads === 1 ? await new Promise(resolve => { resolveFirst = resolve; }) : state([], [{ id: 'new', origin: 'ai', status: 'pending', request: { kind: 'search', query: 'New query' } }]); } });
    const loading = panel.setScope(1);
    await panel.refresh();
    resolveFirst(state());
    await loading;
    assert.equal(reads, 2);
    assert.equal(panel.state.actions[0].id, 'new');
    assert.equal(panel.busy, false);
});
test('failed approved search reports failure even if previous search has results', async () => {
    const action = { id: 'a', origin: 'user', request: { kind: 'search', query: 'Query' }, status: 'pending' };
    const notices = [];
    const panel = fixture({ research: async operation => state([], operation === 'state' ? [action] : [{ ...action, status: 'failed' }]) }, { onNotice: (...args) => notices.push(args) });
    await panel.setScope(0);
    await panel.decide(action, true);
    assert.equal(notices.at(-1)[1], true);
    assert.match(notices.at(-1)[0], /nie powiodła/);
});
