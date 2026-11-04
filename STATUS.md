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
- **T3.2 (2026-11-03):** pomiar zysku kompaktu — identyczna rozmowa 12 krótkich pytań, ten sam model w obu przebiegach: ostatnia odpowiedź przed T3.1 **1465/55**, po T3.1 **1331/61** → **−134 tokenów wejściowych (−9,2%)** już przy krótkiej rozmowie; przy dłuższych odpowiedziach i 30+ turach zysk rośnie, bo limit 8 pełnych tur utrzymuje stały rozmiar historii. #62-520 zamknięte na DT.
- **Gemini thinking (2026-11-04):** `MAX_TOKENS` nie zabija już całej odpowiedzi — częściowy tekst + usage wracają do użytkownika; limit tekstowy 8192 tokenów; obrazy pozostają rygorystyczne.

## Otwarte

- **Rotacja tokenu MCP — WSTRZYMANA:** token po stronie serwera (`szukaj`) podmieniony przez właściciela, ale serwer `mcp-search` jeszcze nie działa; po jego postawieniu — `sudo docker compose up -d --force-recreate app` i weryfikacja `GET /api/search/status` / jednego wyszukania w czacie.
- **`szukaj` w trybie dev** (backend i wbudowany frontend `appEnv:"dev"`); przełączenie na prod wymaga `APP_ENV=prod` w `szukaj/.env` i przebudowy `mcp-search`.
- **Limit logowań za Traefikiem:** `TRUSTED_PROXIES` puste → wspólny licznik nieudanych logowań dla wszystkich klientów.
- **`develop` → `master`:** develop jest przed masterem (T6.1+T6.2 czekają na PR); scalanie wyłącznie przez PR z zielonymi kontrolami.
- **Brak wpisów CHANGELOG** dla T1.0 (Langfuse) i #14.
- Do potwierdzenia na rzeczywistym modelu: #62-495 (kontynuacja badania), OpenRouter (2026-11-04: błąd odpowiedzi przy modelu typu „4o" — wg właściciela po stronie dostawcy, do obserwacji), start `./prod-start.sh` i poczta prod; manualny test filtrów wyszukiwania w UI po deployu.

## Następny priorytet

1. #62-486 (E2.2) — realistyczny User-Agent i Accept-Language w fetcherze (wysoki priorytet z listy jakości źródeł).
2. `TRUSTED_PROXIES` dla Traefika.
3. #62-494 — strumieniowanie odpowiedzi (SSE).
4. Test manualny Gemini 3.8 Flash po naprawie `MAX_TOKENS` (właściciel, po deployu).
