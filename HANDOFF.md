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

## 2026-10-25 — Przygotowanie wydania v1.0.0

- **Gałąź:** `develop` (po scaleniu `release/v1.0.0`). Automatyczna walidacja 100% zielona (`composer check-all`: formatter 0/168, PHP 238/238 — 1142 asercje, UI 93/93; `npm run test:e2e`: 5/5) oraz testy manualne potwierdzone przez właściciela.
- **Zrobione w tej sesji:**
  - Zsynchronizowano dokumentację (`README.md`, `REPO.md`, `docs/MCP-USAGE.md`, `STATUS.md`, `CHANGELOG.md`) ze stanem kodu (`MCP_AUTH_TOKEN`, `/continue`, `chat_messages.kind`, `TRUSTED_PROXIES`, `DELETE /api/research`).
  - Ujednolicono przyjazne komunikaty błędów dla użytkownika (brak surowych kodów `HTTP 5xx`, `[ERROR 503]` i wewnętrznych nazw konfiguracji) oraz rozszerzono logowanie kontekstu diagnostycznego w `DualLogger` (`src/middleware.php`, `public/index.php`, `AuthService`, `ChatService`, `ModelCatalogService`, `ChatReplyGenerator`).
  - Dodano czyszczenie wyników i źródeł badania (`DELETE /api/research`, przyciski `#research-clear-side` w sekcji źródeł i `#research-clear-modal` w modalu) oraz izolację jednego wyszukiwania na jedną wymianę zdań (nowe wyszukiwanie zapisuje wyniki do czystego stanu, a wygenerowanie odpowiedzi automatycznie czyści stan badania i wyłącza ponowne wywołanie narzędzi WWW w tej samej turze).
  - Dodano test E2E logowania z błędnymi i poprawnymi danymi (`tests/e2e/flows.spec.mjs`) oraz testy jednostkowe obsługi błędów i czyszczenia źródeł (`ChatControllerErrorTest`, `ApplicationFailureLogTest`, `SearchDiagnosticsTest`, `ResearchServiceTest`, `ResearchAgentTest`, `chat-controller.test.mjs`, `chat-research.test.mjs`).
- **Od czego zacząć:** utworzenie PR `develop` → `master` na GitHubie (po zielonym CI), deploy na serwer produkcyjny (Optiplex) i oznaczenie tagu `v1.0.0`.
- **Pułapki:**
  - Lokalna modyfikacja `GIT-FLOW.md` oraz nieśledzone `.opencode/plugins/` i `var/` — nie commitować bez wyraźnej decyzji właściciela.
  - Formatter obejmuje `bin/` — nowe/zmieniane skrypty w `bin/` muszą trzymać PSR-12.
  - E2E wysyła pocztę do `mail-trap.dom:1025`, API `http://mail-trap.dom`.
  - Opisy poleceń Bash po polsku; `sudo` wymaga klucza FIDO — polecenia z `sudo` uruchamia właściciel w swoim terminalu.
