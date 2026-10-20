# STATUS

## Jak prowadzić

- Stan **na teraz**: plik jest nadpisywany, nie dopisywany. Historia każdej zmiany trafia do `CHANGELOG.md`.
- Sekcje: Aktualnie, Gotowe, Otwarte/niepotwierdzone, Następny priorytet. Krótko, bez opisów przebiegu testów.
- Wyniki testów podawaj tylko ostatnie, z datą.
- Kolejka zadań: DevBrain-Todo, lista „virralAI” (id 62); zadania podawaj jako `#62-<id zadania>` (480–510).

---

**Ostatnia aktualizacja:** 2026-10-20
**Gałąź:** `develop` wypchnięty na GitHub; `master` (PR #8) zawiera stan do `982f1a8`, nowsze commity czekają na PR do `master`. Deploy na serwer prod (Optiplex) wykonuje właściciel.
**Ostatnia pełna walidacja (2026-10-20):** `composer check-all` — formatter 0/51, PHP 215/215 (1051 asercji), UI 85/85. E2E nie powtarzane (wymaga skrzynki testowej).

## Gotowe (w develop)

- **Konto:** rejestracja z obowiązkowym potwierdzeniem e-maila, logowanie z limitem prób (`Retry-After`), sesja HttpOnly + Bearer, wylogowanie po stronie serwera, CLI resetu hasła.
- **Ochrona danych:** szyfrowanie pól (XChaCha20-Poly1305, `USER_DATA_KEY`), HMAC e-maila, hashe tokenów, migracja CLI `bin/protect-user-data.php`.
- **Czat AI:** OVH, Z.ai (GLM), OpenAI, Anthropic, Gemini, OpenRouter (`OPENROUTER_API_KEY`, opcjonalnie `OPENROUTER_MODELS`, `data_collection: deny`). Połączenia bez klucza są ukryte przy nowej rozmowie (Z.ai zawsze widoczny); stare rozmowy dostają `missing_configuration`.
- **Obsługa błędów:** kontrolery dane → mapper → DTO → serwis, bez try/catch; wspólne mapowanie wyjątków w `src/middleware.php` (422/401/403/404/405/429/503/500), JSON z `JSON_INVALID_UTF8_SUBSTITUTE`.
- **WWW:** MCP `web_search` w trybie ASK, badanie źródeł; fetcher z redirectami (≤3, bez HTTPS→HTTP, kody `too_many_redirects`/`redirect_loop`/`insecure_redirect`), tytuł i fragment w evidence także dla nieudanych źródeł (#62-484, #62-485).
- **Infrastruktura:** `./dev-start.sh` (zawsze dev) i `./prod-start.sh` (prod), zatrzymanie gdy kontener app nie wstaje; poczta przez `MAIL_DSN` (dev: zewnętrzny `mail-trap.dom`, Mailpit usunięty); `/test-api` i linki pulpitu tylko w dev; `qodana.yaml`.
- **Dokumentacja:** README z celem repo, tabelą integracji i instrukcją OpenRouter.

## Otwarte / niepotwierdzone

- OpenRouter na rzeczywistym koncie — niesprawdzony (testy tylko na atrapach).
- Po deployu do sprawdzenia: lista połączeń w czacie zgodna z kluczami, start przez `./prod-start.sh`, poczta produkcyjna.
- E2E (`docs/E2E.md`) nadal zakłada osobnego Mailpita 18025/11025 — do przepięcia na `mail-trap.dom`, jeśli ma API.
- Błąd „Nie udało się odebrać odpowiedzi” po kontynuacji badania — niezdiagnozowany (#62-495).
- Qodana: 91 ifów bez klamer (masowa zmiana formatera — decyzja właściciela).

## Następny priorytet

1. PR `develop` → `master`, deploy na Optiplex (właściciel).
2. #62-486 → #62-493 — jakość źródeł badania.
3. #62-494 — strumieniowanie odpowiedzi (SSE); #62-495 — diagnoza błędu odbioru odpowiedzi.
