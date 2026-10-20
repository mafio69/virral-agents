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

## 2026-10-21 — Uwagi z przeglądu wydania 1.0

- **Gałąź:** `fix/release-1.0-readiness` scalona do `develop`; nowsze od `982f1a8` commity czekają na PR do `master` i deploy właściciela (Optiplex).
- **Kolejka:** DevBrain-Todo, lista „virralAI” (62). #62-495 i #62-503 otwarte — czekają na sprawdzenie na produkcji (lista w `STATUS.md`).
- **Od czego zacząć:** wynik deployu od właściciela; po zielonych sprawdzeniach tag `v1.0.0`. Potem #62-486.
- **Pułapki:**
  - Poza Git: `http/*` (może zawierać tokeny — nie czytać), `artifacts/chat-retry-*.png`, `.php-cs-fixer.dist.php`, `bin/check-env-syntax.php`, zmiana `.gitignore` właściciela — nie commitować bez prośby.
  - Formatter obejmuje teraz `bin/` — nowy plik w `bin/` musi być zgodny z PSR-12, inaczej `check-all` jest czerwone.
  - E2E wysyła pocztę do `mail-trap.dom:1025` (wspólna skrzynka testowa), API `http://mail-trap.dom`.
  - Opisy poleceń Bash i wyjaśnienia przed nimi — po polsku.
  - `sudo` wymaga klucza FIDO — polecenia z sudo uruchamia właściciel w zwykłym terminalu.
