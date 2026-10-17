import test from 'node:test';
import assert from 'node:assert/strict';
import { ResearchModal } from '../../public/ui/chat/chat-research-modal.js';

class Element {
    constructor() { this.handlers = {}; this.children = []; this.isConnected = true; }
    addEventListener(name, handler) { this.handlers[name] = handler; }
    append(child) { this.children.push(child); }
    replaceChildren() { this.children = []; }
    focus() { this.focused = true; }
    showModal() { this.open = true; this.shown = (this.shown || 0) + 1; }
    close() { this.open = false; this.handlers.close(); }
    getBoundingClientRect() { return { left: 10, right: 100, top: 10, bottom: 100 }; }
}
function fixture() {
    globalThis.document = { createElement: () => new Element() };
    return new ResearchModal({ dialog: new Element(), openButtons: [new Element(), new Element()],
        closeButton: new Element(), count: new Element(), sourceList: new Element() });
}
test('closing and reopening keeps results intact and restores focus', () => {
    const modal = fixture();
    const state = { sources: [{ id: 'a', title: '<script>untrusted</script>', url: 'https://example.org/a', page: { status: 'loaded' } }],
        results: [{ id: 'a' }, { id: 'b', title: 'B', url: 'https://example.org/b' }] };
    modal.update(state);
    assert.equal(modal.count.textContent, '1');
    assert.equal(modal.sourceList.children.length, 2);
    const source = modal.sourceList.children[0].children[0];
    assert.equal(source.textContent, '✓ <script>untrusted</script>');
    source.handlers.click(); modal.open(source);
    assert.equal(modal.dialog.shown, 1);
    modal.closeButton.handlers.click();
    assert.equal(source.focused, true);
    assert.equal(modal.sourceList.children.length, 2);
    modal.openButtons[1].handlers.click();
    assert.equal(modal.dialog.open, true);
    assert.equal(state.sources[0].page.status, 'loaded');
});
test('backdrop closes while content click stays open; replaced opener has a focus fallback', () => {
    const modal = fixture();
    const opener = new Element();
    modal.open(opener);
    modal.dialog.handlers.click({ target: modal.dialog, clientX: 50, clientY: 50 });
    assert.equal(modal.dialog.open, true);
    opener.isConnected = false;
    modal.dialog.handlers.click({ target: modal.dialog, clientX: 0, clientY: 0 });
    assert.equal(modal.dialog.open, false);
    assert.equal(modal.openButtons[0].focused, true);
});
