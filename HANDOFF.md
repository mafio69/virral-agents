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

## 2026-11-09 — port resetu hasła na `feature/password-reset-port`

- **Gałąź:** `feature/password-reset-port` (z `develop`), ostatni commit `eb66abe` + dokumentacja (CHANGELOG/STATUS/HANDOFF — do commitnięcia). Walidacja: PHPUnit 317/317, UI 112/112, formatter 0/191.
- **Zrobione:** cherry-pick `2b4f806` (reset hasła z linii archiwalnej) na nową linię; rozwiązane konflikty w README/REPO (zachowany DeepSeek + Langfuse z bieżącej linii); kod zgodny z REPO.md (`resetPasswordWithToken()`, prefiksy `reset-limit/`, `history.replaceState`).
- **Od czego zacząć:** commit docs (jeśli nie zrobione) → push `feature/password-reset-port` → PR do `develop` → scalenie → test manualny resetu w przeglądarce z prawdziwą pocztą + test E2E przez `mail-trap.dom`.
- **Pułapki:**
  - Dwie linie historii są już scalone: stara linia zarchiwizowana w `backup/old-develop` (porzucić po potwierdzeniu, że reset hasła działa).
  - PR #22 (`develop` → `master`) otwarty — domyka composer.lock/skills/test dev-start.
  - Rootless Docker: `./dev-start.sh` bez sudo, port 8182.
  - E2E: `E2E_MAILBOX_URL=http://127.0.0.1:8025 E2E_SMTP_DSN=smtp://127.0.0.1:1025 npm run test:e2e`; UI lokalnie `node --test --test-isolation=none`.
  - Push: `git -c credential.helper='!gh auth git-credential' push origin <branch>` (tylko za zgodą).
