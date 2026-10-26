export function formatUsage(usage) {
    if (!usage || typeof usage.prompt_tokens !== 'number' || typeof usage.completion_tokens !== 'number') {
        return null;
    }
    const fmt = new Intl.NumberFormat('pl-PL');
    return `wej. ${fmt.format(usage.prompt_tokens)} / wyj. ${fmt.format(usage.completion_tokens)}`;
}

export function appendMessageUsage(actions, message, dom = document) {
    const text = formatUsage(message?.usage);
    if (!text) {
        return null;
    }
    const node = dom.createElement('span');
    node.className = 'msg-usage';
    node.textContent = text;
    actions.append(node);
    return node;
}
