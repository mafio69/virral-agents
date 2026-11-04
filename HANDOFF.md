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

## 2026-11-04 — fix Gemini MAX_TOKENS (czeka na commit + push)

- **Stan:** develop zawiera T6.1+T6.2+T3.1+T3.2-docs (pushnięte). Gałąź `fix/gemini-thinking-max-tokens` z pełną naprawą **niezacommitowaną** — walidacja zielona: PHPUnit 284/284 (1331 asercji), UI 107/107, E2E 5/5, formatter czysty.
- **Zrobione:** `GeminiProvider` przy `finishReason: MAX_TOKENS` (tylko modele tekstowe) zwraca częściowy tekst + usage zamiast `invalid_response`; obrazy/SAFETY/puste odpowiedzi nadal odrzucane; `maxOutputTokens` tekstowy 4096→8192. Testy: partial+usage+limit, pusty MAX_TOKENS, SAFETY, asercja 8192. STATUS/CHANGELOG zaktualizowane (rotacja MCP oznaczona WSTRZYMANA — serwer `szukaj` nie działa; token serwerowy już podmieniony przez właściciela).
- **Od czego zacząć:**
  1. Commit na fix → ff do develop → push (`git -c credential.helper='!gh auth git-credential' push origin develop`).
  2. Następne zadanie: #62-486 (E2.2, User-Agent/Accept-Language w `PublicWebPageFetcher` z config/DI) — plan w `TODO-zrodla.md`.
  3. Gdy właściciel postawi `szukaj`: dokończyć rotację MCP (force-recreate app + `GET /api/search/status` zalogowanym + jedno wyszukanie), zdjąć „WSTRZYMANA" ze STATUS.
  4. Po deployu: manualny test `gemini-3.8-flash` w UI (dłuższe pytanie — kiedyś padało na MAX_TOKENS).
- **Pułapki:**
  - Właściciel nie kopiuje z terminala → wszystko do skopiowania w plikach w `var/` (stare: `var/t.txt`, `var/m.txt`, `var/rotacja-mcp.txt` — do usunięcia).
  - E2E: `E2E_MAILBOX_URL=http://127.0.0.1:8025 E2E_SMTP_DSN=smtp://127.0.0.1:1025 npm run test:e2e`.
  - `bin/check-mcp-search.php` nie wysyła tokenu po http do nazw sieciowych (tylko https/loopback) — weryfikacja wewnątrz kontenera app pada na ten guard; używać `/api/search/status` w przeglądarce zalogowanego użytkownika.
  - Push przez jednorazowy helper `gh`; Node 22 lokalnie wymaga `--experimental-test-isolation=none`.
