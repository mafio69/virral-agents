import { mkdirSync } from 'node:fs';

export default async function setup() {
    const runtime = new URL('./runtime/', import.meta.url);
    mkdirSync(runtime, { recursive: true });
    try {
        const response = await fetch('http://127.0.0.1:18025/api/v1/info');
        if (!response.ok) throw new Error();
    } catch {
        throw new Error('Dedicated E2E Mailpit is required on loopback HTTP 18025 / SMTP 11025.');
    }
}
