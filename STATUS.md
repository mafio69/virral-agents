# STATUS

## Jak prowadzić

- Stan **na teraz**: plik jest nadpisywany, nie dopisywany. Historia każdej zmiany trafia do `CHANGELOG.md`.
- Sekcje: Aktualnie, Gotowe, Otwarte/niepotwierdzone, Następny priorytet. Krótko, bez opisów przebiegu testów.
- Wyniki testów podawaj tylko ostatnie, z datą.
- Kolejka zadań: DevBrain-Todo, lista „virralAI” (id 62); zadania podawaj jako `#62-<id zadania>` (480–510).

---

**Ostatnia aktualizacja:** 2026-10-26
**Gałąź:** `develop` (otwarty PR #10 `develop` → `master`).
**Ostatnia pełna walidacja (2026-10-26):** `composer check-all` — formatter 0/168, PHP 239/239 (1148 asercji), UI 93/93; Composer strict/platform-reqs OK; E2E 5/5; testy manualne czatu i badania źródeł w przeglądarce potwierdzone przez właściciela.

## Gotowe (w develop / release/v1.0.0)

- **Konto:** rejestracja z obowiązkowym potwierdzeniem e-maila, logowanie z limitem prób (`Retry-After`), za zaufanym proxy limit per rzeczywisty klient (`TRUSTED_PROXIES`), sesja HttpOnly + Bearer, wylogowanie po stronie serwera, CLI resetu hasła.
- **Ochrona danych:** szyfrowanie pól (XChaCha20-Poly1305, `USER_DATA_KEY`), HMAC e-maila, hashe tokenów, migracja CLI `bin/protect-user-data.php`.
- **Czat AI:** OVH, Z.ai (GLM), OpenAI, Anthropic, Gemini, OpenRouter. Połączenia bez klucza są ukryte przy nowej rozmowie (Z.ai zawsze widoczny).
- **Obsługa błędów i logowanie (`Mariusz\Logger\DualLogger`):** wspólne mapowanie wyjątków w `src/middleware.php`; użytkownik nigdy nie widzi surowych błędów debugowania, kodów `HTTP 5xx` ani wewnętrznych nazw zmiennych — zawsze otrzymuje przyjazny komunikat z przeprosinami. Miejsca przechwycenia wyjątków (`middleware.php`, `public/index.php`, `AuthService`, `ChatService`, `ModelCatalogService`, `ChatReplyGenerator`) logują bogaty kontekst diagnostyczny (`type`, `origin`, `line`, `code`, `method`, `path`, `user_id`, `chat_id`, `model`, `reason`, `stage`, `sqlstate`, `driver_code`).
- **WWW i badanie źródeł:** MCP `web_search` w trybie ASK (`MCP_SEARCH_URL` + `MCP_AUTH_TOKEN`), badanie źródeł z kontynuacją (`POST /api/chats/{id}/messages/{messageId}/continue`), izolacja jednego wyszukiwania na jedną wymianę zdań (automatyczne czyszczenie po wygenerowaniu odpowiedzi oraz ręczne czyszczenie `DELETE /api/research` w panelu bocznym i modalu), `chat_messages.kind` (`message`/`approval_request`/`continuation`), fetcher z bezpiecznymi redirectami.
- **Jakość i dokumentacja:** formatter na całym PHP (`src/`, `bin/`, `tests/`), klamry we wszystkich if/else w PHP i JS, testy spójności zmiennych środowiskowych (`EnvNamesConsistencyTest`), dokumentacja zsynchronizowana pod `v1.0.0`.
- **Testy:** E2E 5/5 działa przez zewnętrzny Mailpit `mail-trap.dom` (rejestracja/SMTP, logowanie z błędnym e-mailem/hasłem i poprawnymi danymi, czat, badanie źródeł, diagnostyka MCP).

## Otwarte / wymagane testy manualne przed tagiem `v1.0.0` (100% pass)

- **Wyszukiwarka MCP (`szukaj`):** po ustawieniu `MCP_AUTH_TOKEN` w `.env` i odtworzeniu kontenera `app` — potwierdzić ręczne wyszukanie WWW oraz prośbę AI o WWW z automatyczną kontynuacją (`/continue`).
- **#62-495:** potwierdzić na rzeczywistym modelu kontynuację badania bez błędu „Nie udało się odebrać odpowiedzi” (w razie wyjątku log zawiera teraz `plik:linia` i kod SQLSTATE/PDO).
- **#62-503:** na produkcji sprawdzić limit za proxy (6 błędnych logowań z telefonu, potem logowanie z komputera; w razie wspólnej blokady ustawić `TRUSTED_PROXIES`). HTTPS i healthcheck kontenera nadal otwarte.
- **OpenRouter i dostawcy AI:** sprawdzić widoczność listy połączeń zgodną z kluczami w `.env` oraz odpowiedź na rzeczywistym koncie OpenRouter.
- **Start i poczta prod:** start przez `./prod-start.sh`, dostarczenie e-maila weryfikacyjnego.

## Następny priorytet

1. Przejście powyższej listy testów manualnych w 100% → scalenie `release/v1.0.0` do `develop`, PR `develop` → `master`, deploy na Optiplex i tag `v1.0.0`.
2. #62-486 → #62-493 — jakość źródeł badania.
3. #62-494 — strumieniowanie odpowiedzi (SSE).
