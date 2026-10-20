# STATUS

## Jak prowadzić

- Stan **na teraz**: plik jest nadpisywany, nie dopisywany. Historia każdej zmiany trafia do `CHANGELOG.md`.
- Sekcje: Aktualnie, Gotowe, Otwarte/niepotwierdzone, Następny priorytet. Krótko, bez opisów przebiegu testów.
- Wyniki testów podawaj tylko ostatnie, z datą.
- Kolejka zadań: DevBrain-Todo, lista „virralAI” (id 62); zadania podawaj jako `#62-<id zadania>` (480–510).

---

**Ostatnia aktualizacja:** 2026-10-21
**Gałąź:** `develop` zawiera uwagi z przeglądu wydania 1.0; `master` (PR #8) zawiera stan do `982f1a8`, nowsze commity czekają na PR do `master`. Deploy na serwer prod (Optiplex) wykonuje właściciel.
**Ostatnia pełna walidacja (2026-10-21):** `composer check-all` — formatter 0/164, PHP 227/227 (1086 asercji), UI 86/86; E2E 4/4 (poczta przez `mail-trap.dom`).

## Gotowe (w develop)

- **Konto:** rejestracja z obowiązkowym potwierdzeniem e-maila, logowanie z limitem prób (`Retry-After`), za zaufanym proxy limit per rzeczywisty klient (`TRUSTED_PROXIES`), sesja HttpOnly + Bearer, wylogowanie po stronie serwera, CLI resetu hasła.
- **Ochrona danych:** szyfrowanie pól (XChaCha20-Poly1305, `USER_DATA_KEY`), HMAC e-maila, hashe tokenów, migracja CLI `bin/protect-user-data.php`.
- **Czat AI:** OVH, Z.ai (GLM), OpenAI, Anthropic, Gemini, OpenRouter. Połączenia bez klucza są ukryte przy nowej rozmowie (Z.ai zawsze widoczny).
- **Obsługa błędów:** wspólne mapowanie wyjątków w `src/middleware.php`; nieoczekiwane błędy logują miejsce oraz SQLSTATE/kod sterownika dla PDO.
- **WWW:** MCP `web_search` w trybie ASK, badanie źródeł, fetcher z bezpiecznymi redirectami.
- **Jakość:** formatter na całym PHP (`src/`, `bin/`, `tests/`), klamry we wszystkich if/else w PHP i JS.
- **Testy:** E2E znów działa przez zewnętrzny Mailpit `mail-trap.dom`.

## Otwarte / niepotwierdzone

- #62-495: przyczyna „Nie udało się odebrać odpowiedzi” niepotwierdzona — po deployu jedna kontrolowana kontynuacja badania na rzeczywistym modelu; log poda teraz miejsce i kod błędu.
- #62-503: na produkcji sprawdzić limit za proxy (6 błędnych logowań z telefonu, potem logowanie z komputera); w razie blokady ustawić `TRUSTED_PROXIES`. HTTPS i healthcheck kontenera nadal otwarte.
- OpenRouter na rzeczywistym koncie — niesprawdzony (testy tylko na atrapach).
- Po deployu: lista połączeń zgodna z kluczami, start przez `./prod-start.sh`, poczta produkcyjna.

## Następny priorytet

1. PR `develop` → `master`, deploy na Optiplex (właściciel), sprawdzenia z listy wyżej → tag `v1.0.0`.
2. #62-486 → #62-493 — jakość źródeł badania.
3. #62-494 — strumieniowanie odpowiedzi (SSE).
