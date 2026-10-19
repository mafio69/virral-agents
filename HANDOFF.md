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

## 2026-10-19 — #62-483 zamknięte, dokumentacja zaktualizowana

- **Gałąź:** `develop`; lokalne commity `684fbf7`, `1fee123`, `733edb9` (+ commit dokumentacji) czekają na push właściciela.
- **Kolejka:** DevBrain-Todo, lista „virralAI” (62). Zadania jako `#62-<id>` (480–508), nie numerem z tytułu. Rezerwacja `[CLAIMED]`, `done` po spełnieniu DoD. Zamknięte: 480–483, 505–508.
- **Od czego zacząć:** #62-484 (tytuł i fragment ≤400 znaków zawsze w evidence, także przy `failed`/`denied`), potem kolejno do #62-493.
- **Pułapki:**
  - Poza Git: `http/*` (`http-client.env.json` może zawierać tokeny — nie czytać, nie commitować), `artifacts/chat-retry-*.png`, `.junie/plans/`.
  - 3 stare stashe `research-source-selection: paused …` do usunięcia przez właściciela (`git stash drop` zablokowany dla agenta).
  - Zdalna gałąź `origin/codex/chat-retry-timeout` — usunięcie po stronie właściciela.
  - E2E wymaga Mailpita uruchamianego przez właściciela (`sudo` z kluczem FIDO); po #62-483 nie powtarzane.
  - Plik środowiska: nazwy zmiennych bez myślników (dotenv rzuca `InvalidFileException` → migrator nie startuje → 502).
  - 6 starych zadań Multi-AI w DT (id 386–391) — decyzja właściciela.
