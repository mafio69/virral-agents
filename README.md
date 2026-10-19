# WirralAI

Prywatny czat AI z wieloma dostawcami, zatwierdzanym dostępem do WWW i szyfrowaną historią.
Backend: Slim 4, PHP 8.4, SQLite; frontend: statyczne moduły ES bez bundlera. Licencja MIT.

## Funkcjonalności

- **Konto** — rejestracja z obowiązkowym potwierdzeniem e-maila, logowanie z limitem prób, sesja w cookie HttpOnly (lub Bearer), wylogowanie unieważniające sesję.
- **Czat AI** — OVH AI Endpoints, Z.ai (GLM), OpenAI, Anthropic i Google Gemini; wybór połączenia i modelu przy nowej rozmowie, analiza obrazów wejściowych, generowanie obrazów Gemini, Markdown oczyszczany przez DOMPurify.
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
./dev-start.sh dev            # Compose + Mailpit, APP_ENV=dev
./dev-start.sh prod           # APP_ENV=prod, bez Mailpita
./dev-start.sh dev --dry-run  # tylko podgląd komend
```

- Aplikacja: `http://localhost:8181` (start), `http://localhost:8181/chat` (czat).
- Mailpit (dev): `http://localhost:8025` — tu odklikuje się link potwierdzający konto testowe.
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
