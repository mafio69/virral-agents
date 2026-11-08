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

## 2026-11-08 — fixy przeglądu PR #16 na gałęzi `fix/pr16-review` (bez push)

- **Gałąź:** `fix/pr16-review` z `origin/develop` (`e94da73`). Commity: fixy review + `dev-start.sh` bez sudo (rootless). Walidacja: PHPUnit 287/287, UI 109/109, formatter 0/181; E2E niepowtórzone.
- **Fixy z review (CodeRabbit):** kwotowanie wielosłownych tokenów (`SearchQueryBuilder`), pusty `query` → `invalid_response` (`WebTools`), ucięty `MAX_TOKENS` `functionCall` odrzucony + `completion_tokens` = kandydaci+myśli (`GeminiProvider`), normalizacja frazy/strony i walidacja inline w UI (`collect()` → `null`), sprostowanie T3.2 w STATUS, docstringi. Pominięte nitpicks (nazwane booleany, rozbiór `assertToken`, helpery w `HistoryCompactor`) — czysta czytelność, opisane w CHANGELOG.
- **WAŻNE — rozejście historii:** LOKALNY `develop` (reset hasła, Langfuse, DeepSeek `bafac2d`) nigdy nie został pushnięty; `origin/develop` = T6.x/T3.x/E2.2 (PR #16). Te linie trzeba kiedyś scalić (rebase/merge + testy) — dopóki tego nie zrobiono, nie pracować równolegle na obu.
- **Od czego zacząć:** push `fix/pr16-review` za zgodą właściciela (doda się do PR #16), obserwować CodeRabbit ponownie; potem scalenie lokalnego developa (decyzja właściciela).
- **Pułapki:**
  - Rootless Docker: `dev-start.sh` bez sudo (socket `/run/user/1000/docker.sock`); `DOCKER_HOST` wróciło do `/etc/environment` u właściciela. Testy UI używają atrapy `docker`, nie prawdziwego daemona.
  - E2E: `E2E_MAILBOX_URL=http://127.0.0.1:8025 E2E_SMTP_DSN=smtp://127.0.0.1:1025 npm run test:e2e`; UI lokalnie `node --test --test-isolation=none`.
  - `nv.tmp` (token DT) jest nieśledzony — nie commitować.
  - Push: `git -c credential.helper='!gh auth git-credential' push origin fix/pr16-review` (tylko za zgodą).
