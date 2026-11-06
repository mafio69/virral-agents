const MAX_TOKENS = 10;
const TOKEN_PATTERN = /^[\p{L}\p{N}][^"\x00-\x1F\x7F]*$/u;

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

    renderChips(current, chips, replace) {
        chips.replaceChildren(...current().map(token => chip(token, () => {
            replace(current().filter(item => item !== token));
            this.renderChips(current, chips, replace);
        })));
    }

    /** Non-empty filter keys only; merged into the search request by ResearchPanel. */
    collect() {
        const filters = {};
        if (this.mustHave.length) {
            filters.must_have = [...this.mustHave];
        }
        if (this.exclude.length) {
            filters.exclude = [...this.exclude];
        }
        const phrase = this.phraseInput.value.trim().replace(/\s+/g, ' ');
        if (phrase) {
            filters.exact_phrase = phrase;
        }
        const site = this.siteInput.value.trim().toLowerCase();
        if (site) {
            filters.sites = [site];
        }
        return filters;
    }

    setEnabled(disabled) {
        for (const input of this.inputs) {
            input.disabled = disabled;
        }
    }
}
