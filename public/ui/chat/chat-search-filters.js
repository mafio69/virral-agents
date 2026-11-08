const MAX_TOKENS = 10;
const TOKEN_PATTERN = /^[\p{L}\p{N}][^"\x00-\x1F\x7F]*$/u;
// Same host shape the backend accepts (src/Dto/Chat/SearchQuery::assertSite).
const SITE_PATTERN = /^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?(\.[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?)+$/;

// Accepts a pasted URL or path; the backend only ever sees the host part.
const normalizeSite = value => value.trim().toLowerCase().replace(/^[a-z][a-z0-9+.-]*:\/\//, '').split(/[/?#]/)[0];

/** Token chip with a click-to-remove affordance. */
const chip = (text, onRemove) => {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'chip';
    button.textContent = text;
    button.title = 'Kliknij, aby usunąć';
    button.addEventListener('click', onRemove);
    return button;
};

/** Advanced search filters UI: chips for excluded/required words, exact phrase and site.
 *  The user never sees search syntax; the backend composes the query (T6.1). */
export class SearchFilters {
    constructor({ excludeInput, excludeChips, mustInput, mustChips, phraseInput, siteInput, moreToggle, morePanel }) {
        Object.assign(this, { excludeInput, excludeChips, mustInput, mustChips, phraseInput, siteInput, moreToggle, morePanel });
        this.exclude = [];
        this.mustHave = [];
        this.inputs = [excludeInput, mustInput, phraseInput, siteInput];
        this.bindTokens(excludeInput, () => this.exclude, excludeChips, tokens => { this.exclude = tokens; });
        this.bindTokens(mustInput, () => this.mustHave, mustChips, tokens => { this.mustHave = tokens; });
        moreToggle.addEventListener('click', () => {
            const open = morePanel.hidden;
            morePanel.hidden = !open;
            moreToggle.setAttribute('aria-expanded', String(open));
        });
    }

    /** Enter adds the input value as a chip; invalid tokens are ignored. */
    bindTokens(input, current, chips, replace) {
        input.addEventListener('keydown', event => {
            if (event.key !== 'Enter') {
                return;
            }
            event.preventDefault();
            const token = input.value.trim().replace(/\s+/g, ' ');
            if (token === '' || !TOKEN_PATTERN.test(token) || token.length > 64 || current().includes(token) || current().length >= MAX_TOKENS) {
                return;
            }
            replace([...current(), token]);
            input.value = '';
            this.renderChips(current, chips, replace);
        });
    }

    /** Re-renders the chip list after tokens change. */
    renderChips(current, chips, replace) {
        chips.replaceChildren(...current().map(token => chip(token, () => {
            replace(current().filter(item => item !== token));
            this.renderChips(current, chips, replace);
        })));
    }

    /** Non-empty filter keys only; merged into the search request by ResearchPanel.
     *  Values are normalized like the backend does, so what the consent card shows
     *  is exactly what will be sent. Returns null when the site stays invalid. */
    collect() {
        const filters = {};
        if (this.mustHave.length) {
            filters.must_have = [...this.mustHave];
        }
        if (this.exclude.length) {
            filters.exclude = [...this.exclude];
        }
        const phrase = this.phraseInput.value.trim().replace(/\s+/g, ' ').replace(/^"(.*)"$/, '$1');
        if (phrase) {
            filters.exact_phrase = phrase;
        }
        this.siteInput.setCustomValidity?.('');
        this.siteInput.setAttribute('aria-invalid', 'false');
        if (this.siteInput.value.trim() !== '') {
            const site = normalizeSite(this.siteInput.value);
            if (!SITE_PATTERN.test(site)) {
                this.siteInput.setCustomValidity?.('Podaj domenę, np. wikipedia.org');
                this.siteInput.setAttribute('aria-invalid', 'true');
                this.siteInput.reportValidity?.();
                return null;
            }
            filters.sites = [site];
        }
        return filters;
    }

    /** Disables or enables every filter input. */
    setEnabled(disabled) {
        for (const input of this.inputs) {
            input.disabled = disabled;
        }
    }
}
