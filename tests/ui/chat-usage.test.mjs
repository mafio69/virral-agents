import test from 'node:test';
import assert from 'node:assert/strict';
import { formatUsage, appendMessageUsage } from '../../public/ui/chat/chat-usage.js';

class MockElement {
    constructor(tag) {
        this.tag = tag;
        this.className = '';
        this.textContent = '';
        this.children = [];
    }
    append(child) {
        this.children.push(child);
    }
}

const mockDom = {
    createElement: tag => new MockElement(tag),
};

test('formatUsage formats prompt and completion tokens using pl-PL locale', () => {
    const fmt = new Intl.NumberFormat('pl-PL');
    const expected = `wej. ${fmt.format(1250)} / wyj. ${fmt.format(300)}`;

    assert.equal(formatUsage({ prompt_tokens: 1250, completion_tokens: 300 }), expected);
});

test('formatUsage returns null when usage is null, missing or invalid', () => {
    assert.equal(formatUsage(null), null);
    assert.equal(formatUsage(undefined), null);
    assert.equal(formatUsage({}), null);
    assert.equal(formatUsage({ prompt_tokens: 10 }), null);
    assert.equal(formatUsage({ completion_tokens: 10 }), null);
});

test('appendMessageUsage appends span.msg-usage with token count to actions container', () => {
    const actions = new MockElement('div');
    const message = {
        role: 'assistant',
        content: 'Odpowiedź',
        usage: { prompt_tokens: 150, completion_tokens: 42 },
    };

    const node = appendMessageUsage(actions, message, mockDom);

    assert.ok(node);
    assert.equal(actions.children.length, 1);
    assert.equal(actions.children[0].tag, 'span');
    assert.equal(actions.children[0].className, 'msg-usage');

    const fmt = new Intl.NumberFormat('pl-PL');
    assert.equal(actions.children[0].textContent, `wej. ${fmt.format(150)} / wyj. ${fmt.format(42)}`);
});

test('appendMessageUsage does not append anything when usage is null or absent', () => {
    const actions = new MockElement('div');
    const messageWithoutUsage = {
        role: 'assistant',
        content: 'Stara odpowiedź',
        usage: null,
    };

    const node = appendMessageUsage(actions, messageWithoutUsage, mockDom);

    assert.equal(node, null);
    assert.equal(actions.children.length, 0);
});
