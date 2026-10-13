# Audyt: rozwój WirralAI jako agenta + integracja wielu modeli

**Data:** 2026-09-30
**Zakres:** odczyt kodu na `master` (bez uruchamiania testów, bez odczytu `src/Infrastructure/Secrets/*` — AGENTS.md §2).

## Werdykt

Architektura (Controller → Mapper → DTO → Service → Repository, DI) nadaje się pod agenta,
a moduł `tasks` jest naturalnym pierwszym zestawem narzędzi. **Warunek wstępny:** domknięcie
fundamentu multi-provider, który na `master` jest wdrożony połowicznie.

## 1. Stan faktyczny vs `STATUS.md`

`STATUS.md` oznacza etapy 4–9 jako zrobione, ale na `master`:

| # | Problem | Gdzie | Skutek |
|---|---|---|---|
| 1 | Brak migracji `ai_urls`, `ai_providers`, `ai_models`, `chats.model_id` | `src/Infrastructure/Database/MigrationRunner.php` | Repozytoria AI rzucą „no such table” na prawdziwej bazie |
| 2 | `openai-php/client` jest w `vendor/`, ale nie w `composer.json`/`composer.lock` | `composer.json`, `OpenAiCompatClient.php` | Czysty `composer install` w Dockerze → brak klasy `OpenAI` |
| 3 | `AiClientFactory` nie zarejestrowana w DI; `ChatService` zależy od konkretnego `OvhAiClient` | `src/dependencies.php`, `src/Service/ChatService.php` | Kod multi-provider jest martwy |
| 4 | Testy zakładają dwa różne schematy `chats` (`model_legacy`/`model_id` vs `model`) | `tests/ChatServiceTestCase.php` vs `tests/ChatServiceModelsTest.php` | Rozjazd testów z produkcją |
| 5 | `/api/ai-models` pobiera modele z OVH na żywo; fallback na sztywno w kontrolerze | `src/Controller/ChatController.php` | Logika w kontrolerze |
| 6 | `ChatService` sam tworzy klienta Guzzle | `src/Service/ChatService.php` | Infrastruktura w serwisie |
| 7 | Test woła prawdziwe API OVH (skip przy braku sieci) | `tests/ChatServiceModelsTest.php` | Test zależny od sieci, fałszywie zielony |
| 8 | Brak `docs/plan-multi-ai-providers.md`; branch `feature/multi-ai-providers` istnieje tylko lokalnie (nie na `origin`) | `STATUS.md` | Część pracy prawdopodobnie nie trafiła na `master` |

## 2. Blokery dla agenta

- `AiClientInterface::generate(array, ?string): string` zwraca sam tekst — brak `tool_calls`,
  `finish_reason`, `usage`, wiadomości z rolą `tool`.
- `chat_messages` ma tylko `role` + `content` — brak miejsca na wywołania i wyniki narzędzi.
- Wszystko synchronicznie w PHP-FPM (timeout 120 s) — wieloetapowa pętla agenta zablokuje workery.
- Walidacja tokenu powielona w każdym kontrolerze — do przeniesienia do middleware.

## 3. Docelowa architektura agenta

```
Infrastructure/AI/
  AiClientInterface::chat(ChatRequest): ChatResponse    # messages, tools, usage, finishReason
  Adapter/OpenAiCompatAdapter                            # OVH, Z.AI, OpenRouter, Mistral, Groq, Ollama/vLLM
  Adapter/AnthropicAdapter, GeminiAdapter (opcjonalnie)  # API inne niż OpenAI
Agent/
  Tool/ToolInterface   name(), description(), parametersSchema(), execute(args, AgentContext)
  Tool/ToolRegistry
  Tool/Task/*          # opakowują TaskService
  AgentRunner          # limit kroków, budżet, timeout, zapis każdego kroku
Repository/AgentRunRepository   # agent_runs, agent_steps (audyt)
```

Zasady bezpieczeństwa:
- `userId` wyłącznie z `AgentContext` (sesja), nigdy z argumentów modelu.
- Narzędzia zapisujące/usuwające wymagają potwierdzenia człowieka.
- Wyniki narzędzi traktowane jako niezaufane dane (prompt injection).
- Sekrety nigdy w kontekście modelu ani w logach.

## 4. Integracja wielu modeli

- Każdy dostawca OpenAI-compatible = nowy rekord w bazie, bez nowego kodu.
- `ai_providers.api_type` (`openai_compat` / `anthropic` / …) → fabryka wybiera adapter.
- `ai_models`: `supports_tools`, `supports_vision`, `context_length`, `max_output` —
  obsługę function calling weryfikować per model; agent wybiera tylko modele z narzędziami.
- Routing: model per rola (planner mocny, executor tani) + łańcuch fallback przy 429/5xx.
- Koszty: `price_in`/`price_out` już są — wystarczy zapisywać `usage` i liczyć koszt uruchomienia.

## 5. Plan etapów (anty-kula-śnieżna)

Reguły: każdy etap = osobny branch + PR, zielony `composer test`; kolejny etap dopiero po
scaleniu poprzedniego; `[x]` w STATUS tylko po weryfikowalnym teście.

| Etap | Zakres | Poza zakresem | Definition of Done |
|---|---|---|---|
| 0 | Ustalić zawartość `feature/multi-ai-providers` vs `master`; urealnić STATUS | kod | STATUS zgodny z kodem |
| 1 | Migracje AI, `composer require openai-php/client`, DI, `ChatService` na `AiClientInterface`, testy bez sieci | UI, agent | `docker compose up` na pustej bazie + testy zielone |
| 2 | `/api/ai-models` z bazy, wybór modelu w UI, usunięcie fallbacku z kontrolera, auth middleware | narzędzia | testy endpointów |
| 3 | `ChatRequest`/`ChatResponse` z `usage`, `finishReason`; zapis kosztu | narzędzia | test kontraktu |
| 4 | `api_type` + kolumny możliwości modelu | nowe adaptery | migracja + test |
| 5 | `ToolInterface`, `ToolRegistry`, jedno narzędzie tylko do odczytu `list_tasks` | zapis | test jednostkowy |
| 6 | Synchroniczny `AgentRunner` (maks. 3 kroki, tylko odczyt), `agent_runs`/`agent_steps` | worker w tle | test pętli na atrapie klienta |
| 7 | Narzędzia zapisujące z potwierdzeniem człowieka | — | testy własności danych |
| 8 | Worker w tle jako usługa w `docker-compose.yml` | — | agent nie blokuje FPM |
| 9 | (opcjonalnie) adapter Anthropic/Gemini, fallback | — | testy adapterów |

## Załącznik: środowisko lokalne (2026-09-30)

Katalog projektu ma ACL z `other::---`. Worker nginx (uid 101, na hoście `messagebus`) nie miał
dostępu → `stat() ... (13: Permission denied)` dla `/favicon.ico`. Dodano ACL:
`u:101:x` na katalogu głównym, `u:101:rX` + domyślny ACL na `public/`. Rozwiązanie działa tylko
lokalnie — na VPS docelowo obraz z `public/` w środku zamiast bind mounta.
