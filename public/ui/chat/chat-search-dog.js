/** Decorative, local SVG: no remote images, scripts or additional requests. */
export function mountSearchDog(container) {
    container.classList.add('search-dog');
    container.setAttribute('role', 'status');
    container.setAttribute('aria-live', 'polite');
    container.innerHTML = `<svg viewBox="0 0 116 78" width="92" height="62" aria-hidden="true" focusable="false">
      <ellipse cx="54" cy="71" rx="35" ry="3" fill="currentColor" opacity=".12"/>
      <path class="dog-tail" d="M31 57Q9 60 17 41" fill="none" stroke="#77838b" stroke-width="8" stroke-linecap="round"/>
      <path d="M36 69Q23 56 36 40Q45 32 54 43L64 67Z" fill="#71818a"/>
      <path d="M43 45Q53 46 55 66L48 69 39 62Z" fill="#d4a36c"/>
      <path d="M35 67Q26 64 25 70H45Q47 65 42 64" fill="#b48254"/>
      <path d="M53 48L61 67Q68 66 68 71H50Z" fill="#e1b582"/>
      <g class="dog-head">
        <path d="M44 29L38 7Q50 9 55 24M58 24L68 8 72 34" fill="#9a6c48" stroke="#c69461" stroke-width="3" stroke-linejoin="round"/>
        <path d="M36 29Q42 17 60 22Q77 24 75 42L62 53Q44 53 37 43L33 39 38 36 32 33Z" fill="#d4a36c"/>
        <path d="M45 23L49 19 54 24 58 20 63 26 62 34 48 33Z" fill="#7c8990"/>
        <path d="M62 37Q82 33 87 43Q83 54 66 49Z" fill="#edc99c"/>
        <ellipse class="dog-nose" cx="86" cy="42" rx="5" ry="4" fill="#283036"/>
        <circle cx="64" cy="33" r="3" fill="#283036"/><circle cx="65" cy="32" r="1" fill="#fff"/>
        <path d="M69 47Q74 50 78 47" fill="none" stroke="#805b40" stroke-width="1.6" stroke-linecap="round"/>
      </g>
      <g class="dog-scent" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round">
        <path d="M96 34l5-3M99 42h7M96 50l5 3"/>
      </g>
    </svg><span class="search-dog-label"></span>`;
    const label = container.querySelector('.search-dog-label');
    return {
        update(activity = 'idle') {
            container.hidden = activity === 'idle';
            container.dataset.activity = activity;
            label.textContent = activity === 'searching' ? 'Węszę… Szukam źródeł i przygotowuję odpowiedź.'
                : activity === 'done' ? 'Gotowe — możesz przejrzeć wyniki.'
                    : 'Zatrzymałem się — sprawdź komunikat przy wiadomości.';
        },
    };
}
