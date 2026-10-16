const empty = () => ({ results: [], sources: [], actions: [] });
const node = (tag, text, className = '') => {
    const el = document.createElement(tag);
    if (text !== undefined) el.textContent = text;
    if (className) el.className = className;
    return el;
};

/** Durable server state; result replacement never clears the selected source shelf. */
export class ResearchPanel {
    constructor(api, { query, search, body, summary, onBusy = () => {}, onNotice = () => {}, onResume = () => {}, onAgent = () => {}, onActivity = () => {} }) {
        Object.assign(this, { api, query, search, body, summary, onBusy, onNotice, onResume, onAgent, onActivity });
        this.scope = null;
        this.version = 0;
        this.busy = false;
        this.ready = false;
        this.state = empty();
        this.resume = false;
        this.refreshQueued = false;
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

    canSearch() { return this.scope !== null && this.ready && !this.busy && this.query.value.trim() !== '' && new TextEncoder().encode(this.query.value.trim()).length <= 1000; }
    updateButton() { this.search.disabled = !this.canSearch(); this.query.disabled = this.scope === null || this.busy; }

    async setScope(scope) {
        if (scope === this.scope) return;
        const version = ++this.version;
        this.scope = scope;
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
            if (!await this.run('prepare', {})) this.resume = false;
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
        if (this.state.actions.find(item => item.id === action.id)?.status === 'failed') {
            this.resume = false;
            this.onNotice('Operacja WWW nie powiodła się. Sprawdź stan poniżej; ponowienie wymaga nowej zgody.', true);
            return;
        }
        const waiting = this.state.actions.some(item => ['pending', 'running'].includes(item.status));
        if (this.resume && !waiting) {
            this.resume = false;
            await this.onResume();
        } else if (action.origin === 'ai' && action.request.kind === 'fetch' && !waiting) {
            await this.onAgent(allow);
        } else if (allow && action.request.kind === 'search') {
            this.onNotice(this.state.results.length ? 'Zaznacz strony do listy źródeł. Możesz wpisać kolejne zapytanie i szukać dalej.' : 'Brak użytecznych wyników. Sprawdź stan operacji poniżej.');
        } else if (!allow) this.onNotice('Odmowa zapisana. Operacja WWW nie została wykonana.');
    }

    render() {
        this.updateButton();
        this.summary.textContent = `Źródła do badania (${this.state.sources.length}/20)`;
        this.body.replaceChildren();
        const refresh = node('button', 'Odśwież listę', 'btn btn-sm');
        refresh.type = 'button'; refresh.disabled = this.busy || this.scope === null;
        refresh.addEventListener('click', () => this.refresh());
        this.body.append(refresh);
        if (!this.ready) this.body.append(node('p', this.busy ? 'Wczytywanie źródeł…' : 'Zaloguj się, aby zbierać źródła.'));
        const pending = this.state.actions.filter(action => action.status === 'pending');
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
        this.body.append(node('h3', 'Twoja lista źródeł'));
        if (!this.state.sources.length) this.body.append(node('p', 'Zaznacz checkboxy przy wynikach. Wybór pozostaje na liście podczas kolejnych wyszukiwań.'));
        this.state.sources.forEach(source => {
            const row = node('div', undefined, 'research-source');
            const link = node('a', source.title || source.url); link.href = source.url; link.target = '_blank'; link.rel = 'noopener noreferrer';
            const labels = { unread: 'Czeka na zgodę i pobranie', loaded: source.page.truncated ? 'Tekst pobrany (skrócony)' : 'Tekst pobrany', denied: 'Odmowa — nie przeczytano', failed: 'Nie udało się pobrać' };
            row.append(link, node('small', labels[source.page.status] || 'Nie przeczytano'));
            if (['denied', 'failed'].includes(source.page.status)) {
                const retry = node('button', 'Poproś ponownie o pobranie…', 'btn btn-sm'); retry.type = 'button'; retry.disabled = this.busy;
                retry.addEventListener('click', () => this.run('propose', { request: { kind: 'fetch', url: source.url } })); row.append(retry);
            }
            const remove = node('button', 'Usuń z listy', 'btn btn-sm'); remove.type = 'button'; remove.disabled = this.busy;
            remove.addEventListener('click', () => this.run('select', { id: source.id, selected: false })); row.append(remove);
            this.body.append(row);
        });
        if (this.state.sources.length) {
            const prepare = node('button', 'Przeczytaj wybrane strony…', 'btn btn-sm'); prepare.type = 'button'; prepare.disabled = this.busy;
            prepare.addEventListener('click', () => this.run('prepare', {})); this.body.append(prepare);
            this.body.append(node('p', 'Gdy lista jest gotowa, wpisz w wiadomości temat i polecenie opracowania. Przed pobraniem każdej strony zobaczysz osobne Allow / Deny.'));
        }
    }

    resultNode(source) {
        const row = node('label', undefined, 'research-result');
        const checkbox = node('input'); checkbox.type = 'checkbox'; checkbox.checked = this.state.sources.some(item => item.id === source.id); checkbox.disabled = this.busy;
        checkbox.setAttribute('aria-label', `Dodaj źródło: ${source.title || source.url}`);
        checkbox.addEventListener('change', () => this.run('select', { id: source.id, selected: checkbox.checked }));
        const text = node('span'); text.append(node('strong', source.title || source.url), node('small', source.url), node('span', source.snippet));
        row.append(checkbox, text); return row;
    }
}
