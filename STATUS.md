# STATUS

## Jak prowadzić

- Stan **na teraz**: plik jest nadpisywany, nie dopisywany. Historia każdej zmiany trafia do `CHANGELOG.md`.
- Sekcje: Aktualnie, Gotowe, Otwarte/niepotwierdzone, Następny priorytet. Krótko, bez opisów przebiegu testów.
- Wyniki testów podawaj tylko ostatnie, z datą.
- Kolejka zadań: DevBrain-Todo, lista „virralAI” (id 62); zadania podawaj jako `#62-<id zadania>` (480–510).

---

**Ostatnia aktualizacja:** 2026-11-02
**Gałąź:** `develop` (pushnięty; zawiera T6.1+T6.2). PR #15 scalony do `master` (`c42ab6b`); `v1.0.0` otagowane na `32ae5a5`.
**Ostatnia pełna walidacja (2026-11-02):** formatter 0/181, PHPUnit 282/282 (1326 asercji), UI 107/107, E2E 5/5 (Mailpit loopback `127.0.0.1:8025`).

## Gotowe

- **v1.0.0:** konto (potwierdzenie e-maila, limit logowań, sesja HttpOnly/Bearer), szyfrowanie pól, czat z 6 dostawcami, obsługa błędów `DualLogger`, wyszukiwanie ASK i badanie źródeł z kontynuacją.
- **Po v1.0.0 (w `master`):** T1.0 Langfuse (OTLP, tokeny z dostawców), T1.1 zapis zużycia `chat_usage`, T1.2 licznik „wej. / wyj.” pod odpowiedzią, #14 `LANGFUSE_HOST` i `LANGFUSE_ENABLED`, PR #15 (nginx `agents.dom`, dokumentacja).
- **T6.1 + T6.2 (2026-11-02, w `develop`):** filtry wyszukiwania zaawansowanego — backend `SearchQuery`/`SearchQueryBuilder`, kontrakt `web_search` dla modeli (`must_have`/`exclude`/`exact_phrase`/`sites`), chipy „Bez / Musi zawierać" + „Więcej opcji" w UI, podsumowanie filtrów na karcie zgody. #62-524 i #62-525 zamknięte na DT.
- **T3.1 (2026-11-02):** `HistoryCompactor` — ostatnie 8 tur w całości (`context_recent_turns` w config/DI), starsze tylko przycięte pytania użytkownika. #62-519 zamknięte na DT.

## Otwarte

- **Token MCP do rotacji:** wartość `MCP_AUTH_TOKEN` trafiła 2026-11-02 do rozmowy z agentem — wygenerować nowy i wpisać w `szukaj/.env` oraz `virral-agents/.env`.
- **`szukaj` w trybie dev** (backend i wbudowany frontend `appEnv:"dev"`); przełączenie na prod wymaga `APP_ENV=prod` w `szukaj/.env` i przebudowy `mcp-search`.
- **Limit logowań za Traefikiem:** `TRUSTED_PROXIES` puste → wspólny licznik nieudanych logowań dla wszystkich klientów.
- **`develop` → `master`:** develop jest przed masterem (T6.1+T6.2 czekają na PR); scalanie wyłącznie przez PR z zielonymi kontrolami.
- **Brak wpisów CHANGELOG** dla T1.0 (Langfuse) i #14.
- Do potwierdzenia na rzeczywistym modelu: #62-495 (kontynuacja badania), OpenRouter, start `./prod-start.sh` i poczta prod; manualny test filtrów wyszukiwania w UI po deployu.

## Następny priorytet

1. T3.2 (#62-520) — pomiar zysku kompaktu: ta sama rozmowa przed/po T3.1, `prompt_tokens` z `chat_usage`, liczby do STATUS (współpraca z właścicielem).
2. Rotacja tokenu MCP.
3. `TRUSTED_PROXIES` dla Traefika.
4. #62-494 — strumieniowanie odpowiedzi (SSE).
