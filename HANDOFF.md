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

## 2026-11-09 — DeepSeek prod, reset hasła, role + CRUD dostawców (admin)

- **Gałąź:** `develop` = `af0c057` (czysty, pushnięty). `master` = `15e2ac8` (PR #24). Walidacja: PHPUnit 323/323, UI 113/113, formatter 0/200.
- **Zrobione dziś:**
  - DeepSeek na prod (problem: brak `DEEPSEEK_API_KEY` w `.env` prod — właściciel dodał, `--force-recreate app`).
  - Reset hasła: port z `backup/old-develop` na `develop` (endpointy, `PasswordResetLimiter`, UI landing, fixy CodeRabbit: bcrypt 72 B, timer resetu).
  - Security: wyścig login/reset (`createSessionIfPasswordUnchanged`, `BEGIN IMMEDIATE`), timing forgot-password (`register_shutdown_function` + `fastcgi_finish_request`).
  - Port z env: `app_url` = `APP_URL` albo `http://localhost:$APP_HOST_PORT` (koniec hardcode 8181).
  - **Faza 1 (role):** `users.role` admin/user, pierwszy user = admin, `AuthMiddleware` dokłada `role`.
  - **Faza 2 (CRUD admin):** `AdminOnlyMiddleware` (404 nie-admin) + `GET/POST/PUT/DELETE /api/ai-providers`, `POST/DELETE /api/ai-models`; sekret nigdy w odpowiedzi (tylko `has_secret`).
- **Od czego zacząć (jutro):**
  1. **Faza 4** — UI panel „Dostawcy AI" (formularz: nazwa, URL, klucz `type=password`, id modelu; lista z `has_secret` → `••••••`). Używa gotowych endpointów.
  2. **Faza 3** — generalizacja rejestru db (dostawcy z UX widoczni w czacie; nachodzi na #62-497).
  3. **Naprawa `TaskController`** — `int $id` w `update`/`delete` rzuca `TypeError` (patrz Pułapki).
  4. **Faza 5 (później)** — memory per `dostawca:model`.
- **Pułapki:**
  - `ControllerInvoker` (PHP-DI) **nie rzutuje** stringa `{id}` na int — w kontrolerach używaj `(int)$request->getAttribute('id')`, nie `int $id` (latent bug w `TaskController`).
  - Sekrety: nigdy w odpowiedzi, nigdy w logach, pole klucza maskowane (`type=password`, `autocomplete="new-password"`).
  - Dwie linie historii scalone; `backup/old-develop` (reset hasła itd.) już scalony — można porzucić po potwierdzeniu.
  - Rootless Docker: `./dev-start.sh` bez sudo, port `APP_HOST_PORT` (default 8181); `DOCKER_HOST` właściciela w `/etc/environment`.
  - E2E: `E2E_MAILBOX_URL=http://127.0.0.1:8025 E2E_SMTP_DSN=smtp://127.0.0.1:1025 npm run test:e2e`; UI `node --test --test-isolation=none`.
  - Push: `git -c credential.helper='!gh auth git-credential' push origin <branch>` (tylko za zgodą).
