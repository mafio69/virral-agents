import test from 'node:test';
import assert from 'node:assert/strict';
import { SearchFilters } from '../../public/ui/chat/chat-search-filters.js';

class Element {
    constructor(tag) { this.tag = tag; this.children = []; this.handlers = {}; this.value = ''; this.disabled = false; this.hidden = false; }
    addEventListener(name, handler) { this.handlers[name] = handler; }
    append(...children) { this.children.push(...children); }
    replaceChildren(...children) { this.children = children; }
    setAttribute(name, value) { this[name] = value; }
    getAttribute(name) { return this[name]; }
    get textContent() { return this.tag === 'button' ? this._text : undefined; }
    set textContent(value) { this._text = value; }
}

const keydown = element => element.handlers.keydown({ key: 'Enter', preventDefault() {} });

function filters() {
    globalThis.document = { createElement: tag => new Element(tag) };
    const parts = {
        excludeInput: new Element('input'), excludeChips: new Element('span'),
        mustInput: new Element('input'), mustChips: new Element('span'),
        phraseInput: new Element('input'), siteInput: new Element('input'),
        moreToggle: new Element('button'), morePanel: new Element('div'),
    };
    // The markup ships the panel with the hidden attribute.
    parts.morePanel.hidden = true;
    return { panel: new SearchFilters(parts), ...parts };
}

test('Enter adds valid tokens as chips; click on chip removes it', () => {
    const { panel, excludeInput, excludeChips } = filters();
    excludeInput.value = ' legacy ';
    keydown(excludeInput);
    assert.deepEqual(panel.collect().exclude, ['legacy']);
    assert.equal(excludeInput.value, '');
    assert.equal(excludeChips.children.length, 1);
    assert.equal(excludeChips.children[0]._text, 'legacy');
    excludeChips.children[0].handlers.click();
    assert.deepEqual(panel.collect(), {});
    assert.equal(excludeChips.children.length, 0);
});

test('operator injection and invalid tokens are ignored', () => {
    const { panel, mustInput } = filters();
    for (const value of ['-legacy', '+twig', 'quo"te', '  ', 'a'.repeat(65), '#hash']) {
        mustInput.value = value;
        keydown(mustInput);
    }
    assert.deepEqual(panel.collect(), {});
    assert.equal(mustInput.value, '#hash');
});

test('duplicates are ignored and the ten token limit holds', () => {
    const { panel, mustInput, mustChips } = filters();
    for (let i = 0; i < 12; i++) {
        mustInput.value = i < 10 ? `word${i}` : 'word9';
        keydown(mustInput);
    }
    assert.equal(panel.collect().must_have.length, 10);
    assert.equal(mustChips.children.length, 10);
});

test('collect returns only non-empty filters with normalized phrase and site', () => {
    const { panel, phraseInput, siteInput } = filters();
    phraseInput.value = '  konfiguracja   yaml ';
    siteInput.value = ' SymFony.COM ';
    assert.deepEqual(panel.collect(), { exact_phrase: 'konfiguracja yaml', sites: ['symfony.com'] });
});

test('collect strips wrapping quotes from the phrase and the URL to its host', () => {
    const { panel, phraseInput, siteInput } = filters();
    phraseInput.value = ' "konfiguracja   yaml" ';
    siteInput.value = 'https://Docs.Symfony.com/page?query=1';
    assert.deepEqual(panel.collect(), { exact_phrase: 'konfiguracja yaml', sites: ['docs.symfony.com'] });
    assert.equal(siteInput.getAttribute('aria-invalid'), 'false');
});

test('collect marks an invalid site inline and returns null', () => {
    const { panel, siteInput } = filters();
    siteInput.value = 'to nie jest domena';
    assert.equal(panel.collect(), null);
    assert.equal(siteInput.getAttribute('aria-invalid'), 'true');
    siteInput.value = 'wikipedia.org';
    assert.deepEqual(panel.collect(), { sites: ['wikipedia.org'] });
    assert.equal(siteInput.getAttribute('aria-invalid'), 'false');
});

test('more options toggle opens and closes the panel', () => {
    const { moreToggle, morePanel } = filters();
    assert.equal(morePanel.hidden, true);
    moreToggle.handlers.click();
    assert.equal(morePanel.hidden, false);
    assert.equal(moreToggle['aria-expanded'], 'true');
    moreToggle.handlers.click();
    assert.equal(morePanel.hidden, true);
    assert.equal(moreToggle['aria-expanded'], 'false');
});

test('setEnabled disables every filter input', () => {
    const { panel, excludeInput, mustInput, phraseInput, siteInput } = filters();
    panel.setEnabled(true);
    assert.deepEqual([excludeInput.disabled, mustInput.disabled, phraseInput.disabled, siteInput.disabled], [true, true, true, true]);
    panel.setEnabled(false);
    assert.deepEqual([excludeInput.disabled, mustInput.disabled, phraseInput.disabled, siteInput.disabled], [false, false, false, false]);
});
