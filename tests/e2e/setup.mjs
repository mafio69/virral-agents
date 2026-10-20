import { mkdirSync } from 'node:fs';

export default async function setup() {
    const runtime = new URL('./runtime/', import.meta.url);
    mkdirSync(runtime, { recursive: true });
    const mailbox = process.env.E2E_MAILBOX_URL || 'http://mail-trap.dom';
    try {
        const response = await fetch(`${mailbox}/api/v1/info`);
        if (!response.ok) {
            throw new Error();
        }
    } catch {
        throw new Error(`Test Mailpit API is required at ${mailbox} (override with E2E_MAILBOX_URL).`);
    }
}
