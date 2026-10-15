import test from 'node:test';
import assert from 'node:assert/strict';
import { ChatApi } from '../../public/ui/chat/chat-api.js';
import { ChatController } from '../../public/ui/chat/chat-controller.js';
import { isInlineImage, isGeneratedImage, appendMessageImages } from '../../public/ui/chat/chat-images.js';

import { chatFixture } from './chat-fixture.mjs';

const image = 'data:image/png;base64,YWJj';

test('assistant image renders with a download link; remote and SVG sources never render', () => {
    const nodes = [];
    const body = { append: node => nodes.push(node) };
    const dom = { createElement: tag => ({ tag }) };
    appendMessageImages(body, { role: 'assistant', images: [image] }, dom);
    assert.equal(nodes[0].tag, 'img');
    assert.equal(nodes[0].src, image);
    assert.equal(nodes[1].download, 'obraz-ai.png');
    assert.equal(nodes[1].href, image);
    appendMessageImages(body, { role: 'assistant', images: ['https://example.invalid/image', 'data:image/svg+xml;base64,YQ=='] }, dom);
    assert.equal(nodes.length, 2);
    const large = 'data:image/png;base64,' + 'A'.repeat(800000);
    assert.equal(isInlineImage(large), false);
    assert.equal(isGeneratedImage(large), true);
    assert.equal(isGeneratedImage('data:image/png;base64,' + 'A'.repeat(12000000)), false);
});
const conversation = { id: 1, title: 'Obraz', model: 'Qwen3.6-27B', messages: [] };
const pair = [{ id: 1, role: 'user', content: '', images: [image] }, { id: 2, role: 'assistant', content: 'Opis' }];
const fixture = overrides => chatFixture({
    listModels: async () => [{ id: 'Qwen3.6-27B', supports_images: true }, { id: 'text-only' }],
    createChat: async () => conversation,
    getChat: async () => ({ ...conversation, messages: pair }),
    sendMessage: async () => pair,
    ...overrides,
});

test('image-only draft is sent once and cleared after success', async () => {
    let payload;
    const controller = new ChatController(fixture({ sendMessage: async (...args) => { payload = args; return pair; } }));
    await controller.load();
    controller.selectModel('Qwen3.6-27B');
    assert.equal(controller.setImage(image), true);
    assert.equal(await controller.send(), true);
    assert.deepEqual(payload, [1, '', [image]]);
    assert.deepEqual(controller.state.images, []);
    assert.deepEqual(controller.state.messages[0].images, [image]);
});

test('model without vision cannot accept or send images', async () => {
    let created = false;
    const controller = new ChatController(fixture({ createChat: async () => { created = true; return conversation; } }));
    await controller.load();
    controller.selectModel('text-only');
    assert.equal(controller.setImage(image), false);
    controller.selectModel('Qwen3.6-27B');
    controller.setImage(image);
    controller.selectModel('text-only');
    assert.equal(await controller.send(), false);
    assert.equal(created, false);
});

test('new conversation clears attachment, failed delivery recovers saved attachment', async () => {
    const controller = new ChatController(fixture({ sendMessage: async () => { throw new Error('offline'); } }));
    await controller.load();
    controller.selectModel('Qwen3.6-27B');
    controller.setImage(image);
    assert.equal(await controller.send(), false);
    assert.deepEqual(controller.state.images, []);
    assert.equal(controller.state.messages.length, 2);
    controller.newChat();
    assert.deepEqual(controller.state.messages, []);
    assert.deepEqual(controller.state.images, []);
});

test('image sources exclude remote URLs, SVG and oversized values', () => {
    assert.equal(isInlineImage(image), true);
    assert.equal(isInlineImage('https://example.invalid/picture.png'), false);
    assert.equal(isInlineImage('data:image/svg+xml;base64,YWJj'), false);
    assert.equal(isInlineImage('data:image/png;base64,' + 'A'.repeat(700001)), false);
});

test('transport includes attachment in message payload', async () => {
    let payload;
    const api = new ChatApi({ fetcher: async (_url, options) => {
        payload = JSON.parse(options.body);
        return { ok: true, json: async () => pair };
    } });
    await api.sendMessage(1, '', [image]);
    assert.deepEqual(payload, { content: '', images: [image] });
});
