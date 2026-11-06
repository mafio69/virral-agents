/** Approval is obtained for this query only; it is never remembered. */
export function approveSearch(query, confirm = () => false) {
    const text = query.trim();
    if (!text || new TextEncoder().encode(text).length > 1000) {
        return null;
    }
    const accepted = confirm(`Zezwolić na jedno wyszukanie WWW?\n\nZapytanie: ${text}\n\nOdbiorcy: MCP Search → SearXNG; po błędzie lub braku wyników Perplexity. Wyniki trafią do wybranego dostawcy AI. Nie wysyłaj danych prywatnych. Strony nie będą pobierane przez fetch.`);
    return accepted === true ? { engine: 'mcp', query: text, approved: true, max_calls: 1 } : null;
}

/** Human-readable filter summary; the user never sees search syntax. */
export function describeSearchFilters(search) {
    const parts = [];
    if (Array.isArray(search?.must_have) && search.must_have.length) {
        parts.push(`Musi zawierać: ${search.must_have.join(', ')}`);
    }
    if (Array.isArray(search?.exclude) && search.exclude.length) {
        parts.push(`Bez: ${search.exclude.join(', ')}`);
    }
    if (typeof search?.exact_phrase === 'string' && search.exact_phrase.trim()) {
        parts.push(`Dokładna fraza: „${search.exact_phrase.trim()}”`);
    }
    if (Array.isArray(search?.sites) && search.sites.length) {
        parts.push(`Strony: ${search.sites.join(', ')}`);
    }
    return parts.join(' · ');
}

/** Inline consent works in embedded browsers that suppress native confirm dialogs. */
export function createSearchApproval({ panel, queryText, allow, deny }) {
    let pending = null;
    const finish = accepted => {
        if (!pending) {
            return;
        }
        const { query, resolve } = pending;
        pending = null;
        panel.hidden = true;
        resolve(accepted ? approveSearch(query, () => true) : null);
    };
    allow.addEventListener('click', () => finish(true));
    deny.addEventListener('click', () => finish(false));
    panel.addEventListener('keydown', event => {
        if (event.key === 'Escape') { event.preventDefault(); finish(false); }
    });
    return {
        request(query) {
            const text = query.trim();
            if (pending || !text || new TextEncoder().encode(text).length > 1000) {
                return Promise.resolve(null);
            }
            queryText.textContent = text;
            panel.hidden = false;
            deny.focus();
            return new Promise(resolve => { pending = { query: text, resolve }; });
        },
        cancel: () => finish(false),
    };
}

export function canSearch(state, query, preparingImage = false) {
    const text = query.trim();
    return !preparingImage && !state.busy && !state.loading && !state.modelsLoading
        && state.authenticated === true && !state.deliveryUncertain
        && Boolean(state.activeChat || state.selectedModel)
        && text !== '' && new TextEncoder().encode(text).length <= 1000;
}

/** The query field, rather than the message composer, controls search availability. */
export function bindSearchControls({ query, button, controller, isPreparing = () => false,
    approve = approveSearch, onSent = () => {} }) {
    let awaitingApproval = false;
    const update = (state = controller.state) => {
        button.disabled = awaitingApproval || !canSearch(state, query.value, isPreparing());
        query.disabled = awaitingApproval || isPreparing() || state.busy || state.loading || state.authenticated !== true;
    };
    query.addEventListener('input', () => update());
    button.addEventListener('click', async () => {
        if (awaitingApproval || !canSearch(controller.state, query.value, isPreparing())) {
            return;
        }
        const version = controller.selectionVersion;
        const chatId = controller.state.activeChat?.id;
        const model = controller.state.selectedModel;
        awaitingApproval = true;
        update();
        try {
            const search = await approve(query.value);
            if (!search) {
                controller.update({ notice: 'Wyszukiwanie anulowane. Nie wysłano zapytania WWW.', error: '' });
                return;
            }
            if (version !== controller.selectionVersion || chatId !== controller.state.activeChat?.id
                || model !== controller.state.selectedModel || search.query !== query.value.trim()
                || !canSearch(controller.state, query.value, isPreparing())) {
                controller.update({ notice: 'Zmieniła się rozmowa lub zapytanie. Zatwierdź wyszukanie ponownie.', error: '' });
                return;
            }
            if (await controller.send(search)) {
                query.value = '';
                onSent();
            }
        } catch {
            controller.update({ error: 'Nie udało się uruchomić wyszukiwania. Spróbuj ponownie.', notice: '' });
        } finally {
            awaitingApproval = false;
            update();
        }
    });
    update();
    return { update };
}
