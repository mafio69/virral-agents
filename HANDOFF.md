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

## 2026-10-26 — Poprawki z przeglądu PR #10 (wydanie v1.0.0)

- **Gałąź:** `develop` (otwarty PR #10 `develop` → `master`). Automatyczna walidacja 100% zielona (`composer check-all`: formatter 0/168, PHP 239/239 — 1148 asercji, UI 93/93; `npm run test:e2e`: 5/5). Potwierdzono testy manualne czatu i badania źródeł w przeglądarce; przed oznaczeniem tagu `v1.0.0` obowiązują pozostałe weryfikacje środowiskowe/produkcyjne z `STATUS.md`.
- **Zrobione w tej sesji:**
  - Usunięto zahardkodowane hasło z `bin/restore-dev-user.php` i `docs/DEV-ADMIN.md`; skrypt wczytuje teraz `DEV_ADMIN_PASSWORD` (min. 10 znaków) z lokalnego środowiska bez wypisywania hasła na `STDOUT`.
  - Zaktualizowano przykład zdalnego adresu `MCP_SEARCH_URL` w `docs/KONFIGURACJA.md`, `README.md` i `REPO.md` na `https://szukaj.dom/mcp` z wymogiem TLS dla zdalnych endpointów z `MCP_AUTH_TOKEN`.
  - W `ChatReplyGenerator` rozróżniono brak konfiguracji wyszukiwania (`missing_configuration`) od chwilowej awarii; w `ChatService` wydzielono wspólne metody pomocnicze dla `addMessage()` i `continueMessage()`; w `SettingsSearchEnvTest` dodano pełny snapshot i przywracanie `$_ENV`/`$_SERVER`/`putenv`; w `GIT-FLOW.md` przeniesiono krok dokumentacji przed `Commit`.
- **Od czego zacząć:** weryfikacja PR #10 na GitHubie (w GitGuardian oznaczenie historycznego commitu `68d938a` jako testowego lub decyzja właściciela), merge `develop` → `master`, deploy na Optiplex i pozostałe kontrole z `STATUS.md` przed tagiem `v1.0.0`.
- **Pułapki:**
  - Nieśledzone `.opencode/plugins/` i `var/` — nie commitować.
  - Formatter obejmuje `bin/` — skrypty w `bin/` muszą trzymać PSR-12.
  - E2E wysyła pocztę do `mail-trap.dom:1025`, API `http://mail-trap.dom`.
  - Opisy poleceń Bash po polsku; `sudo` uruchamia właściciel w swoim terminalu; podpis commitów: `Co-Authored-By: Gemini <noreply@google.com>`.
