const empty = () => ({ results: [], sources: [], actions: [] });
const node = (tag, text, className = '') => {
    const el = document.createElement(tag);
    if (text !== undefined) el.textContent = text;
    if (className) el.className = className;
    return el;
};

/** Durable server state; result replacement never clears the selected source shelf. */
export class ResearchPanel {
    constructor(api, { query, search, body, summary, onBusy = () => {}, onNotice = () => {}, onResume = () => {}, onAgent = () => {}, onActivity = () => {}, onContinue = () => {}, onContinuationChange = () => {}, onState = () => {}, continueControl = null, continueLabel = 'Kontynuuj z wybranymi stronami', canContinue = () => true }) {
        Object.assign(this, { api, query, search, body, summary, onBusy, onNotice, onResume, onAgent, onActivity, onContinue, onContinuationChange, onState, continueControl, continueLabel, canContinue });
        this.scope = null;
        this.version = 0;
        this.busy = false;
        this.ready = false;
        this.state = empty();
        this.resume = false;
        this.continuing = false;
        this.continuationStage = '';
        this.continueButton = null;
        this.refreshQueued = false;
        this.bulkApproval = null;
        continueControl?.addEventListener('click', () => this.continueWithSources());
        query.addEventListener('input', () => this.updateButton());
        search.addEventListener('click', () => {
            const text = query.value.trim();
            if (!this.canSearch()) return;
            void this.run('propose', { request: { kind: 'search', query: text } }).then(ok => {
                if (ok) this.onNotice('Sprawdź dokładne zapytanie poniżej i wybierz Allow lub Deny.');
            });
        });
        this.render();
    }

    canSearch() { return this.scope !== null && this.ready && !this.busy && !this.continuing && this.query.value.trim() !== '' && new TextEncoder().encode(this.query.value.trim()).length <= 1000; }
    updateButton() {
        this.search.disabled = !this.canSearch();
        this.query.disabled = this.scope === null || this.busy;
        if (this.continueButton) {
            this.continueButton.disabled = !this.ready || this.busy || this.continuing || this.scope === null || !this.state.sources.length || !this.canContinue();
            this.continueButton.setAttribute('aria-busy', String(this.continuing));
            this.continueButton.textContent = this.continuing
                ? this.continuationStage === 'ai' ? 'AI przygotowuje odpowiedź…' : 'Pobieram wybrane strony…'
                : this.continueLabel;
        }
    }

    async continueWithSources() {
        if (!this.ready || this.busy || this.continuing || this.scope === null || !this.state.sources.length || !this.canContinue()) return;
        const version = this.version;
        // The explicit click approves fetching only the source URLs visible in this selection.
        const approved = new Set(this.state.sources.map(source => source.url));
        this.continuing = true;
        this.continuationStage = 'fetch';
        this.onContinuationChange(true);
        this.resume = false;
        this.onActivity('searching');
        this.render();
        try {
            if (this.state.sources.some(source => source.page.status === 'unread')) {
                if (!await this.run('prepare', {}) || version !== this.version) return;
            }
            const fetches = this.pendingFetches().filter(action => approved.has(action.request.url));
            for (const action of fetches) {
                if (version !== this.version) return;
                if (!this.state.sources.some(source => source.url === action.request.url)) continue;
                if (!await this.run('decide', { id: action.id, allow: true }) || version !== this.version) return;
            }
            if (version !== this.version) return;
            this.continuationStage = 'ai';
            this.updateButton();
            this.onActivity('done');
            await this.onContinue();
        } finally {
            this.continuing = false;
            this.continuationStage = '';
            this.onContinuationChange(false);
            this.onActivity('idle');
            this.render();
        }
    }

    async setScope(scope) {
        if (scope === this.scope) return;
        const version = ++this.version;
        this.scope = scope;
        this.bulkApproval = null;
        this.resume = false;
        this.ready = false;
        this.state = empty();
        this.render();
        if (scope === null) return;
        this.busy = true;
        this.onBusy(true);
        try {
            const state = await this.api.research('state', scope);
            if (version !== this.version) return;
            this.state = state;
            this.ready = true;
        } catch { if (version === this.version) this.onNotice('Nie udało się wczytać źródeł. Spróbuj ponownie przyciskiem Odśwież listę.', true); }
        finally { if (version === this.version) { this.busy = false; this.onBusy(false); this.render();
            if (this.refreshQueued) { this.refreshQueued = false; await this.refresh(); }
        } }
    }

    async refresh() {
        if (this.busy) { this.refreshQueued = true; return false; }
        if (this.scope === null) return false;
        const scope = this.scope;
        this.scope = null;
        await this.setScope(scope);
        return this.ready;
    }

    async run(operation, payload) {
        if (this.busy || this.scope === null) return false;
        const scope = this.scope, version = this.version;
        this.busy = true;
        this.onBusy(true);
        this.render();
        try {
            const state = await this.api.research(operation, scope, payload);
            if (version !== this.version) return false;
            this.state = state;
            this.ready = true;
            return true;
        } catch {
            this.onNotice('Operacja źródeł nie powiodła się. Odśwież listę przed ponowną decyzją; niczego nie ponawiam automatycznie.', true);
            this.resume = false;
            return false;
        } finally {
            if (version === this.version) { this.busy = false; this.onBusy(false); this.render();
                if (this.refreshQueued) { this.refreshQueued = false; await this.refresh(); }
            }
        }
    }

    async beforeSend() {
        if (!this.ready || this.busy) return false;
        if (this.state.sources.some(source => source.page.status === 'unread')) {
            this.resume = true;
            if (!await this.run('prepare', {})) { this.resume = false; return false; }
        }
        const waiting = this.state.actions.some(action => ['pending', 'running'].includes(action.status));
        if (waiting) this.onNotice('Wybierz Allow lub Deny przy oczekujących operacjach. Sam checkbox nie zezwala na pobranie strony.');
        return !waiting;
    }

    async decide(action, allow) {
        if (this.busy) return;
        this.onActivity(allow ? 'searching' : 'idle');
        const ok = await this.run('decide', { id: action.id, allow });
        this.onActivity(ok && allow ? this.state.actions.find(item => item.id === action.id)?.status === 'failed' ? 'error' : 'done' : 'idle');
        if (!ok) return;
        const failed = this.state.actions.find(item => item.id === action.id)?.status === 'failed';
        if (failed) {
            this.onNotice('Operacja WWW nie powiodła się. Sprawdź stan poniżej; ponowienie wymaga nowej zgody.', true);
            if (action.request.kind !== 'fetch') return;
        }
        const waiting = this.state.actions.some(item => ['pending', 'running'].includes(item.status));
        if (this.resume && !waiting) {
            this.resume = false;
            await this.onResume();
        } else if (action.origin === 'ai' && action.request.kind === 'fetch' && !waiting) {
            await this.onAgent(allow);
        } else if (allow && action.request.kind === 'search' && !failed) {
            this.onNotice(this.state.results.length ? 'Wyniki są domyślnie zaznaczone do limitu 20 źródeł. Odznacz niepotrzebne i kliknij „Kontynuuj z wybranymi stronami”.' : 'Brak użytecznych wyników. Sprawdź stan operacji poniżej.');
        } else if (!allow) this.onNotice('Odmowa zapisana. Operacja WWW nie została wykonana.');
    }

    pendingFetches() {
        const selected = new Set(this.state.sources.map(source => source.url));
        return this.state.actions.filter(action => action.status === 'pending' && action.request.kind === 'fetch' && selected.has(action.request.url));
    }

    async fetchAll() {
        if (this.busy || !this.bulkApproval) return;
        const approved = this.bulkApproval, version = this.version;
        this.bulkApproval = null;
        this.onActivity('searching');
        let failures = 0;
        for (const action of approved) {
            if (version !== this.version) return;
            if (!this.pendingFetches().some(item => item.id === action.id && item.request.url === action.request.url)) continue;
            if (!await this.run('decide', { id: action.id, allow: true })) {
                this.resume = false;
                this.onActivity('error');
                this.onNotice('Nie można potwierdzić stanu operacji. Odśwież listę przed kontynuacją.', true);
                return;
            }
            if (this.state.actions.find(item => item.id === action.id)?.status === 'failed') failures++;
        }
        this.onActivity('done');
        if (failures) this.onNotice(`Nie udało się pobrać ${failures} stron. Opracowanie może korzystać z pozostałych przeczytanych źródeł.`);
        if (this.resume && !this.state.actions.some(item => ['pending', 'running'].includes(item.status))) {
            this.resume = false;
            await this.onResume();
        }
    }

    render() {
        this.updateButton();
        this.summary.textContent = `Źródła do badania (${this.state.sources.length}/20)`;
        this.body.replaceChildren();
        this.continueButton = this.continueControl;
        this.updateButton();
        const refresh = node('button', 'Odśwież listę', 'btn btn-sm');
        refresh.type = 'button'; refresh.disabled = this.busy || this.scope === null;
        refresh.addEventListener('click', () => this.refresh());
        this.body.append(refresh);
        if (!this.ready) this.body.append(node('p', this.busy ? 'Wczytywanie źródeł…' : 'Zaloguj się, aby zbierać źródła.'));
        if (this.state.sources.length) {
            const button = this.continueControl || node('button', this.continueLabel, 'btn btn-silver btn-sm');
            button.type = 'button';
            if (!this.continueControl) button.addEventListener('click', () => this.continueWithSources());
            this.continueButton = button;
            if (!this.continueControl) this.body.append(button);
            this.body.append(node('p', 'Kliknięcie pobierze treść zaznaczonych stron i przekaże ją wybranemu dostawcy AI. Bez dodatkowego potwierdzania pobrań. Odznaczone strony zostaną pominięte.'));
            this.updateButton();
        }
        const pending = this.state.actions.filter(action => action.status === 'pending' && !(this.continuing && action.request.kind === 'fetch' && this.state.sources.some(source => source.url === action.request.url)));
        pending.forEach(action => {
            const card = node('section', undefined, 'research-permission');
            const request = action.request;
            card.append(node('strong', `${action.origin === 'ai' ? 'AI prosi o zgodę' : 'Zgoda'}: ${request.kind === 'search' ? 'wyszukanie WWW' : 'pobranie strony'}`));
            card.append(node('p', request.query || request.url, 'research-address'));
            card.append(node('p', request.kind === 'search'
                ? 'Zapytanie trafi do MCP → SearXNG, awaryjnie Perplexity. Wyniki pojawią się tutaj; dopiero zaznaczone źródła przekażesz AI.'
                : 'Ten adres otrzyma żądanie GET bez Twoich cookies. Pobrany tekst może trafić do wybranego dostawcy AI po wysłaniu polecenia. Każde przekierowanie wymaga nowej zgody.'));
            for (const [allow, label] of [[true, 'Allow — zezwól'], [false, 'Deny — odmów']]) {
                const button = node('button', label, 'btn btn-sm'); button.type = 'button'; button.disabled = this.busy;
                button.addEventListener('click', () => this.decide(action, allow)); card.append(button);
            }
            this.body.append(card);
        });
        this.state.actions.filter(action => ['failed', 'running'].includes(action.status)).slice(-3).forEach(action => {
            this.body.append(node('p', `${action.status === 'failed' ? 'Nie udało się wykonać operacji' : 'Operacja w toku — nie ponawiaj decyzji'}: ${action.request.query || action.request.url}${action.reason ? ' (' + action.reason + ')' : ''}`, 'research-address'));
        });
        if (this.state.results.length) {
            this.body.append(node('h3', 'Wyniki ostatniego udanego wyszukiwania'));
            this.state.results.forEach(source => this.body.append(this.resultNode(source)));
        }
        const earlierSources = this.state.sources.filter(source => !this.state.results.some(result => result.id === source.id));
        if (earlierSources.length) this.body.append(node('h3', 'Źródła z wcześniejszych wyszukiwań'));
        if (!this.state.sources.length) this.body.append(node('p', 'Nowe wyniki będą domyślnie zaznaczone. Wybór pozostaje na liście podczas kolejnych wyszukiwań.'));
        earlierSources.forEach(source => {
            const row = node('div', undefined, 'research-source');
            const link = node('a', source.title || source.url); link.href = source.url; link.target = '_blank'; link.rel = 'noopener noreferrer';
            const labels = { unread: 'Czeka na zgodę i pobranie', loaded: source.page.truncated ? 'Tekst pobrany (skrócony)' : 'Tekst pobrany', denied: 'Odmowa — nie przeczytano', failed: 'Nie udało się pobrać' };
            row.append(link, node('small', labels[source.page.status] || 'Nie przeczytano'));
            if (['denied', 'failed'].includes(source.page.status)) {
                const retry = node('button', 'Poproś ponownie o pobranie…', 'btn btn-sm'); retry.type = 'button'; retry.disabled = this.busy || this.continuing;
                retry.addEventListener('click', () => this.run('propose', { request: { kind: 'fetch', url: source.url } })); row.append(retry);
            }
            const remove = node('button', 'Usuń z listy', 'btn btn-sm'); remove.type = 'button'; remove.disabled = this.busy || this.continuing;
            remove.addEventListener('click', () => this.run('select', { id: source.id, selected: false })); row.append(remove);
            this.body.append(row);
        });
        this.onState(this.state);
    }

    resultNode(source) {
        const row = node('label', undefined, 'research-result');
        const checkbox = node('input'); checkbox.type = 'checkbox'; checkbox.checked = this.state.sources.some(item => item.id === source.id); checkbox.disabled = this.busy || this.continuing;
        checkbox.setAttribute('aria-label', `Dodaj źródło: ${source.title || source.url}`);
        checkbox.addEventListener('change', () => this.run('select', { id: source.id, selected: checkbox.checked }));
        const text = node('span'); text.append(node('strong', source.title || source.url), node('small', source.url), node('span', source.snippet));
        const selected = this.state.sources.find(item => item.id === source.id);
        if (selected) text.append(node('small', selected.page.status === 'loaded' ? 'Treść pobrana i zachowana' : selected.page.status === 'failed' ? 'Pobranie nie powiodło się' : 'Wybrane do opracowania'));
        row.append(checkbox, text); return row;
    }
}

/** Continue the visible research task without requiring a manually typed follow-up. */
export async function continueResearch(controller, enableResearch, sendDraft) {
    enableResearch();
    if (!controller.state.draft.trim()) {
        controller.setDraft(controller.state.messages.some(message => message.role === 'user')
            ? 'Kontynuuj poprzednie zadanie na podstawie wybranych źródeł.'
            : 'Opracuj wybrane źródła, podsumuj najważniejsze informacje i podaj odnośniki.');
    }
    await sendDraft();
}
