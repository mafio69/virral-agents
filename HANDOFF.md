# HANDOFF

## Jak prowadzić

- Pałeczka na **jedną** kolejną sesję. Na starcie sesji przeczytaj, na końcu **wyzeruj i napisz od nowa** — bez dopisywania pod spodem.
- Maksymalnie ~30 linii. Tylko to, czego następna sesja nie odczyta szybko z kodu, `STATUS.md` ani `git status`.
- Historia trafia do `CHANGELOG.md`, stan projektu do `STATUS.md`, trwałe mechanizmy do `REPO.md`.
- Bez sekretów, haseł, tokenów i ścieżek do nich.

Szablon:

```markdown
## RRRR-MM-DD — <temat sesji>
- Gałąź / ostatni commit:
- Zrobione w tej sesji (niezacommitowane lub niezweryfikowane):
- Od czego zacząć:
- Pułapki:
```

---

## 2026-11-02 — T6.1 scalone z masterem (rebase), T6.2 UI w toku

- **Stan:** PR #15 (owner, `chore/agents-dom-status`) scalony do `master` (`c42ab6b`); lokalne `master` i `develop` przesunięte ff. `feature/T6.1` (commit T6.1 z #62-524) zrebasowany na nowy develop; `feature/T6.2` na wierzchu. DT #62-524/#62-525 `[CLAIMED] OpenCode/GLM-5.3`.
- **Kluczowe odkrycie do T6.2:** ręczne wyszukiwanie w UI idzie przez `ResearchPanel` (klik „Szukaj stron…" → `run('propose', {request:{kind:'search',query}})` → karta Allow/Deny), NIE przez stary `bindSearchControls`/`approveSearch` (to kontrakt zgodności API). Chipy filtrów trzeba wpinać w `ResearchPanel` (chat-research.js) + `chat.html`.
- **Zrobione w T6.1 (zacommitowane):** `SearchQuery`+`SearchQueryBuilder`, `SearchOptions`, `ResearchService`, `WebTools` (must_have/exclude/exact_phrase/sites), `describeSearchFilters` w chat-search.js, karta zgody z filtrami, testy (PHP 275/275, UI 100/100), dokumentacja REPO/MCP-USAGE/CHANGELOG.
- **Od czego zacząć:** T6.2 (#62-525): w `chat.html` pod `#search-query` chipy „Bez"/„Musi zawierać" (klik = usuń) + „Więcej opcji" (dokładna fraza, strona); `ResearchPanel` dokleja filtry do `request` w `propose`; `canSearch` bez zmian (query wymagane); po zmianach JS podbić `?v=` w importach `chat.html`; testy: `tests/ui/chat-research.test.mjs` + `chat-search.test.mjs`. Po T6.2: wpis CHANGELOG, walidacja pełna, commit po zgodzie.
- **Pułapki:**
  - Lokalny Node 22: UI przez `node --test --experimental-test-isolation=none tests/ui/*.test.mjs` (CI Node 24).
  - `SearchQueryBuilder` celowo w `Dto\Chat`; limit tokenów przed deduplikacją; `toArray()`: query, must_have, exclude, exact_phrase, sites.
  - Decyzje właściciela: must → `+słowo`; kontrakt WebTools rozszerzony w T6.1; `time_range`/`categories`/`language` czekają na serwer w repo `szukaj` (#62-528).
  - Dostęp do DT: `DT_MCP_TOKEN` z `.env` przez awk/curl bez wyświetlania tokenu (zaakceptowane 2026-11-02).
  - origin/develop wciąż 19 commitów za masterem (push develop czeka na zgodę właściciela).
