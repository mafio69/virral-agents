const KEY = 'virral.chat.selection';

export class ChatPreferences {
    read() {
        try {
            const saved = JSON.parse(localStorage.getItem(KEY) || '{}');
            return { connection: typeof saved.connection === 'string' ? saved.connection : '', models: saved.models && typeof saved.models === 'object' ? saved.models : {} };
        } catch { return { connection: '', models: {} }; }
    }

    save(connection, model) {
        if (!connection || !model) return;
        try {
            const saved = this.read();
            localStorage.setItem(KEY, JSON.stringify({ connection, models: { ...saved.models, [connection]: model } }));
        } catch { /* Wybór działa także bez dostępu do pamięci przeglądarki. */ }
    }
}
