/** Approval is obtained for this query only; it is never remembered. */
export function approveSearch(query, confirm = globalThis.confirm) {
    const text = query.trim();
    if (!text || new TextEncoder().encode(text).length > 1000) return null;
    const accepted = confirm(`Zezwolić na jedno wyszukanie WWW?\n\nZapytanie: ${text}\n\nOdbiorcy: MCP Search → SearXNG; po błędzie lub braku wyników Perplexity. Wyniki trafią do wybranego dostawcy AI. Nie wysyłaj danych prywatnych. Strony nie będą pobierane przez fetch.`);
    return accepted === true ? { engine: 'mcp', query: text, approved: true, max_calls: 1 } : null;
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
    const update = (state = controller.state) => {
        button.disabled = !canSearch(state, query.value, isPreparing());
        query.disabled = isPreparing() || state.busy || state.loading || state.authenticated !== true;
    };
    query.addEventListener('input', () => update());
    button.addEventListener('click', async () => {
        if (!canSearch(controller.state, query.value, isPreparing())) return;
        const search = approve(query.value);
        if (!search) return;
        if (await controller.send(search)) {
            query.value = '';
            onSent();
        }
        update();
    });
    update();
    return { update };
}
