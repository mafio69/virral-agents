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

## 2026-11-08 (2) — DeepSeek + fixy drugiego przeglądu na `feature/deepseek`

- **Gałąź:** `feature/deepseek` (744f866 DeepSeek, 212887e skille, 3f954e1 fix EmptyReply, + fixy drugiego przeglądu — patrz CHANGELOG 2026-11-08 (2)). Walidacja: PHPUnit 294/294, UI 109/109, formatter 0/183. PR #16 (fixy pierwszego przeglądu) scalony do mastera.
- **Fixy drugiego przeglądu:** usage DeepSeek przez `MultimodalClientInterface`, fallback `LANGFUSE_BASE_URL` w settings + wpisy FPM, README/.env.example/REPO/STATUS zgodne z tą linią (bez `check-langfuse.php`, `span()` zamiast `chatReply()`), run-workflow skill (zgoda, ScriptPath, `pipeline` dla tego samego pliku), docstringi.
- **WAŻNE — dwie linie historii:** lokalny `develop` (`bafac2d`: reset hasła, Langfuse z `chatReply()`/`EmptyReply`/`TokenUsage`, `bin/check-langfuse.php`) NIE jest pushnięty i częściowo koliduje z tą linią (inny API LangfuseTrace). Decyzja właściciela: scalić świadomie (portować `output_limit`/reset hasła jako zadania) albo porzucić. Nie mieszać automatycznie.
- **Od czego zacząć:** push `feature/deepseek` za zgodą → obserwować CodeRabbit/CI; potem test manualny DeepSeeka (klucz w `.env`, `./dev-start.sh`, wybór połączenia w czacie).
- **Pułapki:**
  - Stash `stash@{0}`/`stash@{1}` to kopie robocze DeepSeeka — po scaleniu można dropnąć.
  - `nv.tmp` (token DT) w `.gitignore`; nigdy nie commitować.
  - E2E: `E2E_MAILBOX_URL=http://127.0.0.1:8025 E2E_SMTP_DSN=smtp://127.0.0.1:1025 npm run test:e2e`.
  - Push: `git -c credential.helper='!gh auth git-credential' push origin feature/deepseek` (tylko za zgodą).
