# Virral Agents

Prywatny czat AI z wieloma dostawcami, zatwierdzanym dostępem do WWW i szyfrowaną historią.
Backend: Slim 4, PHP 8.4, SQLite; frontend: statyczne moduły ES bez bundlera. Licencja MIT.

## Po co to repozytorium

Jedno miejsce do rozmowy z modelami różnych dostawców (OVH, Z.ai, OpenAI, Anthropic,
Gemini, OpenRouter) na własnym serwerze, z własnymi kluczami API. Model nie dostaje
swobodnego dostępu do internetu: może tylko **zaproponować** wyszukanie lub pobranie
strony, a wykonuje je dopiero zgoda użytkownika (tryb ASK). Historia rozmów, obrazy
i e-maile są szyfrowane w bazie. Aplikacja jest przeznaczona dla jednej osoby lub małego
zespołu, uruchamiana przez Docker Compose.

## Integracje

Każda integracja włącza się kluczem w środowisku (plik `.env` właściciela). Połączenie bez
klucza nie pojawia się na liście w czacie (wyjątek: Z.ai, który może korzystać też z rejestru
dostawców w bazie). Rozmowy założone wcześniej nadal się otwierają; nowa odpowiedź w takiej
rozmowie kończy się czytelnym błędem braku konfiguracji.

| Integracja | Zmienna środowiska | Do czego | Szczegóły |
|---|---|---|---|
| OVH AI Endpoints | `OVH_AI_ENDPOINTS_ACCESS_TOKEN` | czat (modele open source) | `docs/ovhcloud-ai-endpoints.md`, `docs/MODELE_OVH.md` |
| Z.ai (GLM) | `ZAI_API_KEY` | czat | `docs/MODELE_ZAI.md` |
| OpenAI | `OPEN_AI_KEY` | czat (Responses API), obrazy wejściowe | `docs/AI_PROVIDERS.md` |
| Anthropic (Claude) | `ANTHROPIC_API_KEY` | czat (Messages API), obrazy wejściowe | `docs/AI_PROVIDERS.md` |
| Google Gemini | `GEMINI_KEY` | czat, obrazy wejściowe, generowanie obrazów | `docs/AI_PROVIDERS.md` |
| OpenRouter | `OPENROUTER_API_KEY`, opcjonalnie `OPENROUTER_MODELS` | setki modeli wielu firm przez jedno konto | [niżej](#podłączenie-openrouter), `docs/AI_PROVIDERS.md` |
| Wyszukiwarka MCP (SearXNG) | `MCP_SEARCH_URL` (domyślnie `http://mcp-search:8000/mcp`) | wyszukiwanie WWW i badanie źródeł | `docs/MCP-USAGE.md`, `docs/instrukcja-integracji-mcp-search.md` |
| Poczta SMTP | `MAIL_DSN` | potwierdzenie rejestracji | `docs/KONFIGURACJA.md` |

Klucze szyfrowania (`USER_DATA_KEY`, `DB_HASH`) są wymagane zawsze — `docs/OCHRONA_DANYCH.md`.

### Podłączenie nowego dostawcy (ogólnie)

1. Załóż klucz API w panelu dostawcy (najlepiej z limitem wydatków).
2. Dopisz go do `.env` pod nazwą z tabeli — samodzielnie, nie wklejaj klucza do czatu z agentem.
3. Odtwórz kontener app, żeby PHP-FPM dostał nową wartość:
   `sudo docker compose up -d --force-recreate app` (obrazu nie trzeba budować).
4. W czacie: **Nowa rozmowa** → wybierz połączenie i model → wyślij krótką wiadomość.

Jeśli kontener app nie wstaje po zmianie `.env`, najczęściej to błąd składni pliku
(np. brak cudzysłowu) — `./dev-start.sh` zgłosi to komunikatem.

### Podłączenie OpenRouter

1. Na [openrouter.ai](https://openrouter.ai) załóż konto, doładuj saldo lub ustaw limit
   i utwórz klucz w **Settings → Keys**.
2. W `.env` dopisz:

   ```dotenv
   OPENROUTER_API_KEY=...            # Twój klucz
   # opcjonalnie: tylko wybrane modele, po przecinku (bez spacji)
   OPENROUTER_MODELS=anthropic/claude-sonnet-4.5,google/gemini-2.5-flash
   ```

   Bez `OPENROUTER_MODELS` w czacie widać cały tekstowy katalog OpenRouter (kilkaset pozycji).
   Identyfikatory modeli są na stronie modelu w OpenRouter (format `firma/model`).
3. `sudo docker compose up -d --force-recreate app`
4. W czacie wybierz połączenie **OpenRouter** i model „OpenRouter — …”.

Co warto wiedzieć:

- Każde żądanie ma `data_collection: deny` — OpenRouter używa tylko dostawców, którzy nie
  zbierają danych. Jeśli dla danego modelu takiego nie ma (zdarza się przy `:free`),
  zobaczysz błąd połączenia — wybierz inny model.
- Brak środków lub limit zapytań (402/429) → komunikat o limicie dostawcy.
- Wyszukiwanie WWW przez narzędzia działa tylko w modelach, które w OpenRouter obsługują `tools`.

## Agenci AI pracujący nad kodem

Zasady dla agentów (Claude Code, OpenCode i inni) są w `AGENTS.md` — w tym twardy zakaz
czytania `.env` i innych sekretów. Kolejka zadań jest w DevBrain-Todo (lista „virralAI”,
instrukcja `docs/DT-todo-instrukcja.md`, zdalny MCP `docs/agent-todo-remote-mcp.md`).
Przebieg pracy: gałąź z `develop` według `GIT-FLOW.md`, `composer check-all` przed commitem,
wpis w `CHANGELOG.md`, stan w `STATUS.md`, przekazanie w `HANDOFF.md`.

## Funkcjonalności

- **Konto** — rejestracja z obowiązkowym potwierdzeniem e-maila, logowanie z limitem prób, sesja w cookie HttpOnly (lub Bearer), wylogowanie unieważniające sesję.
- **Czat AI** — OVH AI Endpoints, Z.ai (GLM), OpenAI, Anthropic, Google Gemini i OpenRouter; wybór połączenia i modelu przy nowej rozmowie, analiza obrazów wejściowych, generowanie obrazów Gemini, Markdown oczyszczany przez DOMPurify.
- **Wyszukiwanie WWW (ASK)** — zapytanie do serwera MCP (`web_search`, SearXNG) wyłącznie po jawnej zgodzie dla konkretnego zapytania.
- **Badanie źródeł** — wybór wyników checkboxami, szyfrowany koszyk źródeł per rozmowa, pobranie zaznaczonych stron i opracowanie ich treści przez model.
- **Ochrona danych** — szyfrowanie treści wiadomości, tytułów, obrazów, e-maili i zadań (XChaCha20-Poly1305), hashe tokenów sesji.
- **Zadania** — prosty CRUD.

## Wymagania

- Docker i Docker Compose (Docker wywoływany przez sudo w `dev-start.sh`)
- PHP 8.4 z `pdo_sqlite` i `sodium`, Composer — do testów i CLI poza kontenerem
- Node.js — testy UI; opcjonalnie Playwright dla E2E
- Zewnętrzna sieć Docker `ai` z kontenerem `mcp-search` (dla wyszukiwania WWW)

## Konfiguracja

```bash
cp .env.example .env
```

Zwykłe ustawienia są w `config/app.php`. Sekrety (klucze dostawców AI, `USER_DATA_KEY`, `DB_HASH`, `MAIL_DSN`)
przekazuje się przez środowisko procesu — Compose → PHP-FPM (`docker/php-fpm-env.conf`).
Pełna lista nazw i procedury: `docs/KONFIGURACJA.md`, `docs/AI_PROVIDERS.md`, `docs/OCHRONA_DANYCH.md`.

## Uruchamianie

```bash
./dev-start.sh                # development (APP_ENV=dev)
./prod-start.sh               # produkcja (APP_ENV=prod)
./dev-start.sh --dry-run      # tylko podgląd komend
```

- Aplikacja: `http://localhost:8181` (start), `http://localhost:8181/chat` (czat).
- Poczta: zawsze przez `MAIL_DSN`; w dev testowe wiadomości (link potwierdzający konto) odbiera zewnętrzny `mail-trap.dom`.
- Migracje wykonuje `bin/migrate.php` przed startem PHP-FPM (`docker/start-app.sh`); błąd blokuje start.
- `/test-api` (panel testowy) istnieje wyłącznie przy `APP_ENV=dev`.

## Endpointy API

Wszystkie pod `/api`. Trasy oznaczone 🔒 wymagają sesji.

| Metoda | Ścieżka | Opis |
|---|---|---|
| POST | `/auth/register` | Rejestracja (wysyła link potwierdzający) |
| GET | `/auth/verify-email` | Potwierdzenie e-maila z linku |
| POST | `/auth/login` | Logowanie (cookie + token); 403 bez potwierdzenia, 429 po limicie |
| POST | `/auth/logout` | Wylogowanie (204) |
| GET 🔒 | `/auth/me` | Zalogowany użytkownik |
| GET/POST 🔒 | `/tasks` | Lista / tworzenie zadań |
| PUT/DELETE 🔒 | `/tasks/{id}` | Aktualizacja / usunięcie zadania |
| GET/POST 🔒 | `/chats` | Lista / tworzenie rozmowy |
| GET 🔒 | `/chats/{id}` | Rozmowa z wiadomościami |
| POST 🔒 | `/chats/{id}/messages` | Wiadomość i odpowiedź AI (opcjonalnie wyszukanie lub tryb badania) |
| POST 🔒 | `/chats/{id}/messages/{messageId}/retry` | Ponowienie odpowiedzi na ostatnią wiadomość bez odpowiedzi |
| GET 🔒 | `/search/status` | Diagnostyka połączenia MCP (bez wyszukiwania) |
| GET 🔒 | `/research` | Stan koszyka źródeł rozmowy |
| POST 🔒 | `/research/actions` | Propozycja operacji WWW (wyszukanie/fetch) |
| POST 🔒 | `/research/decisions` | Decyzja Allow/Deny dla propozycji |
| PUT 🔒 | `/research/sources` | Wybór źródeł |
| POST 🔒 | `/research/prepare` | Zakolejkowanie decyzji fetch dla wybranych, jeszcze niepobranych źródeł |
| GET | `/ai-connections` | Dostępne połączenia AI |
| GET | `/ai-models?connection=…` | Modele połączenia |

Szczegóły kontraktu wyszukiwania i badania: `docs/MCP-USAGE.md`.

## Struktura

```
public/            index.php (Slim), ui/landing.html, ui/chat.html, ui/chat/*.js, ui/lib (Marked, DOMPurify)
resources/views/   test-api.html (tylko dev)
src/
  Controller/      AuthController, ChatController, TaskController — cienkie
  Mapper/, Dto/    walidacja wejścia i niemutowalne obiekty
  Middleware/      AuthMiddleware
  Service/         Auth, Chat, ChatReplyGenerator, Research*, ModelCatalog, LoginAttemptLimiter, Task
  Repository/      dostęp do SQLite przez ProtectedStore (szyfrowanie pól)
  Infrastructure/  AI (dostawcy), Search (MCP, fetch stron), DataProtection, Database, Mail, Http, Secrets
bin/               migrate.php, protect-user-data.php, reset-password.php, check-mcp-search.php
config/app.php     jawne ustawienia (bez sekretów)
docker/            Dockerfile, nginx, php-fpm-env.conf, start-app.sh
tests/             PHPUnit, ui/ (node:test), e2e/ (Playwright), browser/ (routery atrap)
docs/              dokumentacja szczegółowa
var/               baza SQLite i logi (poza Git)
```

Mapa techniczna, mechanizmy krytyczne i pułapki: `REPO.md`.

## Testy i jakość

```bash
composer check-all   # PHP CS Fixer (dry-run) + PHPUnit + testy UI
composer lint        # tylko formatter, bez zmian
composer format      # zastosowanie formatowania
composer validate --strict --no-check-publish
composer check-platform-reqs
npm run test:e2e     # E2E Playwright, przygotowanie w docs/E2E.md
```

Testy PHP używają SQLite w pamięci i atrap HTTP — nie wywołują rzeczywistych dostawców AI ani WWW.

## Dokumentacja projektu

| Plik | Rola |
|---|---|
| `AGENTS.md`, `GIT-FLOW.md` | zasady pracy agentów i Git |
| `REPO.md` | mapa techniczna repozytorium |
| `STATUS.md` | stan na teraz i priorytety |
| `HANDOFF.md` | przekazanie na kolejną sesję |
| `CHANGELOG.md` | historia zmian (append-only) |
| DevBrain-Todo, lista „virralAI” | kolejka zadań i droga rozwoju (zadania 01–25) |
| `docs/` | konfiguracja, dostawcy AI, MCP, ochrona danych, audyt, E2E |
