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

## 2026-10-20 — OpenRouter, start dev/prod, ukryte połączenia, przepływ błędów

- **Gałąź:** `develop` wypchnięty; nowsze od `982f1a8` commity czekają na PR do `master` i deploy właściciela (Optiplex).
- **Kolejka:** DevBrain-Todo, lista „virralAI” (62), zadania jako `#62-<id>`. Zamknięte: 480–485, 505–510.
- **Od czego zacząć:** potwierdzić z właścicielem wynik deployu (lista połączeń, OpenRouter, poczta), potem #62-486 (realistyczny User-Agent i Accept-Language w `config/app.php`).
- **Pułapki:**
  - Poza Git: `http/*` (może zawierać tokeny — nie czytać), `artifacts/chat-retry-*.png`, `.php-cs-fixer.dist.php`, zmiana `.gitignore` właściciela — nie commitować bez prośby.
  - Błąd składni `.env` → migrator pada → kontener app kończy się → Nginx „host not found in upstream app”; `dev-start.sh` to wykrywa.
  - Zmiana kluczy w `.env` wymaga `--force-recreate app`; bez klucza połączenie znika z listy nowej rozmowy.
  - `sudo` wymaga klucza FIDO — polecenia z sudo uruchamia właściciel w zwykłym terminalu.
