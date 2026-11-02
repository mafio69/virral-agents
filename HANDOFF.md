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

## 2026-11-01 — porządek gałęzi, nginx/ACL, weryfikacja (Claude)

- **Gałąź:** `chore/agents-dom-status` z `origin/master` (lokalny `develop` przesunięty na `origin/master`, bez push). Zmiany niezacommitowane do akceptacji właściciela.
- **Zrobione:** diagnoza i naprawa (przez właściciela, `setfacl`) plików statycznych za nginx; `.env` `660`; `server_name agents.dom`; wpisy CHANGELOG/REPO/STATUS, `docs/E2E.md`; pliki do `.local/karencja` (usunąć po 2026-11-08).
- **Od czego zacząć:** po akceptacji commit i push `develop` + gałęzi, PR do `develop`; potem test wyszukiwania po konfiguracji MCP przez właściciela.
- **Pułapki:**
  - `MCP_SEARCH_URL` musi być `http://mcp-search:8000/mcp` (nie `szukaj:8000` — to SearXNG); token MCP do rotacji.
  - Traefik (osobny projekt) w pętli restartów: port 443 zajęty przez Tailscale; tymczasowo aplikacja po IP `:8181`.
  - Na serwerze `*.dom` się nie rozwiązuje — E2E z `E2E_MAILBOX_URL=http://127.0.0.1:8025 E2E_SMTP_DSN=smtp://127.0.0.1:1025`.
  - Lokalny Node 22 nie zna `--test-isolation=none` (CI ma Node 24); ręcznie `--experimental-test-isolation=none`.
  - Narzędzia Read/Edit agenta są ograniczone do `bin/` — edycje przez Bash.
  - Podpis commitów zgodny z modelem (`GIT-FLOW.md`), np. `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.
