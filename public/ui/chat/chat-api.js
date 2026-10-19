const AI_ERRORS = {
    provider_timeout: 'Model przekroczył limit czasu odpowiedzi. Możesz ponowić po zakończeniu poprzedniej próby.',
    catalogue_unavailable: 'Nie udało się pobrać katalogu modeli od dostawcy. Spróbuj ponownie.',
    missing_configuration: 'Brakuje konfiguracji dostawcy AI lub klucza szyfrowania rejestru. Sprawdź bezpieczne ustawienia aplikacji.',
    provider_auth: 'Dostawca AI odrzucił dostęp. Sprawdź klucz w bezpiecznych ustawieniach, nie w czacie.',
    model_unavailable: 'Model lub endpoint nie jest dostępny u dostawcy AI. Rozpocznij nową rozmowę z dostępnym modelem.',
    provider_limit: 'Dostawca AI zgłasza limit. Spróbuj ponownie później.',
    connection_failed: 'Nie udało się połączyć z dostawcą AI. Sprawdź połączenie i adres usługi.',
    invalid_response: 'Dostawca AI nie zwrócił poprawnej odpowiedzi modelu.',
};
export function aiFailureMessage(error) {
    return Object.hasOwn(AI_ERRORS, error?.reason) ? AI_ERRORS[error.reason] : '';
}

export class ChatApiError extends Error {
    constructor(message, status = 0, reason = '') {
        super(message);
        this.name = 'ChatApiError';
        this.status = status;
        this.reason = reason;
    }
}

/**
 * Transport for the existing Slim chat endpoints.
 * Credentials are supplied by the application; this module never logs them.
 */
export class ChatApi {
    constructor({ fetcher = (...args) => globalThis.fetch(...args), getSession = () => '', baseUrl = '/api' } = {}) {
        this.fetcher = fetcher;
        this.getSession = getSession;
        this.baseUrl = baseUrl;
    }

    async request(method, path, payload) {
        const headers = { Accept: 'application/json' };
        const session = this.getSession();
        if (session) headers.Authorization = 'Bearer ' + session;
        if (payload !== undefined) headers['Content-Type'] = 'application/json';

        let response;
        try {
            response = await this.fetcher(this.baseUrl + path, {
                method,
                headers,
                credentials: 'same-origin',
                ...(payload !== undefined ? { body: JSON.stringify(payload) } : {}),
            });
        } catch {
            throw new ChatApiError('Nie udało się połączyć z aplikacją.', 0, 'app_unreachable');
        }

        if (!response.ok) {
            const message = response.status === 401
                ? 'Zaloguj się, aby korzystać z czatu.'
                : 'Aplikacja nie mogła wykonać tej operacji.';
            // Error bodies can contain provider diagnostics; never expose them in the UI.
            const reason = response.headers?.get?.('X-AI-Error') || (response.status === 504 ? 'provider_timeout' : '');
            throw new ChatApiError(aiFailureMessage({ reason }) || message, response.status, reason);
        }

        try {
            return await response.json();
        } catch {
            throw new ChatApiError('Aplikacja zwróciła nieprawidłową odpowiedź.', response.status, 'invalid_app_response');
        }
    }

    async listChats() {
        const chats = await this.request('GET', '/chats');
        if (!Array.isArray(chats)) throw new ChatApiError('Nieprawidłowa lista rozmów.');
        return chats;
    }

    async listConnections() {
        const connections = await this.request('GET', '/ai-connections');
        if (!Array.isArray(connections)) throw new ChatApiError('Nieprawidłowa lista połączeń.');
        return connections;
    }

    async listModels(connection) {
        const models = await this.request('GET', '/ai-models' + (connection ? '?connection=' + encodeURIComponent(connection) : ''));
        if (!Array.isArray(models)) throw new ChatApiError('Nieprawidłowa lista modeli.');
        return models;
    }

    async getChat(id) {
        const chat = await this.request('GET', '/chats/' + this.chatId(id));
        if (!chat || !Number.isInteger(chat.id) || !Array.isArray(chat.messages)) {
            throw new ChatApiError('Nieprawidłowa odpowiedź rozmowy.');
        }
        return chat;
    }

    async createChat({ title, model }) {
        const chat = await this.request('POST', '/chats', {
            title,
            ...(model ? { model } : {}),
        });
        if (!chat || !Number.isInteger(chat.id)) throw new ChatApiError('Nie udało się utworzyć rozmowy.');
        return chat;
    }

    async sendMessage(id, content, images = [], search = null, research = false) {
        const messages = await this.request('POST', '/chats/' + this.chatId(id) + '/messages', { content, ...(images.length ? { images } : {}), ...(search ? { search } : {}), ...(research ? { research: true } : {}) });
        if (!Array.isArray(messages) || messages.length !== 2
            || messages[0]?.role !== 'user' || messages[1]?.role !== 'assistant'
            || messages.some(message => !Number.isInteger(message.id) || typeof message.content !== 'string')) {
            throw new ChatApiError('Nieprawidłowa odpowiedź modelu.', 0, 'invalid_app_response');
        }
        return messages;
    }

    async retryMessage(chatId, messageId) {
        const messages = await this.request('POST', '/chats/' + this.chatId(chatId) + '/messages/' + this.chatId(messageId) + '/retry', {});
        if (!Array.isArray(messages) || messages.length !== 2 || messages[0]?.role !== 'user'
            || messages[0]?.id !== messageId || messages[1]?.role !== 'assistant'
            || messages.some(message => !Number.isInteger(message.id) || typeof message.content !== 'string')) {
            throw new ChatApiError('Nieprawidłowa odpowiedź ponowienia.', 0, 'invalid_app_response');
        }
        return messages;
    }

    chatId(id) {
        if (!Number.isInteger(id) || id <= 0) throw new ChatApiError('Nieprawidłowa rozmowa.');
        return id;
    }

    async research(operation, scope, payload) {
        if (!Number.isInteger(scope) || scope < 0) throw new ChatApiError('Nieprawidłowe badanie.');
        const routes = { state: ['GET', ''], propose: ['POST', '/actions'], decide: ['POST', '/decisions'], select: ['PUT', '/sources'], prepare: ['POST', '/prepare'] };
        const route = routes[operation];
        if (!route) throw new ChatApiError('Nieznana operacja badania.');
        const data = await this.request(route[0], '/research' + route[1] + '?chat_id=' + scope, payload);
        if (!data || !Array.isArray(data.results) || !Array.isArray(data.sources) || !Array.isArray(data.actions)) throw new ChatApiError('Nieprawidłowy stan źródeł.');
        return data;
    }
}
