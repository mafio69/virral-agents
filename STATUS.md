# STATUS

## Jak prowadzić

- Stan **na teraz**: plik jest nadpisywany, nie dopisywany. Historia każdej zmiany trafia do `CHANGELOG.md`.
- Sekcje: Aktualnie, Gotowe, Otwarte/niepotwierdzone, Następny priorytet. Krótko, bez opisów przebiegu testów.
- Wyniki testów podawaj tylko ostatnie, z datą.
- Kolejka zadań: DevBrain-Todo, lista „virralAI” (id 62); zadania podawaj jako `#62-<id zadania>` (480–510).

---

**Ostatnia aktualizacja:** 2026-11-09
**Gałąź:** `feature/password-reset-port` (reset hasła). DeepSeek i rootless scalone do mastera (PR #18, #21); PR #22 `develop` → `master` otwarty (MERGEABLE).
**Ostatnia walidacja (2026-11-09):** formatter 0/191, PHPUnit 317/317 (1423 asercji), UI 112/112.

## Gotowe (w develop / release/v1.0.0)

- **Konto:** rejestracja z obowiązkowym potwierdzeniem e-maila, logowanie z limitem prób (`Retry-After`), za zaufanym proxy limit per rzeczywisty klient (`TRUSTED_PROXIES`), sesja HttpOnly + Bearer, wylogowanie po stronie serwera, reset zapomnianego hasła linkiem e-mail ze strony logowania (limit prób, token ważny 1 h).
- **Ochrona danych:** szyfrowanie pól (XChaCha20-Poly1305, `USER_DATA_KEY`), HMAC e-maila, hashe tokenów, migracja CLI `bin/protect-user-data.php`.
- **Czat AI:** OVH, Z.ai (GLM), OpenAI, Anthropic, Gemini, OpenRouter, DeepSeek (`deepseek-flash`, `deepseek-v4-pro`; `DEEPSEEK_API_KEY`, opcjonalnie `DEEPSEEK_BASE_URL`). Połączenia bez klucza są ukryte przy nowej rozmowie (Z.ai zawsze widoczny).
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

## Cel: MVP = wyszukiwarka + „odśnieżarka”

„Odśnieżarka” — usprawnienia ograniczające efekt kuli śnieżnej w czacie (każda tura wysyła modelowi historię, więc rosną koszt, czas i ryzyko gubienia wątku). Od T3.1 `ChatService::historyForModel()` używa `HistoryCompactora`: ostatnie 8 tur w całości, starsze tylko pytania użytkownika skrócone do 300 znaków (bez starych odpowiedzi i obrazów — historia dalej rośnie z liczbą tur, ale wolniej); `chat_usage` zapisuje `prompt_tokens` odpowiedzi, które zwróciły `usage`.

**Wyszukiwarka (domknięcie):**
1. Testy manualne z sekcji wyżej: MCP `szukaj` (ręczne wyszukanie + prośba AI z `/continue`) oraz #62-495.

**Odśnieżarka (propozycja kolejności, każdy krok osobnym zadaniem — decyzja właściciela):**
1. Pomiar — **kod gotowy**: tokeny lądują w `chat_usage`, gdy dostawca zwróci `usage`; udana tura zostawia ślad `chat-turn-<n>` w Langfuse (wyjątek w trakcie tury nie zostawia ani tokenów, ani śladu). Do zrobienia: rozmowa testowa i sprawdzenie śladu w Langfuse przez właściciela. Punkt odniesienia z rozmowy 39 (przed poprawką): wejście 849 → 9645 tokenów w 8 turach.
2. Obrazy z dawnych tur nie są wysyłane ponownie (zastępuje je krótka notka).
2a. **Treść pobranych źródeł** — pomiar z rozmowy 41 (`openai/gpt-4o`, 2026-11-01): zwykła tura ~1,4 tys. tokenów wejścia, kontynuacja badania z pobranymi stronami ~26–27 tys. (limit 100 tys. znaków kontekstu). Po odpowiedzi źródła nie wracają do historii. Kandydaci: niższy limit kontekstu badania, skracanie stron, streszczenie strony przed opracowaniem. Duży kontekst źródeł wyczerpał też limit odpowiedzi modelu rozumującego `gpt-5-nano` (rozmowa 40; pusta odpowiedź to na tej linii kod `invalid_response`).
3. Budżet historii: do modelu trafia tylko ostatnia część rozmowy mieszcząca się w limicie (pierwsza wiadomość użytkownika zawsze).
4. Streszczenie starszej części rozmowy (szyfrowane, odświeżane po przekroczeniu budżetu).
5. UI: wskaźnik rozmiaru kontekstu i propozycja „nowa rozmowa ze streszczeniem”.

## Następny priorytet

1. Scalanie PR #22 (`develop` → `master`) i PR resetu hasła (`feature/password-reset-port` → `develop`).
2. Reset hasła: test manualny w przeglądarce z prawdziwą pocztą; osobne zadanie: test E2E przepływu resetu przez `mail-trap.dom`.
3. MVP: domknięcie wyszukiwarki (testy manualne MCP `szukaj` + #62-495), potem odśnieżarka krok 1.
4. Pozostałe testy manualne z listy → deploy na Optiplex i tag `v1.0.0`.
5. Osobne zadanie: `bin/restore-dev-user.php` — `getenv(...) ?? default` nie działa (getenv zwraca `false`).
6. Po MVP: #62-486 → #62-493 (jakość źródeł badania), #62-494 (SSE).
