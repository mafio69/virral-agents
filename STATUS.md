# STATUS

## Jak prowadzić

- Stan **na teraz**: plik jest nadpisywany, nie dopisywany. Historia każdej zmiany trafia do `CHANGELOG.md`.
- Sekcje: Aktualnie, Gotowe, Otwarte/niepotwierdzone, Następny priorytet. Krótko, bez opisów przebiegu testów.
- Wyniki testów podawaj tylko ostatnie, z datą.
- Kolejka zadań: DevBrain-Todo, lista „virralAI” (id 62); zadania podawaj jako `#62-<id zadania>` (480–508).

---

**Ostatnia aktualizacja:** 2026-10-19
**Gałąź:** `develop` — lokalnie `684fbf7`, `1fee123`, `733edb9` czekają na push właściciela; `master` przez PR na GitHub, CI sprawdza też `develop`.
**Ostatnia pełna walidacja (2026-10-19):** `composer check-all` — formatter 0/46, PHP 182/182 (862 asercje), UI 82/82; E2E Playwright 4/4 (przed #62-483).

## Gotowe (w develop, lokalnie)

- **Konto:** rejestracja (min. 10 znaków, neutralny komunikat dla istniejącego adresu), obowiązkowe potwierdzenie e-maila, logowanie z limitem 5 prób / 15 min (IP + e-mail, czas z `Retry-After`), sesja HttpOnly + Bearer (`Secure` poza jawnym `dev`), wylogowanie po stronie serwera, CLI resetu hasła.
- **Ochrona danych:** szyfrowanie pól (XChaCha20-Poly1305, `USER_DATA_KEY`), HMAC e-maila, hashe tokenów, migracja CLI `bin/protect-user-data.php`.
- **Czat AI:** OVH, Z.ai (GLM), OpenAI (Responses), Anthropic (Messages), Google Gemini (tekst + generowanie obrazów); domyślnie Gemini. Wejściowe obrazy PNG/JPEG/WebP. Historia przypisana do dostawcy.
- **Odporność czatu:** ponowienie nieudanej odpowiedzi (`/retry`, `chat_reply_attempts`), jeden budżet 300 s dla wszystkich dostawców z kodem `provider_timeout`, Nginx 360 s.
- **WWW:** MCP `web_search` (SearXNG) w trybie ASK, badanie wybranych źródeł (checkboxy, szyfrowany koszyk, modal, „Kontynuuj z wybranymi stronami”), bezpieczny fetcher publicznych stron z kodem przyczyny błędu (`FETCH_REASONS`) w UI i evidence dla modelu (#62-483).
- **Infrastruktura:** migracje przed startem FPM (`bin/migrate.php` z bezpiecznym kodem przyczyny błędu, `docker/start-app.sh`), `dev-start.sh dev|prod`, Mailpit w dev, `/test-api` tylko w dev.
- **Jakość:** audyt S1–S3, A1–A6, K1, K3 (`docs/AUDYT-2026-10-17.md`); PHP CS Fixer (PSR-12), `composer check-all`, lokalne E2E (`docs/E2E.md`); licencja MIT.

## Otwarte / niepotwierdzone

- Błąd „Nie udało się odebrać odpowiedzi” po kontynuacji badania — przyczyna niezdiagnozowana na rzeczywistym modelu (#62-495).
- Pobieranie stron słabej jakości (brak redirectów, UA blokowany 403, limit 16k znaków) — #62-484 … #62-493 (E1–E3); #62-483 zrobione.
- Kod `charset_error` bez testu (iconv z `//IGNORE` praktycznie nie zawodzi).
- Niepotwierdzone na działającej instalacji: migracja `chat_reply_attempts` i start kontenera (A5), pełny czat z rzeczywistym dostawcą po wyszukaniu, doręczenie poczty produkcyjnej.

## Następny priorytet

1. #62-484 → #62-493 — jakość źródeł badania (E1 → E2 → E3).
2. #62-494 — strumieniowanie odpowiedzi (SSE).
3. #62-495 — diagnoza błędu odbioru odpowiedzi na rzeczywistym modelu.
