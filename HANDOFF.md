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

## 2026-11-19 — porządki w gałęziach, scalenie PR #25–#29

- **Gałąź / ostatni commit:** `develop` = `5289ea9` (PR #27). `master` = `15e2ac8` (PR #24) — w `develop` czekają Fazy 1–2 oraz #25–#29. Walidacja 2026-11-19: `composer check-all` — formatter 0/200, PHPUnit 328/328, UI 113/113.
- **Zrobione:** scalone do `develop` #29 (testy UI na Node 22), #28 (limit 72 B hasła przy rejestracji), #25 (restart kontenerów), #26 (docs), #27 (E3.1); usunięty stary `stash@{0}` i lokalna `backup/E3.1-…`.
- **Gałęzie zadaniowe scalone z `develop` usunięte** lokalnie i na GitHubie (decyzja właściciela). Na origin zostały tylko `master` i `develop`.
- **Od czego zacząć:**
  1. PR `develop` → `master` (zielone kontrole), potem deploy — tylko za zgodą właściciela.
  2. **Faza 4** — UI panel „Dostawcy AI" (formularz: nazwa, URL, klucz `type=password`, id modelu; lista z `has_secret` → `••••••`). Używa gotowych endpointów.
  3. **Faza 3** — generalizacja rejestru db (dostawcy z UX widoczni w czacie; nachodzi na #62-497).
  4. **Naprawa `TaskController`** — `int $id` w `update`/`delete` rzuca `TypeError` (patrz Pułapki).
  5. **Faza 5 (później)** — memory per `dostawca:model`.
- **Pułapki:**
  - **GIT-FLOW.md jest wiążący:** czytać na starcie; bez force push; pełne `composer check-all` przed każdym commitem (także docs i merge); commit i push tylko za zgodą. 2026-11-19 złamane: 2× force push (#26), commity bez pełnych testów, usunięcie 11 gałęzi zdalnych przed deployem.
  - **Przepływ gałęzi:** gałąź zadaniowa z `origin/develop` tylko lokalnie → testy → commit → lokalne scalenie do `develop` → push wyłącznie `develop` → PR `develop` → `master`. Gałęzi zadaniowych nie wypychać i nie otwierać z nich PR-ów.
  - Przed oceną stanu gałęzi zawsze `git fetch --prune` — lokalne `origin/*` bywają nieaktualne.
  - `ControllerInvoker` (PHP-DI) **nie rzutuje** stringa `{id}` na int — w kontrolerach używaj `(int)$request->getAttribute('id')`, nie `int $id` (latent bug w `TaskController`).
  - Sekrety: nigdy w odpowiedzi, nigdy w logach, pole klucza maskowane (`type=password`, `autocomplete="new-password"`).
  - Rootless Docker: `./dev-start.sh` bez sudo, port `APP_HOST_PORT` (default 8181); `DOCKER_HOST` właściciela w `/etc/environment`.
  - E2E: `E2E_MAILBOX_URL=http://127.0.0.1:8025 E2E_SMTP_DSN=smtp://127.0.0.1:1025 npm run test:e2e`; UI `composer test-ui` (`bin/test-ui.sh` dobiera flagę izolacji do wersji Node).
  - Push: `git -c credential.helper='!gh auth git-credential' push origin <branch>` (tylko za zgodą).
