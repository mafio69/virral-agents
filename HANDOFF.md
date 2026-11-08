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

## 2026-11-08 — DeepSeek (niezacommitowane) + czerwony test Gemini

- **Gałąź:** `develop` (ostatni `4987be3`).
- **Zrobione w tej sesji (niezacommitowane):** integracja DeepSeek — `src/Infrastructure/AI/DeepSeekProvider.php`, wiring w `src/settings.php` + `src/dependencies.php` + `docker-compose.yml` + `docker/php-fpm-env.conf`, testy `tests/DeepSeekProviderTest.php` (7/7), docs (README, AI_PROVIDERS, KONFIGURACJA), rytuał końca (CHANGELOG/STATUS/REPO). Reset hasła z limitem `PasswordResetLimiter` został scalony wcześniej (`2b4f806`) — nie ruszać. Do tego: globalny config opencode ma `mcp.devbrain` (token z `nv.tmp`, poza repo).
- **Od czego zacząć:** naprawa `EmptyReplyTest` „Gemini" (czerwony na develop: `GeminiProvider` przy pustej treści z `finishReason: MAX_TOKENS` ma rzucać `EmptyReply::exception(..., limitReached: true, candidatesTokenCount, thoughtsTokenCount)`; wzorzec mapowania w `EmptyReplyTest` wiersz ~55). Potem commit DeepSeek (tylko za zgodą właściciela) i test manualny po wklejeniu `DEEPSEEK_API_KEY` do `.env` + `--force-recreate app`.
- **Pułapki:**
  - `DEEPSEEK_BASE_URL_ANTHROPIC` w `.env.example` to celowo niespaiwana notatka właściciela (wyjście awaryjne) — nie podłączać.
  - `GIT-FLOW.md` zawiera ręczną edycję właściciela („weewewew") — plik tylko dla człowieka, nie cofać bez zgody.
  - `.env.example` ma notatki DeepSeek właściciela (komentarze + puste zmienne) — bezpieczne do commitu, ale to jego zmiana.
  - Właściciel: „nie trać czasu na badanie kodu" — czytać tylko to, co konieczne do zadania.
  - E2E: `E2E_MAILBOX_URL=http://127.0.0.1:8025 E2E_SMTP_DSN=smtp://127.0.0.1:1025 npm run test:e2e`; UI lokalnie `node --test --experimental-test-isolation=none`.
  - Push: `git -c credential.helper='!gh auth git-credential' push origin develop` (tylko za zgodą).
