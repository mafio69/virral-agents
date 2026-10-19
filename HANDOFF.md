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

## 2026-10-19 — #62-480, #62-481, #62-482 zamknięte

- **Gałąź:** `develop` (lokalnie przed push sprzątania). `master` zaktualizowany przez właściciela na GitHub.
- **Kolejka:** DevBrain-Todo, lista „virralAI” (62). Zadania podawaj jako `#62-<id>` (480–504), nie numerem z tytułu. Rezerwacja `[CLAIMED]`, `done` po spełnieniu DoD.
- **Od czego zacząć:** #62-483 (kody przyczyn nieudanego fetch), potem kolejno do #62-493.
- **Pułapki:**
  - Poza Git: `http/*` (`http-client.env.json` może zawierać tokeny — nie czytać, nie commitować), `artifacts/chat-retry-*.png`, `.junie/plans/`.
  - 3 stare stashe `research-source-selection: paused …` do usunięcia przez właściciela (`git stash drop` zablokowany dla agenta).
  - Na GitHub wisi zdalna gałąź `origin/codex/chat-retry-timeout` — usunięcie po stronie właściciela.
  - E2E wymaga Mailpita uruchamianego przez właściciela (`sudo` z kluczem FIDO).
  - 6 starych zadań Multi-AI w DT (id 386–391) — decyzja właściciela.
