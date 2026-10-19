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

## 2026-10-19 — DT 01 domknięte, kolejka w DevBrain-Todo

- **Gałąź:** `develop` lokalnie, z commitami DT 01 (retry/timeout/E2E) i dokumentacji. **Bez push** — wymaga zgody właściciela; `codex/chat-retry-timeout` usunąć dopiero po udanym push.
- **Kolejka:** DevBrain-Todo, lista „virralAI” (id 62), zadania 01–25 (id 480–504). Rezerwacja komentarzem `[CLAIMED]`, `done` dopiero po weryfikacji.
- **Od czego zacząć:** DT 02 (CI: `develop` w triggerach, `composer lint`, `composer test-ui`), potem PR develop → master na GitHub.
- **Pułapki:**
  - W katalogu leżą poza Git: `http/*` (w tym `http-client.env.json` — może zawierać tokeny, nie czytać, nie commitować), `artifacts/chat-retry-*.png`.
  - E2E wymaga Mailpita uruchamianego przez właściciela (`sudo` z kluczem FIDO — agent nie ma terminala do autoryzacji).
  - `ChatRepository` sprawdza istnienie `chat_reply_attempts` przy odczytach do czasu migracji na instalacji.
  - 6 starych zadań Multi-AI w DT (id 386–391, 2026-08-30) wygląda na wykonane — czeka na decyzję właściciela.
