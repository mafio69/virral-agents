const TAGS = [
    'p', 'br', 'strong', 'em', 'del', 's', 'code', 'pre',
    'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'ul', 'ol', 'li',
    'blockquote', 'table', 'thead', 'tbody', 'tr', 'th', 'td', 'hr', 'a',
];

/**
 * Display-only Markdown: no scripts, forms, frames, images or remote resources.
 * The dependencies are injected so the graphic design remains independent.
 */
export class MarkdownRenderer {
    constructor({
        parser = globalThis.marked,
        purifier = globalThis.DOMPurify,
        clipboard = globalThis.navigator?.clipboard,
    } = {}) {
        this.parser = parser;
        this.purifier = purifier;
        this.clipboard = clipboard;
    }

    renderInto(container, source) {
        const text = String(source ?? '');
        if (!this.parser || !this.purifier?.isSupported) {
            container.textContent = text;
            return false;
        }

        let fragment;
        try {
            const html = this.parser.parse(text, { gfm: true, breaks: true, async: false });
            fragment = this.purifier.sanitize(html, {
                ALLOWED_TAGS: TAGS,
                ALLOWED_ATTR: ['href', 'title', 'class'],
                ALLOW_DATA_ATTR: false,
                ALLOW_ARIA_ATTR: false,
                RETURN_DOM_FRAGMENT: true,
            });
        } catch {
            container.textContent = text;
            return false;
        }

        // No class names originating from a model become application controls.
        fragment.querySelectorAll('[class]').forEach(element => {
            const language = element.tagName === 'CODE'
                ? Array.from(element.classList).find(name => /^language-[\w+-]{1,24}$/.test(name))
                : null;
            element.removeAttribute('class');
            if (language) element.className = language;
        });

        fragment.querySelectorAll('a').forEach(anchor => {
            const href = anchor.getAttribute('href') || '';
            if (!/^https?:\/\//i.test(href)) {
                anchor.removeAttribute('href');
                return;
            }
            anchor.target = '_blank';
            anchor.rel = 'noopener noreferrer';
            anchor.referrerPolicy = 'no-referrer';
        });

        fragment.querySelectorAll('table').forEach(table => {
            const wrapper = container.ownerDocument.createElement('div');
            wrapper.className = 'table-wrap';
            table.replaceWith(wrapper);
            wrapper.appendChild(table);
        });

        fragment.querySelectorAll('pre').forEach(pre => {
            const code = pre.querySelector('code');
            if (!code) return;
            const document = container.ownerDocument;
            const wrapper = document.createElement('div');
            wrapper.className = 'code';
            const toolbar = document.createElement('div');
            toolbar.className = 'code-head';
            const label = document.createElement('span');
            label.className = 'lang';
            label.textContent = code.className.replace(/^language-/, '') || 'Kod';
            const copy = document.createElement('button');
            copy.type = 'button';
            copy.className = 'btn-ghost';
            copy.textContent = 'Kopiuj';
            copy.setAttribute('aria-label', 'Kopiuj blok kodu');
            copy.addEventListener('click', async () => {
                try {
                    if (!this.clipboard) throw new Error('Clipboard unavailable');
                    await this.clipboard.writeText(code.textContent || '');
                    copy.textContent = 'Skopiowano';
                } catch {
                    copy.textContent = 'Zaznacz kod i skopiuj';
                }
            });
            toolbar.append(label, copy);
            pre.replaceWith(wrapper);
            wrapper.append(toolbar, pre);
        });

        container.replaceChildren(fragment);
        return true;
    }
}
