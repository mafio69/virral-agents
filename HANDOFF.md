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

## 2026-11-06 — E3.1 zrobione; do scalenia do `develop` i pusha

- **Gałąź / commit:** `feature/E3.1` (z `develop`) — ekstrakcja treści głównej w `PublicWebPageFetcher`. Niepushnięty; czeka na lokalne scalenie do `develop` i push właściciela.
- **Zrobione (E3.1, #62-490):** treść z `main`/`article`/`[role="main"]` (fallback `body`), usuwanie `aside`/`header`/`figure` + banerów zgód (`cookie|cookies|consent|gdpr|cmp` w `id`/`class`), cięcie 16 000 znaków na granicy akapitu (`\n\n`). Walidacja: PHPUnit 289/289, UI 107/107, formatter 0/181.
- **Od czego zacząć (właściciel):** scalenie `feature/E3.1` → `develop`, zamknięcie #62-490 na DT (curl), push `develop`. Potem #62-542 (data/dzień tygodnia w prompcie systemowym).
- **Wstrzymane (właściciel):** rotacja MCP — serwer w repo `szukaj` nie działa; po postawieniu: `sudo docker compose up -d --force-recreate app`, weryfikacja `GET /api/search/status` + jedno wyszukanie. Po deployu: manualny test filtrów w UI, `gemini-3.8-flash` (fix MAX_TOKENS) i E2.2 na Gazeta.pl.
- **Pułapki:**
  - Właściciel nie kopiuje z terminala → pliki pomocnicze w `var/` (sprzątać: `t.txt`, `m.txt`, `rotacja-mcp.txt`, `TEST-T3.2-instrukcja.txt`).
  - E2E: `E2E_MAILBOX_URL=http://127.0.0.1:8025 E2E_SMTP_DSN=smtp://127.0.0.1:1025 npm run test:e2e`; UI lokalnie `node --test --experimental-test-isolation=none` (Node lokalny nie zna `--test-isolation=none`, które `composer check-all` wywołuje i kończy się error 9 — uruchamiać UI osobno).
  - Push: `git -c credential.helper='!gh auth git-credential' push origin develop`.
  - DT przez curl; `devbrain_todo_note` bywają odrzucane `-32700` (zgłoszone #62-543); done/claim działają stabilnie; python urllib → 403.
  - Właściciel: „nie trać czasu na badanie kodu" — czytać tylko to, co konieczne do zadania.
