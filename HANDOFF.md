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

## 2026-11-05 — E2.2 zrobione; następne #62-490 (E3.1)

- **Stan:** develop = commit E2.2 (push). E2.2: fetcher z konfigurowalnym UA (Chrome) + Accept-Language z `config/app.php` (`research.fetch_user_agent`/`fetch_accept_language`) przez DI; domyślnie neutralny UA bez AL; bez cookies/JS dalej. Walidacja: PHPUnit 285/285, UI 107/107, E2E 5/5.
- **Następne (kolejność uzgodniona z właścicielem):** #62-490 (E3.1) — ekstrakcja `main`/`article` + cięcie na granicy akapitów w `PublicWebPageFetcher` (plan w `TODO-zrodla.md`, E3.1); po nim #62-542 (data/dzień tygodnia w prompcie systemowym — małe).
- **Wstrzymane (właściciel):** rotacja MCP — serwer w repo `szukaj` nie działa; po postawieniu: `sudo docker compose up -d --force-recreate app`, weryfikacja `GET /api/search/status` (zalogowany) + jedno wyszukanie; zdjąć „WSTRZYMANA" ze STATUS. Po deployu też: manualny test filtrów w UI, `gemini-3.8-flash` (fix MAX_TOKENS) i E2.2 na Gazeta.pl (właściciel).
- **Pułapki:**
  - Właściciel nie kopiuje z terminala → pliki pomocnicze w `var/` (do sprzątania: `t.txt`, `m.txt`, `rotacja-mcp.txt`, `TEST-T3.2-instrukcja.txt`).
  - E2E: `E2E_MAILBOX_URL=http://127.0.0.1:8025 E2E_SMTP_DSN=smtp://127.0.0.1:1025 npm run test:e2e`; UI lokalnie `node --test --experimental-test-isolation=none`.
  - Push: `git -c credential.helper='!gh auth git-credential' push origin develop`.
  - DT przez curl; notatki `devbrain_todo_note` bywają odrzucane `-32700` sporadycznie (serwer/WAF DevBrain — zgłoszone jako #62-543); done/claim działają stabilnie; python urllib → 403.
  - Właściciel: „nie trać czasu na badanie kodu" — czytać tylko to, co konieczne do zadania.
