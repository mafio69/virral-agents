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

## 2026-11-02 — T6.2 UI filtrów gotowe (czeka na zgodę na commit + scalenie)

- **Stan:** `develop` wypchnięty na GitHub (zawiera T6.1, `8c1e349`; #62-524 zamknięte na DT). Praca na `feature/T6.2` (na bazie T6.1). T6.2 (#62-525) **niezacommitowane** i kompletne: PHPUnit 275/275, UI 107/107, formatter czysty, `composer validate --strict` OK.
- **Zrobione (T6.2):** nowy `public/ui/chat/chat-search-filters.js` (`SearchFilters`: chipy, Enter/klik, „Więcej opcji", `collect()`, `setEnabled`), `ResearchPanel` z opcją `filters` (dokleja do `propose`), markup+style w `chat.html` (`?v=20261102-filters`), testy `chat-search-filters.test.mjs` + przypadek w `chat-research.test.mjs`, wpisy CHANGELOG/REPO/MCP-USAGE.
- **Od czego zacząć:**
  1. Zgoda na commit T6.2 → commit na `feature/T6.2`; potem wg wzorca właściciela: ff do `develop` i push (`git -c credential.helper='!gh auth git-credential' push origin develop`); `devbrain_todo_done` 525 (token `DT_MCP_TOKEN` z `.env`, curl bez wyświetlania).
  2. Następne zadania: T3.1 (#62-519, HistoryCompactor — wysoki priorytet) lub rotacja tokenu MCP / `TRUSTED_PROXIES` (STATUS).
- **Pułapki:**
  - Push działa tylko przez jednorazowy helper `gh` (użytkownik zalogowany jako mafio69); zwykły `git push` pada na HTTPS bez credentialów.
  - Lokalny Node 22: UI przez `node --test --experimental-test-isolation=none tests/ui/*.test.mjs`.
  - Testowy stub DOM w `chat-search-filters.test.mjs` ma własny `Element` (nie ten z `chat-research.test.mjs`); `morePanel` musi startować z `hidden = true` (jak w markupie).
  - Manualny test UI po deployu: czat → „Szukaj stron" → chipy/fraza/strona → karta zgody pokazuje podsumowanie; `?v=` podbity, więc twarde odświeżenie niepotrzebne.
