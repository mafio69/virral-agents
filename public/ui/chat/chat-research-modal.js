/** Presentation only: durable source data stays in the encrypted server workspace. */
export class ResearchModal {
    constructor({ dialog, openButtons, closeButton, count, sourceList }) {
        Object.assign(this, { dialog, openButtons, closeButton, count, sourceList });
        this.opener = null;
        openButtons.forEach(button => button.addEventListener('click', () => this.open(button)));
        closeButton.addEventListener('click', () => this.close());
        dialog.addEventListener('click', event => { if (event.target === dialog) {
            const bounds = dialog.getBoundingClientRect();
            if (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom) {
                this.close();
            }
        } });
        dialog.addEventListener('close', () => (this.opener?.isConnected ? this.opener : this.openButtons[0])?.focus());
    }

    open(opener = this.openButtons[0]) {
        this.opener = opener;
        if (!this.dialog.open) {
            this.dialog.showModal();
        }
    }

    close() { if (this.dialog.open) this.dialog.close(); }

    update(state) {
        this.count.textContent = String(state.sources.length);
        this.sourceList.replaceChildren();
        const selected = new Set(state.sources.map(source => source.id));
        const sources = [...state.sources, ...state.results.filter(source => !selected.has(source.id))];
        for (const source of sources) {
            const item = document.createElement('li');
            const button = document.createElement('button');
            button.type = 'button';
            const loaded = source.page?.status === 'loaded';
            button.textContent = `${loaded ? '✓ ' : ''}${source.title || source.url}`;
            button.title = source.url;
            button.addEventListener('click', () => this.open(button));
            item.append(button);
            this.sourceList.append(item);
        }
    }
}
