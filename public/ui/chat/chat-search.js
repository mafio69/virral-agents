/** Approval is obtained for this query only; it is never remembered. */
export function approveSearch(query, confirm = globalThis.confirm) {
    const text = query.trim();
    if (!text || new TextEncoder().encode(text).length > 1000) return null;
    const accepted = confirm(`Zezwolić na jedno wyszukanie WWW?\n\nZapytanie: ${text}\n\nOdbiorcy: MCP Search → SearXNG; po błędzie lub braku wyników Perplexity. Wyniki trafią do wybranego dostawcy AI. Nie wysyłaj danych prywatnych. Strony nie będą pobierane przez fetch.`);
    return accepted === true ? { engine: 'mcp', query: text, approved: true, max_calls: 1 } : null;
}
