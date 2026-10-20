import { aiFailureMessage } from './chat-api.js?v=20261018-chat-retry';
import { isInlineImage } from './chat-images.js?v=20261013-6';

function deliveryFailureDetail(error) {
    if (error.status) {
        return ` HTTP ${error.status}.`;
    }
    if (error.reason === 'invalid_app_response') {
        return ' Aplikacja zwróciła odpowiedź w nieoczekiwanym formacie.';
    }
    return ' Błąd połączenia z aplikacją.';
}

/**
 * Conversation state independent of the graphic design and the HTTP transport.
 * The view subscribes to updates and calls these methods for user actions.
 */
export class ChatController {
    constructor(api, onChange = () => {}, preferences = { read: () => ({ connection: '', models: {} }), save: () => {} }, initialConnections = []) {
        this.api = api;
        this.preferences = preferences;
        this.modelsVersion = 0;
        this.onChange = onChange;
        this.selectionVersion = 0;
        this.uncertainDelivery = null;
        this.state = {
            chats: [], models: [], connections: initialConnections, selectedConnection: this.preferredConnection(initialConnections)?.id || '', modelsLoading: false, activeChat: null, messages: [], draft: '', images: [],
            selectedModel: '', authenticated: null, loading: false, busy: false,
            pendingMessage: null, retryMessageId: null, error: '', notice: '', deliveryUncertain: false, searchActivity: 'idle',
        };
    }

    preferredConnection(connections) {
        const remembered = this.preferences.read().connection;
        return connections.find(item => item.id === remembered)
            || connections.find(item => item.id === 'google') || connections[0];
    }

    update(changes) {
        this.state = { ...this.state, ...changes };
        this.onChange(this.state);
    }

    async load() {
        this.update({ loading: true, error: '' });
        const chats = this.api.listChats().then(items => {
            this.update({ chats: items, authenticated: true, loading: false });
        }).catch(error => {
            this.update({
                authenticated: error.status === 401 ? false : null,
                loading: false,
                error: error.status === 401 ? '' : 'Nie udało się wczytać historii rozmów.',
            });
        });
        const connections = this.api.listConnections().then(async items => {
            const prepared = items.filter(item => typeof item.id === 'string' && typeof item.name === 'string');
            this.update({ connections: prepared });
            const selected = this.preferredConnection(prepared);
            if (selected) {
                await this.selectConnection(selected.id);
            }
            else {
                this.update({ notice: 'Brak przygotowanych połączeń AI.' });
            }
        }).catch(async () => {
            const selected = this.preferredConnection(this.state.connections);
            if (selected) {
                await this.selectConnection(selected.id);
            }
            else {
                this.update({ notice: 'Nie udało się pobrać połączeń AI. Odśwież stronę.' });
            }
        });
        await Promise.all([chats, connections]);
    }

    async selectConnection(connection, { forHistory = false } = {}) {
        if (this.state.busy || (this.state.activeChat && !forHistory)
            || !this.state.connections.some(item => item.id === connection)) {
            return false;
        }
        const version = ++this.modelsVersion;
        const remembered = this.preferences.read().models[connection];
        this.update({ selectedConnection: connection, models: [], selectedModel: forHistory ? this.state.activeChat.model : '',
            modelsLoading: true, images: forHistory ? this.state.images : [], notice: '' });
        try {
            const items = await this.api.listModels(connection);
            if (version !== this.modelsVersion) {
                return false;
            }
            const models = items.filter(item => typeof item.id === 'string' && item.id !== '');
            const selected = forHistory ? this.state.activeChat?.model
                : models.find(item => item.id === remembered)?.id || models[0]?.id || '';
            this.update({ models, selectedModel: selected || '', modelsLoading: false,
                notice: models.length ? models.every(item => item.configured === false) ? 'Katalog pobrany. Aby rozmawiać, skonfiguruj dostęp do wybranego dostawcy.' : '' : 'To połączenie nie ma skonfigurowanych modeli.' });
            if (!forHistory && selected) {
                this.preferences.save(connection, selected);
            }
            return true;
        } catch (error) {
            if (version !== this.modelsVersion) {
                return false;
            }
            this.update({ modelsLoading: false, notice: aiFailureMessage(error) || 'Nie udało się pobrać modeli tego połączenia. Wybierz połączenie ponownie.' });
            return false;
        }
    }

    setDraft(draft) {
        if (!this.state.busy) {
            this.update({ draft });
        }
    }

    canAttachImage() {
        const id = this.state.activeChat?.model || this.state.selectedModel || this.state.models.find(model => model.is_default)?.id;
        return this.state.models.some(model => model.id === id && model.supports_images === true);
    }

    setImage(image) {
        if (this.state.busy || this.state.loading || this.state.deliveryUncertain
            || this.state.authenticated !== true || !this.canAttachImage() || !isInlineImage(image)) {
            return false;
        }
        this.update({ images: [image], error: '' });
        return true;
    }

    removeImage() {
        if (!this.state.busy && !this.state.deliveryUncertain) {
            this.update({ images: [] });
        }
    }

    selectModel(model) {
        if (!this.state.activeChat && !this.state.busy && !this.state.modelsLoading
            && this.state.models.some(item => item.id === model)) {
            this.update({ selectedModel: model, images: [], error: '' });
            this.preferences.save(this.state.selectedConnection, model);
        }
    }

    newChat() {
        if (this.state.busy) {
            return false;
        }
        this.selectionVersion++;
        this.uncertainDelivery = null;
        this.update({
            activeChat: null, messages: [], draft: '', images: [], loading: false, searchActivity: 'idle',
            pendingMessage: null, retryMessageId: null, error: '', notice: '', deliveryUncertain: false,
        });
        const connection = this.preferredConnection(this.state.connections)?.id;
        if (connection) {
            void this.selectConnection(connection);
        }
        return true;
    }

    async openChat(id) {
        if (this.state.busy || this.state.authenticated !== true) {
            return false;
        }
        const version = ++this.selectionVersion;
        this.update({ loading: true, error: '' });
        try {
            const chat = await this.api.getChat(id);
            if (version !== this.selectionVersion) {
                return false;
            }
            const last = chat.messages.at(-1);
            const running = last?.role === 'user' && last.reply_status === 'running';
            this.uncertainDelivery = running ? { content: last.content, images: last.images || [], previousIds: new Set(chat.messages.slice(0, -1).map(message => message.id)) } : null;
            this.update({
                activeChat: chat, messages: chat.messages, selectedModel: chat.model || '',
                draft: '', images: [], loading: false, deliveryUncertain: running, retryMessageId: this.retryableMessage(chat.messages),
                notice: running ? 'Serwer nadal przetwarza tę wiadomość. Sprawdź zapis za chwilę.' : '', searchActivity: 'idle',
            });
            const namespace = chat.model?.split(':', 1)[0];
            const connection = namespace === 'db' ? 'zai'
                : this.state.connections.some(item => item.id === namespace) ? namespace : 'ovh';
            await this.selectConnection(connection, { forHistory: true });
            return true;
        } catch (error) {
            if (version !== this.selectionVersion) {
                return false;
            }
            this.update({
                loading: false,
                authenticated: error.status === 401 ? false : this.state.authenticated,
                error: error.status === 401 ? '' : 'Nie udało się otworzyć rozmowy.',
            });
            return false;
        }
    }

    async send(search = null, research = false) {
        const content = this.state.draft.trim()
            || (search?.approved === true && typeof search.query === 'string' ? search.query.trim() : '');
        const images = this.state.images;
        if ((!content && !images.length) || this.state.busy || this.state.loading
            || this.state.deliveryUncertain || this.state.authenticated !== true || this.state.modelsLoading) {
            return false;
        }

        if (!this.state.activeChat && !this.state.selectedModel) {
            this.update({ error: 'Wybierz połączenie z dostępnym modelem.' });
            return false;
        }
        if (images.length && !this.canAttachImage()) {
            this.update({ error: 'Wybierz model obsługujący obrazy.' });
            return false;
        }
        const previousIds = new Set(this.state.messages.map(message => message.id));
        let messageRequestStarted = false;
        this.update({ busy: true, error: '', notice: '', retryMessageId: null, pendingMessage: { role: 'user', content, images }, searchActivity: search ? 'searching' : 'idle' });
        try {
            if (!this.state.activeChat) {
                const title = Array.from((content || 'Analiza obrazu').split(/\r?\n/, 1)[0]).slice(0, 72).join('');
                const chat = await this.api.createChat({ title, model: this.state.selectedModel });
                this.update({
                    activeChat: chat,
                    selectedModel: chat.model || this.state.selectedModel,
                    chats: [chat, ...this.state.chats.filter(item => item.id !== chat.id)],
                });
            }
            messageRequestStarted = true;
            const messages = await this.api.sendMessage(this.state.activeChat.id, content, images, ...(research ? [search, true] : search ? [search] : []));
            this.update({ messages: [...this.state.messages, ...messages], draft: '', images: [], pendingMessage: null, searchActivity: search ? 'done' : 'idle' });
            return true;
        } catch (error) {
            this.update({
                pendingMessage: null,
                searchActivity: search ? 'error' : 'idle',
                authenticated: error.status === 401 ? false : this.state.authenticated,
                error: error.status === 401 ? '' : aiFailureMessage(error) || 'Nie udało się odebrać odpowiedzi.' + deliveryFailureDetail(error) + ' Treść wiadomości została zachowana.',
            });
            if (messageRequestStarted && error.status !== 401) {
                // The backend saves the user message before contacting AI.
                // Read its actual state before permitting a second POST.
                this.uncertainDelivery = { content, images, previousIds };
                await this.reconcileDelivery();
            }
            return false;
        } finally {
            this.update({ busy: false });
        }
    }

    async reconcileDelivery() {
        if (!this.uncertainDelivery || !this.state.activeChat) {
            return true;
        }
        try {
            const chat = await this.api.getChat(this.state.activeChat.id);
            const { content, images, previousIds } = this.uncertainDelivery;
            const matches = message => !previousIds.has(message.id) && message.role === 'user'
                && message.content === content && JSON.stringify(message.images || []) === JSON.stringify(images);
            const saved = chat.messages.some(message =>
                matches(message));
            const answered = saved && chat.messages.some((message, index) =>
                message.role === 'assistant' && chat.messages.slice(0, index).some(previous =>
                    matches(previous)));
            const running = saved && !answered && chat.messages.some(message => matches(message) && message.reply_status === 'running');
            if (!running) {
                this.uncertainDelivery = null;
            }
            this.update({
                activeChat: chat, messages: chat.messages, deliveryUncertain: running,
                retryMessageId: running ? null : this.retryableMessage(chat.messages),
                draft: saved ? '' : this.state.draft,
                images: saved ? [] : this.state.images,
                error: answered ? '' : saved ? this.state.error || 'Wiadomość została zapisana, ale model nie odpowiedział.' : this.state.error,
                notice: running ? 'Serwer nadal przetwarza tę wiadomość. Sprawdź zapis za chwilę.' : answered ? 'Odpowiedź jest już w rozmowie.' : saved
                    ? this.retryableMessage(chat.messages) ? 'Użyj przycisku Ponów.' : 'Nie ma potwierdzonego stanu tej próby. Ponowienie jest niedostępne.' : 'Wiadomość nie została zapisana. Możesz wysłać ją ponownie.',
            });
            return true;
        } catch {
            this.update({
                deliveryUncertain: true,
                notice: 'Sprawdź zapis rozmowy przed ponownym wysłaniem wiadomości.',
            });
            return false;
        }
    }

    retryableMessage(messages) {
        const last = messages.at(-1);
        return last?.role === 'user' && last.reply_status === 'failed' ? last.id : null;
    }

    async retryReply() {
        const id = this.state.retryMessageId;
        if (!id || this.state.busy || this.state.loading || this.state.deliveryUncertain || this.state.authenticated !== true) {
            return false;
        }
        const target = this.state.messages.find(message => message.id === id);
        if (!target) {
            return false;
        }
        this.update({ busy: true, error: '', notice: '', retryMessageId: null });
        try {
            // Read first: another tab or the slow original request may already have completed.
            const chat = await this.api.getChat(this.state.activeChat.id);
            this.update({ activeChat: chat, messages: chat.messages });
            if (this.retryableMessage(chat.messages) !== id) {
                this.update({ notice: 'Zapis rozmowy został odświeżony. Nie ponowiono wywołania AI.' });
                return false;
            }
            const pair = await this.api.retryMessage(chat.id, id);
            const ids = new Set(pair.map(message => message.id));
            this.update({ messages: [...chat.messages.filter(message => !ids.has(message.id)), ...pair], notice: '' });
            return true;
        } catch (error) {
            this.update({ error: aiFailureMessage(error) || 'Ponowienie odpowiedzi nie powiodło się.', authenticated: error.status === 401 ? false : this.state.authenticated });
            this.uncertainDelivery = { content: target.content, images: target.images || [], previousIds: new Set(this.state.messages.filter(message => message.id !== id).map(message => message.id)) };
            await this.reconcileDelivery();
            return false;
        } finally {
            this.update({ busy: false });
        }
    }

    async checkDelivery() {
        if (this.state.busy) {
            return false;
        }
        this.update({ busy: true });
        try {
            return await this.reconcileDelivery();
        } finally {
            this.update({ busy: false });
        }
    }
}

export function handleComposerKey(event, send) {
    if (event.key !== 'Enter' || event.shiftKey || event.isComposing || event.keyCode === 229) {
        return;
    }
    event.preventDefault();
    if (!event.repeat) {
        send();
    }
}
