# STATUS

## Jak prowadzić

- Stan **na teraz**: plik jest nadpisywany, nie dopisywany. Historia każdej zmiany trafia do `CHANGELOG.md`.
- Sekcje: Aktualnie, Gotowe, Otwarte/niepotwierdzone, Następny priorytet. Krótko, bez opisów przebiegu testów.
- Wyniki testów podawaj tylko ostatnie, z datą.
- Kolejka zadań: DevBrain-Todo, lista „virralAI” (id 62); zadania podawaj jako `#62-<id zadania>` (480–510).

---

**Ostatnia aktualizacja:** 2026-11-01
**Gałąź:** `chore/agents-dom-status` (z `develop` = `origin/master` po fast-forward). `v1.0.0` otagowane na `32ae5a5` (PR #10).
**Ostatnia pełna walidacja (2026-11-01):** formatter 0/175, PHPUnit 261/261 (1224 asercje), UI 99/99, E2E 5/5. Test manualny na `agents.dom`: czat (Gemini) i licznik tokenów OK; wyszukiwanie WWW działa po poprawce konfiguracji (2026-11-02).

## Gotowe

- **v1.0.0:** konto (potwierdzenie e-maila, limit logowań, sesja HttpOnly/Bearer), szyfrowanie pól, czat z 6 dostawcami, obsługa błędów `DualLogger`, wyszukiwanie ASK i badanie źródeł z kontynuacją.
- **Po v1.0.0 (w `master`):** T1.0 Langfuse (OTLP, tokeny z dostawców), T1.1 zapis zużycia `chat_usage`, T1.2 licznik „wej. / wyj.” pod odpowiedzią, #14 `LANGFUSE_HOST` i `LANGFUSE_ENABLED`.
- **2026-11-01:** naprawione serwowanie plików statycznych przez nginx (ACL dla uid 101), `.env` z prawami `660`, nginx odpowiada na `agents.dom`.

## Otwarte

- **Token MCP do rotacji:** wartość `MCP_AUTH_TOKEN` trafiła 2026-11-02 do rozmowy z agentem — wygenerować nowy i wpisać w `szukaj/.env` oraz `virral-agents/.env`.
- **`szukaj` w trybie dev** (backend i wbudowany frontend `appEnv:"dev"`); przełączenie na prod wymaga `APP_ENV=prod` w `szukaj/.env` i przebudowy `mcp-search`.
- **Limit logowań za Traefikiem:** `TRUSTED_PROXIES` puste → wspólny licznik nieudanych logowań dla wszystkich klientów.
- **Przepływ Git:** `develop` na GitHubie 19 commitów za `master`; po akceptacji push `develop`. Kolejne zmiany tylko przez `develop` (PR feature → `develop`, potem `develop` → `master`).
- **Brak wpisów CHANGELOG** dla T1.0 (Langfuse) i #14.
- Do potwierdzenia na rzeczywistym modelu: #62-495 (kontynuacja badania), OpenRouter, start `./prod-start.sh` i poczta prod.

## Następny priorytet

1. Rotacja tokenu MCP.
2. `TRUSTED_PROXIES` dla Traefika.
3. #62-486 → #62-493 — jakość źródeł badania (część E1/E2 już wdrożona, zweryfikować `TODO-zrodla.md`).
4. #62-494 — strumieniowanie odpowiedzi (SSE).
