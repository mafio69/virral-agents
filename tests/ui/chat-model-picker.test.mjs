import test from 'node:test';
import assert from 'node:assert/strict';
import { pickerPosition } from '../../public/ui/chat/chat-model-picker.js';

test('model popup centers under its button and stays within both viewport edges', () => {
    for (const viewport of [320, 768, 1440]) {
        for (const left of [0, viewport / 2, viewport - 120]) {
            const position = pickerPosition({ left, width: 120, top: 20, bottom: 56 }, viewport, 800);
            assert.ok(parseFloat(position.left) >= 16);
            assert.ok(parseFloat(position.left) + parseFloat(position.width) <= viewport - 16);
        }
    }
    assert.equal(pickerPosition({ left: 600, width: 200, top: 20, bottom: 56 }, 1440, 800).left, '440px');
});

test('model popup opens upward when there is insufficient space below', () => {
    const position = pickerPosition({ left: 100, width: 200, top: 700, bottom: 736 }, 1000, 800);
    assert.equal(position.top, 'auto');
    assert.equal(position.bottom, '106px');
});
