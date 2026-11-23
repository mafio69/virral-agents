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

## 2026-11-22 — Autodeploy przez Tailscale + wcześniejsze listy wyszukiwań i Smart UX

- **Gałąź:** `develop` (scalone `feat/autodeploy-tailscale`, pushnięte na `origin/develop`, zaktualizowany PR #31 → `master`). Walidacja: formatter 0/200, PHPUnit 331/331 (1500 asercji), UI 124/124.
- **Zrobione w tej sesji:**
  - Autodeploy (`.github/workflows/deploy.yml`, `bin/deploy-prod.sh`, `docs/DEPLOY.md`, `tests/ui/deploy-prod.test.mjs`): start automatyczny po zielonym `Tests` na `push` do `master`, Tailscale OAuth (`tag:ci`) + SSH (`StrictHostKeyChecking=yes`), `git pull --ff-only`, warunkowy `composer install --no-dev` w obrazie `app`, `docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d --force-recreate app` i `nginx`, healthcheck HTTP 200 (`/api/ai-connections`).
  - Wcześniej dziś na `develop` (PR #31): listy wyszukiwań (`pool`/`ephemeral` + `saved` + kosz), Smart UX czatu, lżejszy modal (`Nowa sesja`, `Nowy prompt`, `Kontynuuj`), przycisk `+` (obraz/plik tekstowy), porządek `APP_PORT`/`LANGFUSE_BASE_URL`, limit `8192` tokenów u wszystkich dostawców AI i scalanie ról w `OpenRouterProvider`.
- **Od czego zacząć:**
  1. Konfiguracja jednorazowa z `docs/DEPLOY.md` (Tailscale ACL dla `tag:ci`, OAuth Client, klucz SSH i 7 sekretów w GitHub Environment `production`) oraz weryfikacja `APP_PORT` / `LANGFUSE_BASE_URL` w `.env` na Optiplexie.
  2. Po zielonym CI na PR #31: merge do `master` (uruchomi pierwszy autodeploy).
  3. Kolejne z `STATUS.md`: **Faza 4** (UI panel „Dostawcy AI"), **Faza 3** (generalizacja rejestru DB), naprawa `TaskController` (`int $id`), **Faza 5** (memory).
- **Pułapki:**
  - Przed oceną stanu gałęzi zawsze `git fetch --prune` — lokalne `origin/*` bywają nieaktualne.
  - Przepływ gałęzi: gałąź zadaniowa lokalnie → testy → commit → lokalny merge do `develop` → push wyłącznie `develop` → PR `develop` → `master`.
  - W nieśledzonych plikach roboczych na dysku są `agents.code-workspace`, `docs/INSTRUCTIONS.md`, `docs/INSTRUKCJA.md`, `docs/Makefile`, `favicon.svg` — nie dodawać ich do commita.
  - `ControllerInvoker` (PHP-DI) nie rzutuje stringa `{id}` na `int` — używać `(int)$request->getAttribute('id')` (latent bug w `TaskController`).
  - Rootless Docker: `./dev-start.sh` bez sudo, port `APP_PORT` (default 8181); testy UI przez `composer test-ui` (`bin/test-ui.sh`).
