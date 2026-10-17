# WirralAI - Backend API with Slim 4, PHP 8.4, and SQLite

WirralAI to backend API zbudowany na Slim 4 z użyciem PHP 8.4 i SQLite. Zawiera integrację z OVH AI Endpoints, system logowania, zarządzania zadaniami oraz czaty AI.

## Funkcjonalności

- **Logowanie i rejestracja** — z obsługą "zapamiętaj mnie" (cookie + localStorage)
- **Zarządzanie zadaniami (CRUD)** — z batch insertem i transakcjami
- **Czaty z AI** — pełna obsługa historii rozmów z OVH AI
- **Integracja z OVH AI Endpoints** — OpenAI-compatible API
- **Logger strukturalny** — DualLogger (plik + stdout), logowanie błędów, middleware
- **Wybór modelu AI** — model konfigurowalny przez `.env` (planowana rozbudowa)

## Architektura

Aplikacja stosuje czystą architekturę z oddzieleniem warstw:
- **Controller** — tylko obsługuje żądania HTTP
- **Mapper** — mapuje dane wejściowe do DTO z walidacją
- **Service** — logika aplikacji
- **Repository** — dostęp do bazy danych (SQLite przez PDO)
- **DTO** — immutable obiekty przenoszące dane
- **Infrastructure** — klienci zewnętrzni (AI, DB, mail)

## Wymagania

- PHP 8.4
- Docker i Docker Compose
- SQLite
- Composer

## Konfiguracja

```bash
cp .env.example .env
```

Ustaw w `.env`:
- `OVH_AI_ENDPOINTS_ACCESS_TOKEN` — token z https://kepler.ai.cloud.ovh.net/
- `OVH_AI_MODEL` — model AI (domyślnie `Qwen3-Coder-30B-A3B-Instruct`)
- `LOG_LEVEL` — poziom logowania (`debug`, `info`, `warning`, `error`)

## Uruchamianie

```bash
docker compose up --build   # budowanie i uruchomienie
docker compose up -d        # w tle
```

Aplikacja dostępna na `http://localhost:8181`.

## Panel Web UI

SPA dostępne pod `/` — landing page z kafelkami do sekcji:
- **Zadania** — CRUD
- **Czat AI** — rozmowy z modelem
- Ustawienia wysyłania: Enter / Shift+Enter
- Automatyczne scrollowanie, animacja ładowania
- Token sesji w localStorage + cookie

## Routing

| Ścieżka | Opis |
|---------|------|
| `/` | Landing page SPA |
| `/test-api` | Panel testowy wyłącznie przy `APP_ENV=dev` |
| `/api/auth/*` | Autoryzacja |
| `/api/tasks*` | Zadania |
| `/api/chats*` | Czaty |

## Endpointy API

### Autoryzacja
| Metoda | Ścieżka | Opis |
|--------|---------|------|
| POST | `/api/auth/register` | Rejestracja |
| POST | `/api/auth/login` | Logowanie (zwraca token) |
| GET | `/api/auth/me` | Pobierz zalogowanego użytkownika |

### Zadania
| Metoda | Ścieżka | Opis |
|--------|---------|------|
| GET | `/api/tasks` | Lista zadań |
| POST | `/api/tasks` | Tworzenie zadania |
| PUT | `/api/tasks/{id}` | Aktualizacja zadania |
| DELETE | `/api/tasks/{id}` | Usunięcie zadania |

### Czaty
| Metoda | Ścieżka | Opis |
|--------|---------|------|
| GET | `/api/chats` | Lista czatów |
| POST | `/api/chats` | Tworzenie czatu |
| GET | `/api/chats/{id}` | Pobranie czatu z wiadomościami |
| POST | `/api/chats/{id}/messages` | Dodanie wiadomości (wywołuje AI) |

## Struktura katalogów

```
├── public/
│   ├── index.php              # Entry point Slim + migracje + error handling
│   ├── ui/landing.html        # Strona startowa i formularze konta
│   └── error-pages/503.html   # Strona błędu retro terminal
├── src/
│   ├── Controller/            # AuthController, ChatController, TaskController
│   ├── Mapper/Auth/           # LoginInputMapper
│   │   Mapper/Chat/           # CreateChatInputMapper, AddChatMessageInputMapper
│   │   Mapper/Task/           # CreateTaskInputMapper, UpdateTaskInputMapper
│   ├── Dto/Auth/              # LoginInput, LoginResult
│   │   Dto/Chat/              # CreateChatInput, AddChatMessageInput, ChatResult
│   │   Dto/Task/              # CreateTaskInput, UpdateTaskInput, TaskResult
│   ├── Service/               # AuthService, ChatService, TaskService
│   ├── Repository/            # UserRepository, ChatRepository, TaskRepository
│   ├── Infrastructure/
│   │   ├── AI/OvhAiClient.php # Klient OVH AI (Guzzle, OpenAI-compatible)
│   │   ├── Database/          # ConnectionFactory, MigrationRunner
│   │   ├── Http/JsonResponder.php
│   │   └── Mail/MailService.php
│   └── Exception/             # ValidationException, NotFoundException, UnauthorizedException
├── var/
│   ├── database/app.sqlite    # Baza SQLite
│   └── logs/                  # Logi aplikacji
├── docker/nginx/default.conf  # Konfiguracja nginx
├── docker-compose.yml
├── .env.example
└── .local/todo.md             # Lista zadań
```

## Baza danych

SQLite z automatycznymi migracjami przy starcie (`public/index.php`).

Tabele:
- `users` — id, email, password_hash, created_at
- `sessions` — id, user_id, token, created_at, expires_at
- `chats` — id, user_id, title, created_at
- `chat_messages` — id, chat_id, role (user/assistant), content, created_at
- `tasks` — id, user_id, title, description, status, created_at, updated_at

W przypadku błędu migracji — HTTP 503 (strona retro terminala).

## Integracja z OVH AI

Klasa `OvhAiClient` (Guzzle) wywołuje `POST {base_uri}/chat/completions` z:
- Bearer tokenem
- nazwą modelu
- historią konwersacji

Endpoint: `https://oai.endpoints.kepler.ai.cloud.ovh.net/v1/`

Aktualny model: `Qwen3-Coder-30B-A3B-Instruct` (planowany wybór modelu per czat).

## Logger

DualLogger (`mafio69/fast-php-logger`) — zapis do pliku + stdout.
Poziomy: `debug`, `info`, `warning`, `error`, `critical`.
Logowane: błędy AI, logowania, CRUD, wyjątki middleware (422/401/404/500).

## Optymalizacje wydajności

- Batch insert dla wiadomości czatu (`ChatRepository::addMessagesBatch`)
- Batch insert dla zadań (`TaskRepository::createBatch`)
- Transakcje PDO dla operacji grupowych
- Redukcja zapisów do bazy w jednym flow czatu

## Testy

```bash
composer test
```

## Track zadań

Szczegółowa lista zadań: `.local/todo.md`
