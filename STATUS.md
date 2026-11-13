# STATUS

## Jak prowadzić

- Stan **na teraz**: plik jest nadpisywany, nie dopisywany. Historia każdej zmiany trafia do `CHANGELOG.md`.
- Sekcje: Aktualnie, Gotowe, Otwarte/niepotwierdzone, Następny priorytet. Krótko, bez opisów przebiegu testów.
- Wyniki testów podawaj tylko ostatnie, z datą.
- Kolejka zadań: DevBrain-Todo, lista „virralAI” (id 62); zadania podawaj jako `#62-<id zadania>` (480–510).

---

**Ostatnia aktualizacja:** 2026-11-09
**Gałąź:** `develop` (DeepSeek + reset hasła + role + CRUD dostawców scalone; master = `15e2ac8` po PR #24).
**Ostatnia walidacja (2026-11-09):** formatter 0/200, PHPUnit 323/323 (1451 asercji), UI 113/113.

## Gotowe (w develop / release/v1.0.0)

- **Konto:** rejestracja z obowiązkowym potwierdzeniem e-maila, logowanie z limitem prób (`Retry-After`), za zaufanym proxy limit per rzeczywisty klient (`TRUSTED_PROXIES`), sesja HttpOnly + Bearer, wylogowanie po stronie serwera, reset zapomnianego hasła linkiem e-mail ze strony logowania (limit prób, token ważny 1 h).
- **Role i uprawnienia:** `users.role` (`admin`/`user`); pierwszy użytkownik = admin, kolejni = user (nigdy admin); `AuthMiddleware` dokłada `role`, `AdminOnlyMiddleware` zwraca 404 nie-adminowi.
- **Zarządzanie dostawcami AI (admin-only):** `GET/POST/PUT/DELETE /api/ai-providers`, `POST/DELETE /api/ai-models`; sekret dostawcy nigdy nie jest zwracany (tylko `has_secret`), plaintext szyfrowany w bazie (`SecretService`).
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

1. **Faza 4:** UI panel „Dostawcy AI" (dodawanie/edycja/usuwanie, maskowane pole klucza) na endpointach z Fazy 2.
2. **Faza 3:** generalizacja rejestru db — dostawcy z UX widoczni i wybieralni w czacie (nachodzi na #62-497).
3. Naprawa `TaskController` — `int $id` w `update`/`delete` nie rzutuje argumentu trasy (`TypeError` w strict mode); wzór: `(int)$request->getAttribute('id')`.
4. **Faza 5 (później):** memory per `dostawca:model`.
5. Reset hasła: test manualny w przeglądarce z prawdziwą pocztą + E2E przez `mail-trap.dom`.
6. MVP: domknięcie wyszukiwarki (testy manualne MCP `szukaj` + #62-495), potem odśnieżarka krok 1.
7. Pozostałe testy manualne → deploy na Optiplex i tag `v1.0.0`.
