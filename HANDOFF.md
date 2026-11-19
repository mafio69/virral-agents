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

## 2026-11-19 — porządki w gałęziach, przegląd resetu hasła

- **Gałąź / ostatni commit:** `develop` = `749ff3f` (role + CRUD dostawców, Fazy 1–2; tylko w `develop`). `master` = `15e2ac8` (PR #24). Ostatnia walidacja z 2026-11-09: PHPUnit 323/323, UI 113/113, formatter 0/200.
- **Zrobione:** stany ✅/🟡/❌ zadań w `TODO-zrodla.md`; usunięty stary `stash@{0}`, lokalna `backup/E3.1-…` i 11 scalonych/porzuconych gałęzi zdalnych.
- **Otwarte PR-y do `develop`:** #25 `fix/restart-po-awarii` (restart kontenerów), #26 ta dokumentacja, #27 `feature/E3.1` (E3.1; możliwy konflikt w `STATUS.md` — scalać po #26 i oznaczyć E3.1 jako ✅).
- **Przegląd resetu hasła:** OK (hash tokenu, atomowe zużycie, unieważnienie sesji, limit, stały czas odpowiedzi, `replaceState`). Do naprawy: `RegisterInputMapper` nie ma limitu 72 bajtów (bcrypt przycina) — reset go ma.
- **Od czego zacząć:**
  1. Scalić #25 → #26 → #27.
  2. **Faza 4** — UI panel „Dostawcy AI" (formularz: nazwa, URL, klucz `type=password`, id modelu; lista z `has_secret` → `••••••`). Używa gotowych endpointów.
  3. **Faza 3** — generalizacja rejestru db (dostawcy z UX widoczni w czacie; nachodzi na #62-497).
  4. **Naprawa `TaskController`** — `int $id` w `update`/`delete` rzuca `TypeError` (patrz Pułapki); poprawka 72 B w rejestracji.
  5. **Faza 5 (później)** — memory per `dostawca:model`.
- **Pułapki:**
  - Przed oceną stanu gałęzi zawsze `git fetch --prune` — lokalne `origin/*` bywają nieaktualne.
  - `ControllerInvoker` (PHP-DI) **nie rzutuje** stringa `{id}` na int — w kontrolerach używaj `(int)$request->getAttribute('id')`, nie `int $id` (latent bug w `TaskController`).
  - Sekrety: nigdy w odpowiedzi, nigdy w logach, pole klucza maskowane (`type=password`, `autocomplete="new-password"`).
  - `origin/feature/password-reset-port` scalona (PR #23) — do usunięcia za zgodą; `backup/old-develop` nie istnieje już ani lokalnie, ani na origin.
  - Rootless Docker: `./dev-start.sh` bez sudo, port `APP_HOST_PORT` (default 8181); `DOCKER_HOST` właściciela w `/etc/environment`.
  - E2E: `E2E_MAILBOX_URL=http://127.0.0.1:8025 E2E_SMTP_DSN=smtp://127.0.0.1:1025 npm run test:e2e`; UI `node --test --test-isolation=none`.
  - Push: `git -c credential.helper='!gh auth git-credential' push origin <branch>` (tylko za zgodą).
