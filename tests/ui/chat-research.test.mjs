import test from 'node:test';
import assert from 'node:assert/strict';
import { ResearchPanel, continueResearch, fetchReasonText } from '../../public/ui/chat/chat-research.js';

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

test('Fetch all requires confirmation and approves only the displayed selected fetches', async () => {
    const actions = ['one', 'two', 'other'].map(id => ({ id, origin: 'user', status: 'pending', request: { kind: 'fetch', url: `https://example.org/${id}` } }));
    actions.push({ id: 'search', status: 'pending', request: { kind: 'search', query: 'Other query' } });
    const sources = actions.slice(0, 2).map(action => ({ id: action.id, url: action.request.url, page: { status: 'unread' } }));
    const calls = [];
    const panel = fixture({ research: async (operation, scope, payload) => {
        if (operation === 'decide') { calls.push(payload); actions.find(a => a.id === payload.id).status = 'done'; }
        return state(sources, actions);
    } });
    await panel.setScope(0);
    await panel.fetchAll();
    assert.equal(calls.length, 0);
    panel.bulkApproval = panel.pendingFetches().map(action => ({ ...action, request: { ...action.request } }));
    assert.equal(calls.length, 0);
    assert.deepEqual(panel.bulkApproval.map(a => a.id), ['one', 'two']);
    await panel.fetchAll();
    assert.deepEqual(calls, [{ id: 'one', allow: true }, { id: 'two', allow: true }]);
    await panel.fetchAll();
    assert.equal(calls.length, 2);
    assert.equal(actions[2].status, 'pending');
    assert.equal(actions[3].status, 'pending');
});
test('Fetch all continues after page failure and resumes the requested synthesis', async () => {
    const actions = ['one', 'two'].map(id => ({ id, origin: 'user', status: 'pending', request: { kind: 'fetch', url: `https://example.org/${id}` } }));
    const sources = actions.map(a => ({ id: a.id, url: a.request.url, page: { status: 'unread' } }));
    const calls = [];
    const panel = fixture({ research: async (operation, scope, payload) => {
        if (operation === 'decide') { calls.push(payload.id); actions.find(a => a.id === payload.id).status = payload.id === 'one' ? 'failed' : 'done'; }
        return state(sources, actions);
    } });
    await panel.setScope(0);
    let resumed = 0;
    panel.onResume = () => { resumed++; };
    panel.resume = true;
    panel.bulkApproval = panel.pendingFetches();
    await panel.fetchAll();
    assert.deepEqual(calls, ['one', 'two']);
    assert.equal(resumed, 1);
    assert.equal(panel.bulkApproval, null);
    assert.equal(actions[1].status, 'done');
});

test('a failed individual fetch still resumes the waiting command', async () => {
    const action = { id: 'one', origin: 'user', status: 'pending', request: { kind: 'fetch', url: 'https://example.org/one' } };
    let resumed = 0;
    const panel = fixture({ research: async operation => state([], [{ ...action, status: operation === 'state' ? 'pending' : 'failed' }]) }, { onResume: () => { resumed++; } });
    await panel.setScope(0);
    panel.resume = true;
    await panel.decide(action, true);
    assert.equal(resumed, 1);
});


test('visible continue action needs no typed follow-up, keeps draft and prevents duplicate clicks', async () => {
    const source = { id: 'a', url: 'https://example.org/a', page: { status: 'loaded' } };
    let sends = 0, release;
    const controller = { state: { draft: '', messages: [{ role: 'user', content: 'Original question' }] },
        setDraft(value) { this.state.draft = value; } };
    let researchEnabled = false;
    const panel = fixture({ research: async () => state([source]) }, {
        onContinue: () => continueResearch(controller, () => { researchEnabled = true; }, async () => {
            sends++;
            await new Promise(resolve => { release = resolve; });
        }),
    });
    await panel.setScope(1);
    const button = panel.body.children.find(el => el.textContent === 'Kontynuuj z wybranymi stronami');
    assert.ok(button);
    const first = button.handlers.click();
    await button.handlers.click();
    assert.equal(sends, 1);
    assert.equal(button.disabled, true);
    assert.equal(researchEnabled, true);
    assert.match(controller.state.draft, /Kontynuuj poprzednie zadanie/);
    release(); await first;
    assert.equal(panel.continueButton.disabled, false);
    controller.state.draft = 'My specific synthesis instructions';
    await continueResearch(controller, () => {}, async () => { sends++; });
    assert.equal(controller.state.draft, 'My specific synthesis instructions');
    assert.equal(sends, 2);
});

test('continue click fetches selected pages without a second prompt then resumes exactly once', async () => {
    const source = { id: 'a', url: 'https://example.org/a', page: { status: 'unread' } };
    const action = { id: 'fetch', request: { kind: 'fetch', url: source.url }, origin: 'user', status: 'pending' };
    const calls = []; let sends = 0;
    let snapshot = state([source]);
    const panel = fixture({ research: async (operation, scope, payload) => {
        calls.push([operation, payload]);
        if (operation === 'prepare') snapshot = state([source], [action]);
        if (operation === 'decide') snapshot = state([{ ...source, page: { status: 'loaded' } }], [{ ...action, status: 'done' }]);
        return snapshot;
    } });
    panel.onContinue = async () => { if (await panel.beforeSend()) sends++; };
    await panel.setScope(1);
    await panel.continueWithSources();
    assert.deepEqual(calls.map(call => call[0]), ['state', 'prepare', 'decide']);
    assert.deepEqual(calls[2][1], { id: 'fetch', allow: true });
    assert.equal(sends, 1);
});

test('failed preparation and blocked chat never dispatch a continuation', async () => {
    const source = { id: 'a', url: 'https://example.org/a', page: { status: 'unread' } };
    let sends = 0;
    const panel = fixture({ research: async operation => {
        if (operation === 'prepare') throw new Error('Fixture unavailable');
        return state([source]);
    } });
    panel.onContinue = async () => { if (await panel.beforeSend()) sends++; };
    await panel.setScope(1);
    await panel.continueWithSources();
    assert.equal(sends, 0);
    assert.equal(panel.resume, false);
    panel.canContinue = () => false;
    panel.onContinue = () => { sends++; };
    panel.updateButton();
    assert.equal(panel.continueButton.disabled, true);
    await panel.continueWithSources();
    assert.equal(sends, 0);
});

test('new research starts with a useful default task and search results reflect persisted selection', async () => {
    const source = { id: 'a', url: 'https://example.org/a', title: 'Fixture', snippet: '', page: { status: 'unread' } };
    const controller = { state: { draft: '', messages: [] }, setDraft(value) { this.state.draft = value; } };
    await continueResearch(controller, () => {}, async () => {});
    assert.match(controller.state.draft, /Opracuj wybrane źródła/);
    const panel = fixture({ research: async () => ({ ...state([source]), results: [source] }) });
    await panel.setScope(0);
    assert.equal(panel.resultNode(source).children[0].checked, true);
    panel.state.sources = [];
    panel.render();
    assert.equal(panel.resultNode(source).children[0].checked, false);
    assert.equal(panel.continueButton, null);
});


test('continuation fetches only the clicked selection and continues after a page failure', async () => {
    const sources = ['one', 'two'].map(id => ({ id, url: `https://example.org/${id}`, page: { status: 'unread' } }));
    const actions = [...sources, { id: 'unchecked', url: 'https://example.org/unchecked' }].map(source => ({
        id: source.id, request: { kind: 'fetch', url: source.url }, origin: 'user', status: 'pending',
    }));
    const calls = []; let continued = 0;
    const panel = fixture({ research: async (operation, scope, payload) => {
        if (operation === 'decide') {
            calls.push(payload.id);
            actions.find(action => action.id === payload.id).status = payload.id === 'one' ? 'failed' : 'done';
            sources.find(source => source.id === payload.id).page.status = payload.id === 'one' ? 'failed' : 'loaded';
        }
        return state(sources, actions);
    } }, { onContinue: () => { continued++; } });
    await panel.setScope(1);
    await panel.continueWithSources();
    assert.deepEqual(calls, ['one', 'two']);
    assert.equal(actions[2].status, 'pending');
    assert.equal(continued, 1);
});

test('uncertain fetch delivery stops continuation without retry or AI dispatch', async () => {
    const source = { id: 'a', url: 'https://example.org/a', page: { status: 'unread' } };
    const action = { id: 'a', request: { kind: 'fetch', url: source.url }, origin: 'user', status: 'pending' };
    let attempts = 0, continued = 0;
    const panel = fixture({ research: async operation => {
        if (operation === 'decide') { attempts++; throw new Error('Lost response'); }
        return state([source], [action]);
    } }, { onContinue: () => { continued++; } });
    await panel.setScope(1);
    await panel.continueWithSources();
    assert.equal(attempts, 1);
    assert.equal(continued, 0);
});

test('switching chats during preparation cancels old fetch approval and continuation', async () => {
    const source = { id: 'a', url: 'https://example.org/a', page: { status: 'unread' } };
    let resolvePrepare, continued = 0, fetches = 0;
    const panel = fixture({ research: async (operation, scope) => {
        if (operation === 'prepare') return new Promise(resolve => { resolvePrepare = resolve; });
        if (operation === 'decide') fetches++;
        return scope === 1 ? state([source]) : state();
    } }, { onContinue: () => { continued++; } });
    await panel.setScope(1);
    const pending = panel.continueWithSources();
    await panel.setScope(2);
    resolvePrepare(state([source], [{ id: 'a', request: { kind: 'fetch', url: source.url }, status: 'pending' }]));
    await pending;
    assert.equal(fetches, 0);
    assert.equal(continued, 0);
});


test('continue stays visibly locked through page loading, rerenders and the AI response', async () => {
    const source = { id: 'a', url: 'https://example.org/a', page: { status: 'unread' } };
    const action = { id: 'a', origin: 'user', status: 'pending', request: { kind: 'fetch', url: source.url } };
    let resolveFetch, resolveAi, aiCalls = 0;
    const locks = [];
    const panel = fixture({ research: async operation => {
        if (operation === 'state') return state([source]);
        if (operation === 'prepare') return state([source], [action]);
        return new Promise(resolve => { resolveFetch = () => resolve(state([
            { ...source, page: { status: 'loaded' } },
        ], [{ ...action, status: 'done' }])); });
    } }, {
        onContinuationChange: value => locks.push(value),
        onContinue: async () => { aiCalls++; await new Promise(resolve => { resolveAi = resolve; }); },
    });
    await panel.setScope(1);
    const pending = panel.continueWithSources();
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(panel.continueButton.disabled, true);
    assert.equal(panel.continueButton['aria-busy'], 'true');
    assert.match(panel.continueButton.textContent, /Pobieram/);
    await panel.continueWithSources();
    assert.equal(aiCalls, 0);
    resolveFetch();
    await new Promise(resolve => setImmediate(resolve));
    panel.render();
    assert.equal(panel.continueButton.disabled, true);
    assert.match(panel.continueButton.textContent, /AI przygotowuje/);
    await panel.continueWithSources();
    assert.equal(aiCalls, 1);
    assert.deepEqual(locks, [true]);
    resolveAi(); await pending;
    assert.deepEqual(locks, [true, false]);
    assert.equal(panel.continueButton.disabled, false);
    assert.equal(panel.continueButton['aria-busy'], 'false');
});


test('modal footer owns the single Continue control and survives panel redraws', async () => {
    const control = new Element('button');
    const snapshots = [];
    let finish, sent = 0;
    const source = { id: 'a', url: 'https://example.org/a', title: 'A', page: { status: 'loaded' } };
    const panel = fixture({ research: async () => state([source]) }, {
        continueControl: control, continueLabel: 'Kontynuuj', onState: value => snapshots.push(value),
        onContinue: async () => { sent++; await new Promise(resolve => { finish = resolve; }); },
    });
    await panel.setScope(7);
    assert.equal(control.textContent, 'Kontynuuj');
    assert.equal(control.disabled, false);
    const descendants = el => el.children.flatMap(child => [child, ...descendants(child)]);
    assert.equal(descendants(panel.body).filter(el => el.tag === 'button' && /Kontynuuj|Przeczytaj|Fetch all/.test(el.textContent)).length, 0);
    const sending = panel.continueWithSources();
    panel.render();
    assert.equal(control.disabled, true);
    assert.equal(control['aria-busy'], 'true');
    assert.match(control.textContent, /AI przygotowuje/);
    await control.handlers.click();
    assert.equal(sent, 1);
    finish(); await sending;
    assert.equal(control.disabled, false);
    assert.equal(snapshots.at(-1).sources[0].id, 'a');
});

test('fetch failure codes map to readable labels and unknown codes stay visible', () => {
    assert.equal(fetchReasonText('http_blocked'), 'strona odmówiła dostępu');
    assert.equal(fetchReasonText('empty_content'), 'brak tekstu (strona wymaga JavaScript)');
    assert.equal(fetchReasonText('redirect'), 'nieprawidłowe przekierowanie');
    assert.equal(fetchReasonText('insecure_redirect'), 'przekierowanie z HTTPS na HTTP');
    assert.equal(fetchReasonText('future_code'), 'future_code');
    assert.equal(fetchReasonText(undefined), '');
});
test('a web operation requested by AI in a reply opens the consent view once', async () => {
    const action = { id: 'ai-1', request: { kind: 'search', query: 'Public query' }, origin: 'ai', status: 'pending' };
    let pending = false;
    const notices = [];
    const panel = fixture({ research: async () => state([], pending ? [action] : []) }, { onNotice: (text, error = false) => notices.push([text, error]) });
    await panel.setScope(0);
    assert.equal(await panel.refreshAfterReply(), true);
    assert.deepEqual(notices, []);
    pending = true;
    assert.equal(await panel.refreshAfterReply(), true);
    assert.equal(notices.length, 1);
    assert.match(notices[0][0], /Allow lub Deny/);
    assert.equal(notices[0][1], false);
});
