# REPO.md — mapa techniczna repozytorium

> **Cel:** skrócić wejście w projekt. Zapisuj tu tylko trwałe fakty: architekturę, sposób uruchamiania, istotne mechanizmy, świadome decyzje i pułapki. Dzięki temu agent lub programista nie musi ponownie przeszukiwać kodu ani powtarzać starych błędów.
>
> To nie jest instrukcja polityk ani dziennik prac: zasady pracy są w `AGENTS.md` / `GIT-FLOW.md`, bieżący stan w `STATUS.md`, przekazanie sesji w `HANDOFF.md`, a historia zmian w `CHANGELOG.md`.

## 1. Przeznaczenie

- **Problem rozwiązywany:** prywatny, wielodostawcowy czat AI z kontrolowanym (zatwierdzanym przez użytkownika) dostępem do WWW i szyfrowaną historią.
- **Główni użytkownicy:** właściciel instalacji i niewielka liczba kont z potwierdzonym e-mailem (polskojęzyczny UI).
- **Najważniejszy przepływ:** wiadomość (+ opcjonalny obraz / zatwierdzone wyszukanie / wybrane źródła) → `ChatService` → generator odpowiedzi → klient wybranego dostawcy AI → szyfrowany zapis pary wiadomości → odpowiedź JSON → render Markdown (DOMPurify).
- **Granice systemu:** serwer wyszukiwania MCP (repo `searxng`, kontener `mcp-search`) jest osobnym projektem; tutaj jest wyłącznie klient. Brak własnego modelu AI, brak płatności, brak publicznej rejestracji bez e-maila. Moduł zadań (`/api/tasks`) to prosty CRUD poboczny.

## 2. Uruchamianie

| Cel | Polecenie | Istotna uwaga |
|---|---|---|
| Instalacja | `composer install` | PHP ^8.4 z `pdo_sqlite`, `sodium`; lokalnie bywa 8.5, CI używa 8.4 |
| Development | `./dev-start.sh dev` | Compose + Mailpit (`127.0.0.1:8025`), `APP_ENV=dev`, aplikacja `http://localhost:8181`; Docker przez sudo |
| Produkcja (lokalnie) | `./dev-start.sh prod` | wymusza `APP_ENV=prod`, zatrzymuje Mailpit; `--dry-run` tylko wypisuje komendy |
| Migracje | automatycznie w `docker/start-app.sh` (`php bin/migrate.php`) | błąd migracji blokuje start FPM; poza Compose operator uruchamia migrator ręcznie |
| Testy kompletne | `composer check-all` | formatter (dry-run) + PHPUnit + testy UI Node; musi przejść przed commitem |
| Walidacja Composer | `composer validate --strict --no-check-publish` | tak samo jak w GitHub Actions |
| E2E (opcjonalnie) | `npm run test:e2e` / `npm run test:local` | Playwright + osobny Mailpit na 18025/11025; szczegóły `docs/E2E.md` |
| Build | brak | frontend to statyczne moduły ES w `public/ui`, bez bundlera |

Sekrety (klucze dostawców, `USER_DATA_KEY`, `DB_HASH`, `MAIL_DSN`) trafiają do kontenera wyłącznie przez zmienne Compose → `docker/php-fpm-env.conf`. Nie wpisuj ich do `config/app.php` ani dokumentacji.

## 3. Architektura

```text
przeglądarka: public/ui/landing.html, public/ui/chat.html (+ moduły public/ui/chat/*.js)
        ↓  fetch JSON, cookie HttpOnly / Bearer
Nginx :8181 → PHP-FPM: public/index.php (Slim 4, PHP-DI)
        ↓  src/routes.php → AuthMiddleware → Controller → Mapper → DTO
Service: AuthService, ChatService, ChatReplyGenerator, ResearchService, ModelCatalogService, TaskService
        ↓
Repository (ProtectedStore, szyfrowanie pól) → SQLite var/database/app.sqlite
Infrastructure: AI providers (OVH, Z.ai, OpenAI, Anthropic, Gemini), McpSearchClient → mcp-search:8000,
                PublicWebPageFetcher → publiczne strony, MailService → SMTP
```

| Obszar | Lokalizacja | Odpowiedzialność |
|---|---|---|
| Wejście aplikacji | `public/index.php`, `src/settings.php`, `src/dependencies.php`, `src/middleware.php` | konfiguracja, DI, sprawdzenie gotowości bazy (bez migracji), obsługa błędów |
| Interfejs / API | `src/routes.php`, `src/Controller/*`, `src/Mapper/*`, `src/Middleware/AuthMiddleware.php` | cienkie kontrolery; walidacja wejścia w mapperach |
| Logika domenowa | `src/Service/*` | rozmowy, wybór modelu, generowanie odpowiedzi, badanie źródeł, konto |
| Dane | `src/Repository/*`, `src/Infrastructure/Database/*`, `src/Infrastructure/DataProtection/*` | SQLite przez PDO, migracje idempotentne, szyfrowanie pól |
| Integracje | `src/Infrastructure/AI/*`, `src/Infrastructure/Search/*`, `src/Infrastructure/Mail/*` | dostawcy AI, MCP, fetch stron, poczta |
| Frontend | `public/ui/chat/*.js`, `public/ui/theme.css`, `public/ui/lib/*` | stan czatu (`chat-controller.js`), transport (`chat-api.js`), badanie (`chat-research*.js`) |
| CLI | `bin/*.php` | migracje, ochrona danych, reset hasła, diagnostyka MCP |
| Testy | `tests/*.php`, `tests/ui/*.test.mjs`, `tests/e2e/`, `tests/browser/` | PHPUnit z SQLite w pamięci i atrapami HTTP; Node test runner; Playwright; routery atrap do testów ręcznych |

## 4. Mechanizmy krytyczne

| Mechanizm | Jak działa | Gdzie szukać |
|---|---|---|
| Autoryzacja | `SessionTokenResolver` (Bearer ma pierwszeństwo przed cookie) → `AuthMiddleware` ustawia atrybut `userId`; sesja wymaga potwierdzonego e-maila. Katalog modeli/połączeń jest publiczny | `src/Http`, `src/Middleware`, `AuthService` |
| Szyfrowanie danych | `ProtectedStore` szyfruje pola z `ProtectedFields` i wiąże szyfrogram z tabelą/polem/ID/właścicielem; bez `USER_DATA_KEY` lub znacznika migracji aplikacja zwraca 503 | `src/Infrastructure/DataProtection`, `docs/OCHRONA_DANYCH.md` |
| Wybór modelu | `ModelSelector` rozdziela `provider:model`; rozmowa jest związana z dostawcą — zmiana dostawcy wymaga nowej rozmowy (historia nie jest przekazywana innemu dostawcy) | `src/Dto/Chat/ModelSelector.php`, `ProviderRegistry` |
| Zapis odpowiedzi | wiadomość użytkownika jest zapisywana **przed** wywołaniem AI; po błędzie UI odczytuje rozmowę zamiast ponownie wysyłać POST (ochrona przed duplikatem) | `ChatService::addMessage`, `chat-controller.js` |
| Zgoda na WWW (ASK) | każde wyszukanie i fetch wymaga jawnej decyzji; zgoda jest jednorazowa i nie przechodzi na kolejną wiadomość; model nie może sam uruchomić sieci (prośby AI tworzą tylko oczekującą decyzję) | `ResearchService`, `ChatReplyGenerator`, `chat-search.js` |
| Fetch stron | tylko publiczny IPv4 (ochrona SSRF, połączenie przypięte do IP), HTML/tekst, bez redirectów, cookies i JS | `PublicWebPageFetcher`, `PublicWebUrl` |
| Migracje | `bin/migrate.php` przed startem FPM; HTTP tylko `assertReady`. Inicjalizacja szyfrowania automatyczna wyłącznie dla pustej bazy | `docker/start-app.sh`, `DatabaseInitializer`, `MigrationRunner` |
| Błędy dostawców | UI pokazuje komunikaty z zamkniętej listy kodów (`X-AI-Error`), nigdy treści odpowiedzi dostawcy ani wyjątków | `AiConnectionException`, `chat-api.js` |

## 5. Decyzje i pułapki

| Temat | Przyjęte rozwiązanie | Dlaczego / czego nie robić |
|---|---|---|
| Konfiguracja | jawne ustawienia w `config/app.php`, sekrety tylko ze środowiska procesu (`src/settings.php`) | nie przenosić kluczy do `config/app.php`; zmiana Compose/FPM wymaga odtworzenia kontenerów przez operatora |
| Nazwy kluczy | `OPEN_AI_KEY`, `GEMINI_KEY`, `ANTHROPIC_API_KEY`, `ZAI_API_KEY`, `OVH_AI_ENDPOINTS_ACCESS_TOKEN` | nazwy wybrane przez właściciela; nie „poprawiać” na `OPENAI_API_KEY` itp. |
| Sieć MCP | aplikacja w sieciach `default` i zewnętrznej `ai`; `MCP_SEARCH_URL=http://mcp-search:8000/mcp` | brak sieci `ai` = `search.status: unavailable` (DNS); PHP na hoście potrzebuje `http://127.0.0.1:8890/mcp` |
| Podgląd UI | otwierać `http://localhost:8181/chat` | `file:///…/chat.html` nie działa (brak API); po zmianie JS podbić `?v=` w importach, bo przeglądarka trzyma cache modułów |
| Reverse proxy | limit logowań liczy `REMOTE_ADDR` | `X-Forwarded-For` celowo ignorowany; za proxy trzeba skonfigurować zaufany peer po stronie serwera WWW |
| Szyfrowanie | szyfrowane są wybrane pola, nie cały plik SQLite | metadane (czasy, ID, liczba wiadomości) pozostają jawne; dostawca AI widzi treść rozmowy |
| Git | gałęzie z `develop`, push tylko `develop`, `master` tylko przez PR | patrz `GIT-FLOW.md`; nie scalać lokalnie do `master` |
| Testy UI | `node --test --test-isolation=none` | w ograniczonym środowisku agenta izolacja procesów zawodzi |

## 6. Stałe operacyjne

- **Porty:** aplikacja `8181` (Nginx), Mailpit dev `127.0.0.1:8025` (HTTP) / `127.0.0.1:1025` (SMTP), MCP w sieci Docker `mcp-search:8000`, E2E `18181/18182` + Mailpit `18025/11025`.
- **Limity / timeouty:**
  - logowanie: 5 nieudanych prób / 900 s osobno dla IP i e-maila (`LoginAttemptLimiter`); hasło min. 10 znaków;
  - obraz wejściowy: max 512 KiB po stronie serwera (`ChatImageValidator::MAX_BYTES`), przeglądarka skaluje do 1600 px; obraz generowany Gemini: max 8 MiB;
  - MCP: odpowiedź max 1 MiB, timeout 45 s; wspierane wersje protokołu w `McpSearchClient::SUPPORTED_PROTOCOLS`;
  - fetch: 1 MiB / 16 000 znaków na stronę; badanie: 20 źródeł, 100 000 znaków kontekstu, 80 zapamiętanych wykluczeń;
  - AI: OVH `ovh_ai.timeout` 120 s (`config/app.php`), katalog modeli 30 s.
- **Dane runtime:** `var/database/app.sqlite`, `var/logs/` — poza Git, przetrwają restart kontenera (bind mount katalogu repo). Mailpit bez wolumenu (poczta znika po restarcie).
- **Języki:** rozmowa/dokumentacja projektowa po polsku; nazwy w kodzie po angielsku; komunikaty UI obecnie po polsku bezpośrednio w JS/HTML (brak warstwy i18n).
- **Zależności infrastrukturalne:** Docker + Compose, zewnętrzna sieć Docker `ai` z kontenerem `mcp-search`, SMTP (prod) lub Mailpit (dev), dostęp HTTPS do API dostawców AI.

## Aktualizacja

### MCP Search w czacie

- `ChatService` deleguje odpowiedź do `ChatReplyGeneratorInterface`. Implementacja
  `ChatReplyGenerator` wykonuje zatwierdzone wyszukanie, przekazuje niezaufane wyniki
  do wybranego klienta AI i dopisuje URL źródeł do szyfrowanego tekstu odpowiedzi.
- `McpSearchClient` obsługuje Streamable HTTP (JSON/SSE), inicjalizację, negocjację
  protokołu, sesję, `tools/list`, `tools/call` i zamknięcie sesji; limit odpowiedzi 1 MiB,
  timeout wywołania/odczytu 45 s. Nie otrzymuje kluczy dostawców AI.
- Domyślny tryb WWW to ASK. Zgoda dotyczy jednego jawnego zapytania i nie przechodzi
  na kolejną wiadomość. Wyszukiwanie jest oddzielne od wiadomości; checkboxy zbierają źródła. Fetch wymaga jawnej zgody: „Kontynuuj z wybranymi stronami” zatwierdza pobranie zaznaczonej listy bez dodatkowych pytań; ręczne akcje zachowują Allow/Deny.
- `MCP_SEARCH_URL`: aplikacja/Compose/FPM domyślnie używają `http://mcp-search:8000/mcp`.
  PHP uruchomione na hoście wymaga jawnego `http://127.0.0.1:8890/mcp`.
  Samodzielny skrypt diagnostyczny nadal domyślnie używa adresu hosta.
  Wymagana wspólna sieć Docker `ai`; działające kontenery trzeba do niej podłączyć.
- Chroniony `GET /api/search/status` sprawdza tylko inicjalizację i narzędzie, bez WWW.
  `search.status: unavailable` zawiera bezpieczne kody `reason`/`stage`; te same kody
  są logowane bez zapytań, historii, sesji i surowych wyjątków.
- `bin/check-mcp-search.php` sprawdza protokół bez wczytywania konfiguracji,
  sekretów i bazy. Wyszukanie wymaga jawnego `--query` i `--approve-search`.
- Szczegóły: `docs/MCP-USAGE.md`. Testy: `McpSearchClientTest`, `ChatSearchTest`,
  `ProviderRoutingTest` i `tests/ui/chat-search.test.mjs`.

Aktualizuj `REPO.md` w tym samym commicie, gdy zmienia się architektura, uruchamianie, trwały mechanizm, limit, struktura katalogów albo znana pułapka. Nie wpisuj statusu zadania, doraźnych obejść ani sekretów.

### Badanie wybranych źródeł

- `ResearchService` zarządza wyborem i jednorazowymi decyzjami, a `ResearchReplyGenerator` przekazuje wyłącznie treść pobranych, zaznaczonych źródeł. Kontroler tylko mapuje wejście.
- `ResearchRepositoryInterface` i szyfrowana tabela `research_workspaces` przechowują wyniki, koszyk oraz decyzje per użytkownik/rozmowa; scope 0 jest szkicem przenoszonym przy utworzeniu czatu.
- `PublicWebPageFetcher` sprawdza publiczny IPv4 i przypina połączenie do niego; obsługuje HTML/tekst bez przekierowań, cookies i JS. Limit 1 MiB / 16 tys. znaków na stronę; 20 źródeł / 100 tys. znaków kontekstu.
- `WebToolClientInterface` daje natywne prośby AI o search/fetch; wywołanie tworzy oczekującą decyzję, nigdy zgodę. Modele generujące obrazy Gemini nie otrzymują tools.
- `chat-research.js` zbiera źródła między wyszukiwaniami; checkbox nie oznacza zgody na fetch. Szczegóły API i testów w `docs/MCP-USAGE.md`.

### Licencja i walidacja Composer

Projekt jest na licencji MIT (LICENSE). Przed commitem używaj `composer validate --strict --no-check-publish`, zgodnie z GitHub Actions; walidacja bez --strict przepuszcza ostrzeżenia, które blokują CI.

### Ochrona przed zgadywaniem haseł

- `LoginAttemptLimiterInterface` jest wymaganą zależnością `AuthService`; implementacja używa `LoginAttemptRepositoryInterface` i wspólnej tabeli `login_attempts` w SQLite.
- Limit 5 nieudanych prób w ruchomym oknie 900 s, osobno dla e-maila i IP. Rezerwacja w transakcji `BEGIN IMMEDIATE` poprzedza sprawdzenie hasła; udana próba zwalnia tylko własny wpis. Zablokowane próby nie przedłużają okna. HTTP 429 zawiera `Retry-After` i `Cache-Control: no-store`.
- Identyfikatory to HMAC z klucza indeksowania danych użytkownika i osobnymi prefiksami; nie zapisujemy jawnych adresów ani haseł. E-mail limitera jest przycinany i sprowadzany do małych liter; nie zmienia to reguł wyszukiwania kont. IP jest normalizowany binarnie (w tym IPv6).
- Kontroler przekazuje wyłącznie `REMOTE_ADDR`. Przy reverse proxy operator musi zapewnić prawidłowy peer po stronie serwera WWW; nagłówki klienta nie obchodzą limitu. Brak poprawnego IP trafia do wspólnego koszyka.
- Przeterminowane wpisy są usuwane przy rezerwacji kolejnej próby; bez kolejnych logowań mogą pozostać w bazie. Rejestracja wymaga 10 znaków Unicode; starsze hasła nadal pozwalają się logować.

### Obowiązkowe potwierdzanie e-maila

- `AuthService` sprawdza `email_confirmed_at` po poprawnej weryfikacji hasła i przy walidacji istniejącej sesji. Wymóg dotyczy wszystkich kont, także wcześniej zarejestrowanych; nie ma automatycznej migracji potwierdzeń.
- `EmailConfirmationRequiredException` jest mapowany na HTTP 403, kod `email_confirmation_required` i no-store. Nie powstaje nowa sesja ani cookie; błędne dane logowania nadal zwracają 401. Wylogowanie działa również dla starej sesji niepotwierdzonego konta.
- Istniejący endpoint `/api/auth/verify-email` zatwierdza konto i unieważnia link przez usunięcie jego hasha. Błędny albo wykorzystany link zwraca 422.
- Awaria SMTP nie usuwa utworzonego konta i nie omija wymogu potwierdzenia. Nie ma endpointu ponownej wysyłki; obsługa niedostarczonego lub utraconego linku wymaga osobnego zadania. Testy używają wyłącznie poczty atrapowej.

### Panel testowy wyłącznie w development

- `src/routes.php` rejestruje `/test-api` wyłącznie, gdy `settings.environment` jest dokładnie `dev`; domyślnie `prod` (404).
- `APP_ENV` nadpisuje jawne ustawienie `config/app.php`; Compose i PHP-FPM przekazują tę nazwę środowiska. Zmiany wymagają odtworzenia kontenerów przez operatora.
- Szablon panelu jest w `resources/views/test-api.html`, poza document root; dawny `/ui/index.html` nie stanowi obejścia warunku trasy. Nie przenosić szablonu z powrotem do `public`.

### Lokalna poczta developerska

- Opcjonalny profil `dev` udostępnia Mailpit `v1.31.6`; panel HTTP na `127.0.0.1:8025`, SMTP hosta na `127.0.0.1:1025`. Bez wolumenu danych i bez skonfigurowanego przekazywania na zewnątrz; limit 100 wiadomości.
- `docker-compose.dev.yml` łączyć z bazowym Compose i `--profile dev`. Nadpisuje w app tylko `APP_ENV=dev` i `MAIL_DSN=smtp://mailpit:1025`; zależność od mailpit zapewnia start skrzynki przed app. Sam profil bez pliku developerskiego nie przełącza poczty aplikacji.
- Potwierdzanie kont testowych odbywa się przez link z wiadomości w panelu Mailpita do `app_url` (lokalnie localhost:8181). Nie ma obejścia potwierdzania ani zmian rzeczywistych kont. Po przełączeniu transportu odtworzyć app; szczegóły w `docs/KONFIGURACJA.md`.

### Start dev/prod

- `dev-start.sh [dev|prod] [--dry-run]` wybiera bazowy Compose i jawny plik dev albo prod; wszystkie wywołania Docker przez sudo. Bez argumentu tylko APP_ENV=dev procesu wybiera dev, inaczej prod. Skrypt nie analizuje plików środowiska.
- `docker-compose.prod.yml` wymusza APP_ENV=prod w app. Dev używa lokalnego Mailpita, prod bazowego transportu i zatrzymuje mailpit po udanym starcie.
- Start app poprzedza start Nginxa bez odtwarzania; reload Nginxa odświeża upstream po zmianie adresu app. Błąd startu app przerywa skrypt przed operacjami na Nginxie. Dry-run tylko pokazuje komendy.

### Jawna kontynuacja badania

- Nowe wyniki wyszukiwania są wybierane w ResearchService do limitu 20 źródeł, bez automatycznego fetch. Ostatnich 80 ręcznych wykluczeń przechowuje szyfrowany workspace; ponowne zaznaczenie usuwa wykluczenie.
- ResearchPanel pokazuje „Kontynuuj z wybranymi stronami”. Kliknięcie zapisuje listę zatwierdzonych URL, przygotowuje i zatwierdza fetch dla tej listy, następnie wysyła polecenie w trybie badania. Nie wymaga kolejnego potwierdzania fetch; nie obejmuje nowych ani odznaczonych adresów.
- Własny draft zostaje zachowany, pusty otrzymuje polecenie kontynuacji lub opracowania. Zmiana rozmowy i niepewna odpowiedź API zatrzymują operację. Błąd pojedynczej strony pozwala użyć pozostałych treści. Blokady sesji, modelu i powtórnego wysłania pozostają aktywne.

- Stan `researchContinuation` blokuje edycję/wysyłkę i wybór modelu przez cały ciąg fetch → żądanie AI. ResearchPanel pokazuje odpowiedni etap na przycisku i zachowuje disabled/aria-busy po renderowaniu. Blokada kończy się po zakończeniu operacji (również błędem); nie oznacza potwierdzenia odpowiedzi dostawcy.

### Modal wyników i magazyn źródeł

- `chat-research-modal.js` zarządza natywnym dialogiem umieszczonym poza `#app`, przywołaniem z bocznej listy i przywracaniem fokusu. `chat-research.js` renderuje wyniki i aktualizuje pojedynczy przycisk w stopce dialogu; źródła nie zajmują wysokości formularza czatu.
- Zamknięcie modalu nie czyści magazynu. Wyniki ostatniego wyszukania, wybrane źródła i pobrane teksty są w szyfrowanym `research_workspaces`, nie w jawnym localStorage. Powrót do rozmowy po reload odtwarza jej magazyn; niewybrane wyniki kolejnych wyszukiwań nie stanowią historii wszystkich wyszukań.
- Kliknięcie „Kontynuuj” zamyka dialog na czas fetch i AI. Kontekst backendu zawiera tytuły, adresy, statusy, teksty i informację o skróceniu; teksty już pobrane są używane ponownie. Obowiązują dotychczasowe limity 20 źródeł / 100 tys. znaków.


### Audyt: autoryzacja, katalog i start bazy

- `AuthMiddleware` z `SessionAuthenticatorInterface` waliduje sesję raz dla grupy prywatnych tras i ustawia `userId`. `SessionTokenResolver` zachowuje pierwszeństwo Bearer przed cookie; wylogowanie unieważnia obie przedstawione sesje. Katalog modeli/połączeń pozostaje publiczny.
- `ModelCatalogInterface` / `ModelCatalogService` przechowują prezentację i możliwości modeli, niezależnie od zapisu rozmów. `ModelSelector` rozdziela dostawcę i identyfikator, zachowując obsługę dawnych modeli OVH.
- `bin/migrate.php` uruchamia schemat i inicjalizację ochrony wyłącznie pustej bazy. Compose uruchamia go przez `docker/start-app.sh` przed FPM; błąd blokuje start. Istniejące jawne dane nadal wymagają oddzielnej, świadomej procedury ochrony danych. HTTP tylko sprawdza gotowość, bez migracji. Uruchomienia poza Compose wymagają wcześniejszego lokalnego wykonania migratora przez operatora.
- Puste wyniki MCP wymagają jawnego `status: empty`, `source: null`, `results: []` i braku błędów. Nieznane wersje protokołu są odrzucane; lista i testy są w `McpSearchClient::SUPPORTED_PROTOCOLS` i `McpSearchClientTest`.
