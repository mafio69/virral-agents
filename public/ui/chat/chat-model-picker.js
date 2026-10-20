/** A viewport-bounded model list; the select remains the application's data source. */
export function enhanceModelPicker(select) {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'model-picker-button';
    button.setAttribute('aria-haspopup', 'listbox');
    button.setAttribute('aria-expanded', 'false');
    button.setAttribute('aria-label', 'Model AI');
    const popup = document.createElement('div');
    popup.className = 'model-picker-popup';
    popup.id = 'model-picker-list';
    popup.setAttribute('role', 'listbox');
    popup.setAttribute('aria-label', 'Modele AI');
    popup.hidden = true;
    button.setAttribute('aria-controls', popup.id);
    select.hidden = true;
    select.after(button);
    document.body.append(popup);
    let active = 0;

    const close = () => {
        popup.hidden = true;
        button.setAttribute('aria-expanded', 'false');
    };
    const sync = () => {
        button.textContent = select.selectedOptions[0]?.textContent || 'Brak modeli';
        button.disabled = select.disabled;
        button.title = select.title;
        if (select.disabled) {
            close();
        }
    };
    const choose = index => {
        const option = select.options[index];
        if (!option || option.disabled) {
            return;
        }
        select.value = option.value;
        select.dispatchEvent(new Event('change', { bubbles: true }));
        sync();
        close();
        button.focus();
    };
    const focusOption = index => {
        const options = [...popup.children];
        if (!options.length) {
            return;
        }
        active = (index + options.length) % options.length;
        options[active].focus();
    };
    const open = () => {
        if (select.disabled) {
            return;
        }
        popup.replaceChildren(...[...select.options].map((option, index) => {
            const item = document.createElement('button');
            item.type = 'button';
            item.setAttribute('role', 'option');
            item.setAttribute('aria-selected', String(option.selected));
            item.tabIndex = -1;
            item.disabled = option.disabled;
            item.textContent = option.textContent;
            item.title = option.title;
            item.addEventListener('click', () => choose(index));
            return item;
        }));
        popup.hidden = false;
        button.setAttribute('aria-expanded', 'true');
        const rect = button.getBoundingClientRect();
        const position = pickerPosition(rect, innerWidth, innerHeight);
        Object.assign(popup.style, position);
        focusOption(Math.max(0, select.selectedIndex));
    };
    button.addEventListener('click', () => popup.hidden ? open() : close());
    button.addEventListener('keydown', event => {
        if (['ArrowDown', 'ArrowUp'].includes(event.key)) { event.preventDefault(); open(); }
    });
    popup.addEventListener('keydown', event => {
        if (event.key === 'Escape') { event.preventDefault(); close(); button.focus(); }
        else if (event.key === 'Tab') { close(); button.focus(); }
        else if (['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key)) {
            event.preventDefault();
            focusOption(event.key === 'Home' ? 0 : event.key === 'End' ? popup.children.length - 1 : active + (event.key === 'ArrowDown' ? 1 : -1));
        }
    });
    document.addEventListener('pointerdown', event => {
        if (!popup.contains(event.target) && !button.contains(event.target)) {
            close();
        }
    });
    window.addEventListener('resize', close);
    select.addEventListener('change', sync);
    new MutationObserver(sync).observe(select, { childList: true, subtree: true, attributes: true });
    sync();
}

export function pickerPosition(rect, viewportWidth, viewportHeight) {
    const width = Math.min(520, viewportWidth - 32);
    const left = Math.max(16, Math.min(rect.left + rect.width / 2 - width / 2, viewportWidth - width - 16));
    const below = viewportHeight - rect.bottom - 16;
    const above = rect.top - 16;
    const upward = below < 180 && above > below;
    return { width: `${width}px`, left: `${left}px`,
        top: upward ? 'auto' : `${rect.bottom + 6}px`,
        bottom: upward ? `${viewportHeight - rect.top + 6}px` : 'auto',
        maxHeight: `${Math.max(40, Math.min(360, (upward ? above : below) - 6))}px` };
}
