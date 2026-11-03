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

## 2026-11-02 — T3.1 HistoryCompactor (czeka na commit + scalenie + push)

- **Stan:** sesja zamknęła T6.1 (#62-524, `8c1e349`), T6.2 (#62-525, `f079c76`), oba w `develop` na GitHubie; DT zamknięte z notatkami. Teraz `feature/T3.1` z pełną implementacją #62-519 **niezacommitowaną** — walidacja zielona: formatter 0/181, PHPUnit 282/282 (1326 asercji), UI 107/107, E2E 5/5 (Mailpit loopback).
- **Zrobione (T3.1):** `src/Service/HistoryCompactor.php` (ostatnie `context_recent_turns`=8 tur w całości; starsze: tylko user przycięty do 300 znaków, bez obrazów; notice mechaniczne wypadają; `modelContent` przeniesione jako static), `ChatService::historyForModel` deleguje (nowy parametr z defaultem — fixture bez zmian), `config/app.php` → `chat.context_recent_turns`, DI w `dependencies.php`, testy `HistoryCompactorTest` (7), wpisy REPO/CHANGELOG/STATUS.
- **Od czego zacząć:**
  1. Commit na `feature/T3.1`, ff do `develop`, push (`git -c credential.helper='!gh auth git-credential' push origin develop`), `devbrain_todo_done` 519 + notatka z hashem (token `DT_MCP_TOKEN` z `.env` przez awk, bez wyświetlania).
  2. T3.2 (#62-520): pomiar zysku kompaktu — wymaga właściciela (rozmowa testowa przed/po na rzeczywistym modelu; `prompt_tokens` z `chat_usage`; liczby do STATUS). Umów się na przebieg.
- **Pułapki:**
  - E2E lokalnie: `E2E_MAILBOX_URL=http://127.0.0.1:8025 E2E_SMTP_DSN=smtp://127.0.0.1:1025 npm run test:e2e` (`mail-trap.dom` z tej maszyny się nie rozwiązuje; Mailpit stoi na loopback).
  - Push tylko przez jednorazowy helper `gh`; zwykły `git push` pada (HTTPS bez credentiali).
  - Lokalny Node 22: `node --test --experimental-test-isolation=none tests/ui/*.test.mjs`.
  - Tura w kompaktorze = wiadomość użytkownika (też wisząca bez odpowiedzi); N konfigurowalne, min. 1.
