import { ChatPreferences } from './chat-preferences.js?v=20261013-6';
import { enhanceModelPicker } from './chat-model-picker.js?v=20261015-picker';
import { ChatApi } from './chat-api.js?v=20261025-clear';
import { ChatController, handleComposerKey } from './chat-controller.js?v=20261025-clear';
import { mountSearchDog } from './chat-search-dog.js?v=20261016-sniffing';
import { ResearchModal } from './chat-research-modal.js?v=20261025-clear';
import { ResearchPanel, continueResearch } from './chat-research.js?v=20261025-clear';
import { MarkdownRenderer } from './chat-markdown.js?v=20261013-6';
import { prepareImage, isInlineImage, appendMessageImages } from './chat-images.js?v=20261015-images';

let preparingImage = false;
let researchPanel;
let researchBusy = false;
let researchContinuation = false;
let researchActivity = 'idle';

const byId = id => document.getElementById(id);
const app = byId('app'), sidebar = byId('sidebar'), list = byId('chat-list');
const thread = byId('thread'), messages = byId('chat-messages');
const input = byId('chat-input'), send = byId('send-button'), model = byId('chat-model');
const connection = byId('chat-connection');
const researchModal = new ResearchModal({
    dialog: byId('research-dialog'), openButtons: [byId('research-open'), byId('research-open-side')],
    closeButton: byId('research-close'), count: byId('research-count'), sourceList: byId('research-source-list'),
    clearButton: byId('research-clear-side'),
    onClear: () => {
        void researchPanel?.clear();
    },
});
const status = byId('chat-status'), mobile = matchMedia('(max-width: 860px)');
const markdown = new MarkdownRenderer();
const searchDog = mountSearchDog(byId('search-dog'));
const element = (tag, className, text) => {
    const node = document.createElement(tag);
    if (className) {
        node.className = className;
    }
    if (text !== undefined) {
        node.textContent = text;
    }
    return node;
};
const icon = name => {
    const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    const use = document.createElementNS(svg.namespaceURI, 'use');
    svg.setAttribute('class', 'mark');
    svg.setAttribute('aria-hidden', 'true');
    use.setAttribute('href', '#' + name);
    svg.append(use);
    return svg;
};

function setSidebar(open) {
    app.dataset.sidebar = open ? 'open' : 'closed';
    sidebar.inert = !open;
    document.querySelectorAll('button[data-toggle-sidebar]').forEach(button => {
        button.setAttribute('aria-expanded', String(open));
    });
}

function autosize() {
    input.style.height = 'auto';
    input.style.height = Math.min(input.scrollHeight, 200) + 'px';
}

function messageNode(message) {
    if (message.kind && message.kind !== 'message') {
        return element('p', 'msg-chip', message.content);
    }
    const assistant = message.role === 'assistant';
    const article = element('article', 'msg ' + (assistant ? 'ai' : 'user'));
    const body = element('div', 'body');
    const prose = element('div', 'prose');
    markdown.renderInto(prose, message.content);
    appendMessageImages(body, message);
    body.append(prose);
    if (assistant) {
        const avatar = element('div', 'avatar');
        avatar.append(icon('i-mark'));
        const actions = element('div', 'msg-actions');
        const copy = element('button', 'btn-ghost', 'Kopiuj');
        copy.type = 'button';
        copy.setAttribute('aria-label', 'Kopiuj odpowiedź');
        copy.addEventListener('click', async () => {
            try {
                await navigator.clipboard.writeText(message.content);
                copy.textContent = 'Skopiowano';
            } catch {
                copy.textContent = 'Zaznacz tekst i skopiuj';
            }
        });
        actions.append(copy);
        body.append(actions);
        article.append(avatar);
    }
    article.append(body);
    return article;
}

let renderedMessages, renderedPending, renderedBusy, renderedChats, renderedChatId;
let renderedModels, renderedModel, renderedImages, renderedConnections, renderedConnection, renderedModelsLoading;
function render(state) {
    const blocked = preparingImage || researchBusy || researchContinuation || state.busy || state.loading || state.authenticated !== true;
    const currentId = state.activeChat?.id;
    app.dataset.state = state.authenticated === false ? 'loggedout' : state.busy ? 'pending'
        : !state.messages.length && !state.pendingMessage ? 'empty' : 'chat';
    byId('chat-title').textContent = state.activeChat?.title || 'Nowa rozmowa';
    const confirmedModel = !state.modelsLoading && state.authenticated === true
        ? state.models.find(item => item.id === (state.activeChat?.model || state.selectedModel)) : null;
    const modelLabel = byId('composer-model');
    modelLabel.hidden = !confirmedModel;
    modelLabel.textContent = confirmedModel ? `Model: ${confirmedModel.name || confirmedModel.id}` : '';
    input.disabled = preparingImage || researchContinuation || state.busy || state.loading;
    if (input.value !== state.draft) { input.value = state.draft; autosize(); }
    send.disabled = blocked || state.modelsLoading || (!state.activeChat && !state.selectedModel) || state.deliveryUncertain || (!state.draft.trim() && !state.images.length);
    researchPanel?.setScope(state.authenticated === true ? currentId || 0 : null);
    if (researchPanel) {
        researchPanel.updateButton();
        researchPanel.search.disabled ||= preparingImage || state.busy || state.loading;
        byId('research-clear-side').disabled = preparingImage || researchContinuation || researchBusy || state.busy || state.loading || !researchPanel.hasContent();
    }
    searchDog.update(state.authenticated === true ? researchBusy ? researchActivity : state.searchActivity !== 'idle' ? state.searchActivity : researchActivity : 'idle');
    const supportsImages = controller.canAttachImage();
    byId('attach-image').disabled = blocked || state.deliveryUncertain || !supportsImages;
    byId('attach-image').title = supportsImages ? 'Dodaj PNG, JPEG lub WebP do analizy' : 'Wybierz model oznaczony tekst i obrazy';
    send.setAttribute('aria-busy', String(state.busy));
    connection.disabled = researchContinuation || researchBusy || state.busy || state.loading || Boolean(state.activeChat) || !state.connections.length;
    model.disabled = researchContinuation || researchBusy || state.busy || state.modelsLoading || Boolean(state.activeChat) || !state.models.length;
    connection.title = state.activeChat ? 'Aby zmienić połączenie, rozpocznij nową rozmowę.' : 'Wybierz dostawcę AI';
    model.title = state.activeChat ? 'Aby zmienić model, rozpocznij nową rozmowę.' : 'Model nowej rozmowy';
    byId('new-chat').disabled = researchContinuation || researchBusy || state.busy;
    byId('research-mode').disabled = researchContinuation || researchBusy || state.busy;

    status.replaceChildren(element('span', '', state.busy ? state.searchActivity === 'searching' ? 'Szukam źródeł i przygotowuję odpowiedź…' : 'Model przygotowuje odpowiedź…'
        : state.loading ? 'Wczytywanie rozmów…' : state.modelsLoading ? 'Pobieranie modeli…' : [state.error, state.notice].filter(Boolean).join(' ')));
    status.dataset.kind = state.error ? 'error' : state.busy || state.loading ? 'busy' : '';
    if (!state.modelsLoading && !state.models.length && state.selectedConnection && !state.activeChat) {
        const retry = element('button', 'btn btn-sm', 'Pobierz modele');
        retry.type = 'button';
        retry.addEventListener('click', () => controller.selectConnection(state.selectedConnection));
        status.append(retry);
    }
    if (state.retryMessageId && !state.busy && !state.deliveryUncertain) {
        const retry = element('button', 'btn btn-sm', 'Ponów');
        retry.type = 'button';
        retry.addEventListener('click', async () => { await controller.retryReply(); await researchPanel.refreshAfterReply(); });
        status.append(retry);
    }
    if (state.deliveryUncertain && !state.busy) {
        const check = element('button', 'btn btn-sm', 'Sprawdź zapis');
        check.type = 'button';
        check.addEventListener('click', () => controller.checkDelivery());
        status.append(check);
    }

    if (renderedChats !== state.chats || renderedChatId !== currentId) {
        list.replaceChildren(element('h2', 'group-label', 'Rozmowy'));
        const items = element('ul');
        state.chats.forEach(chat => {
            if (!Number.isInteger(chat.id) || chat.id <= 0) {
                return;
            }
            const item = element('li');
            const button = element('button', 'chat-item');
            button.type = 'button';
            button.dataset.chatId = String(chat.id);
            button.append(element('span', '', chat.title || 'Rozmowa'));
            if (chat.id === currentId) {
                button.setAttribute('aria-current', 'true');
            }
            button.addEventListener('click', async () => {
                if (await controller.openChat(chat.id) && mobile.matches) {
                    setSidebar(false);
                }
            });
            item.append(button);
            items.append(item);
        });
        list.append(items);
        renderedChats = state.chats;
        renderedChatId = currentId;
    }
    list.querySelectorAll('button').forEach(button => { button.disabled = state.busy || researchBusy; });

    if (renderedConnections !== state.connections || renderedConnection !== state.selectedConnection) {
        connection.replaceChildren(...state.connections.map(item => new Option(item.name, item.id)));
        if (!state.connections.length) {
            connection.append(new Option('Wczytywanie połączeń…', ''));
        }
        connection.value = state.selectedConnection;
        renderedConnections = state.connections;
        renderedConnection = state.selectedConnection;
    }
    if (renderedModels !== state.models || renderedModel !== state.selectedModel || renderedModelsLoading !== state.modelsLoading) {
        model.replaceChildren();
        state.models.forEach(item => {
            const label = (item.name || item.id) + (item.generates_images ? ' · generowanie obrazów' : item.supports_images ? ' · tekst i obrazy' : '');
            const option = new Option(label, item.id);
            option.title = item.description || '';
            model.append(option);
        });
        if (state.activeChat && state.selectedModel && !state.models.some(item => item.id === state.selectedModel)) {
            model.append(new Option('Model zapisanej rozmowy — niedostępny', state.selectedModel));
        }
        if (!model.options.length) {
            model.append(new Option(state.modelsLoading ? 'Pobieranie modeli…' : 'Brak dostępnych modeli', ''));
        }
        model.value = state.selectedModel;
        renderedModels = state.models;
        renderedModel = state.selectedModel;
        renderedModelsLoading = state.modelsLoading;
    }

    if (renderedImages !== state.images) {
        const preview = byId('image-preview');
        preview.replaceChildren();
        if (state.images.length && isInlineImage(state.images[0])) {
            const picture = element('img');
            picture.src = state.images[0];
            picture.alt = 'Podgląd obrazu do analizy';
            const remove = element('button', 'btn btn-sm', 'Usuń obraz');
            remove.type = 'button';
            remove.addEventListener('click', () => controller.removeImage());
            preview.append(picture, element('span', '', 'Obraz gotowy do analizy'), remove);
        }
        renderedImages = state.images;
    }
    byId('image-preview').querySelectorAll('button').forEach(button => { button.disabled = blocked || state.deliveryUncertain; });

    if (renderedMessages !== state.messages || renderedPending !== state.pendingMessage || renderedBusy !== state.busy) {
        const nearBottom = messages.scrollHeight - messages.scrollTop - messages.clientHeight < 100;
        const changedChat = renderedMessages !== state.messages;
        thread.replaceChildren(...state.messages.map(messageNode));
        if (state.pendingMessage) {
            thread.append(messageNode(state.pendingMessage));
        }
        if (state.busy) {
            thread.append(element('p', 'pending-reply', 'Oczekiwanie na odpowiedź…'));
        }
        renderedMessages = state.messages;
        renderedPending = state.pendingMessage;
        renderedBusy = state.busy;
        if (nearBottom || changedChat || state.pendingMessage) {
            requestAnimationFrame(() => messages.scrollTo({ top: messages.scrollHeight }));
        }
    }
}

const controller = new ChatController(new ChatApi({
    getSession: () => {
        try { return localStorage.getItem('token') || ''; } catch { return ''; }
    },
}), render, new ChatPreferences(), Array.from(connection.options)
    .filter(option => option.value).map(option => ({ id: option.value, name: option.textContent })));

setSidebar(!mobile.matches);
mobile.addEventListener('change', () => setSidebar(!mobile.matches));
document.addEventListener('click', event => {
    if (event.target.closest('[data-toggle-sidebar]')) {
        setSidebar(app.dataset.sidebar !== 'open');
    }
    const suggestion = event.target.closest('[data-suggest]');
    if (suggestion && !input.disabled) {
        controller.setDraft(suggestion.dataset.suggest);
        autosize();
        input.focus();
    }
});
document.addEventListener('keydown', event => {
    if (event.key === 'Escape' && mobile.matches && app.dataset.sidebar === 'open') {
        setSidebar(false);
        document.querySelector('.open-side').focus();
    }
});
byId('new-chat').addEventListener('click', () => {
    if (!controller.newChat()) {
        return;
    }
    if (mobile.matches) {
        setSidebar(false);
    }
    input.focus();
});
connection.addEventListener('change', () => controller.selectConnection(connection.value));
model.addEventListener('change', () => controller.selectModel(model.value));
enhanceModelPicker(model);
input.addEventListener('input', () => { controller.setDraft(input.value); autosize(); });
async function sendDraft() {
    if (preparingImage || researchBusy || controller.state.busy || !controller.state.draft.trim() && !controller.state.images.length) {
        return;
    }
    const useResearch = byId('research-mode').checked;
    if (useResearch && !await researchPanel.beforeSend()) { researchModal.open(); return; }
    await controller.send(null, useResearch);
    if (useResearch) {
        await researchPanel.refreshAfterReply();
    }
    if (!input.disabled) {
        input.focus();
    }
}
input.addEventListener('keydown', event => handleComposerKey(event, sendDraft));
byId('chat-form').addEventListener('submit', event => {
    event.preventDefault();
    sendDraft();
});
researchPanel = new ResearchPanel(controller.api, {
    query: byId('search-query'), search: byId('search-send'), body: byId('research-body'), summary: byId('research-summary'),
    continueControl: byId('research-continue'), continueLabel: 'Kontynuuj',
    onState: state => researchModal.update(state),
    onContinuationChange: busy => { if (busy) researchModal.close(); researchContinuation = busy; render(controller.state); },
    onBusy: busy => { researchBusy = busy; render(controller.state); },
    onNotice: (text, error = false) => {
        if (!researchContinuation) {
            researchModal.open();
        }
        controller.update(error ? { error: text, notice: '' } : { notice: text, error: '' });
    },
    onActivity: activity => { researchActivity = activity; render(controller.state); },
    canContinue: () => !preparingImage && !controller.state.busy && !controller.state.loading
        && controller.state.authenticated === true && !controller.state.modelsLoading
        && !controller.state.deliveryUncertain && Boolean(controller.state.activeChat || controller.state.selectedModel),
    onContinue: async () => {
        const last = controller.state.messages.at(-1);
        const waitingForContinuation = !controller.state.draft.trim()
            && controller.state.messages.some(message => message.role === 'user')
            && (!last || last.role === 'user' || last.kind === 'approval_request');
        if (waitingForContinuation) {
            await controller.continueReply();
            await researchPanel.refreshAfterReply();
        } else {
            await continueResearch(controller, () => { byId('research-mode').checked = true; }, sendDraft);
        }
    },
    onResume: () => sendDraft(),
    onAgent: async () => {
        if (!await controller.continueReply()) {
            controller.update({ notice: 'Decyzja zapisana. Użyj Kontynuuj, aby ponowić odpowiedź.' });
        }
        await researchPanel.refreshAfterReply();
    },
});
render(controller.state);
await controller.load();

byId('attach-image').addEventListener('click', () => byId('image-file').click());
byId('image-file').addEventListener('change', async event => {
    const file = event.target.files[0];
    event.target.value = '';
    if (!file || preparingImage || !controller.canAttachImage()) {
        return;
    }
    const version = controller.selectionVersion;
    const selected = controller.state.activeChat?.model || controller.state.selectedModel;
    preparingImage = true;
    controller.update({ notice: 'Przygotowywanie obrazu…', error: '' });
    try {
        const source = await prepareImage(file);
        if (version !== controller.selectionVersion
            || selected !== (controller.state.activeChat?.model || controller.state.selectedModel)) {
            return;
        }
        if (controller.setImage(source)) {
            controller.update({ notice: '' });
        }
    } catch {
        if (version === controller.selectionVersion) {
            controller.update({ error: 'Nie udało się dodać obrazu. Wybierz PNG, JPEG lub WebP do 20 MB.', notice: '' });
        }
    } finally {
        preparingImage = false;
        render(controller.state);
    }
});
