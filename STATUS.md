# STATUS

## Jak prowadzić

- Stan **na teraz**: plik jest nadpisywany, nie dopisywany. Historia każdej zmiany trafia do `CHANGELOG.md`.
- Sekcje: Aktualnie, Gotowe, Otwarte/niepotwierdzone, Następny priorytet. Krótko, bez opisów przebiegu testów.
- Wyniki testów podawaj tylko ostatnie, z datą.
- Kolejka zadań: DevBrain-Todo, lista „virralAI” (id 62), zadania numerowane 01–25 w kolejności realizacji.

---

**Ostatnia aktualizacja:** 2026-10-19
**Gałąź:** `develop` (lokalnie; bez push)
**Ostatnia pełna walidacja (2026-10-19):** `composer check-all` — formatter 0/46, PHP 179/179 (822 asercje), UI 81/81; E2E Playwright 4/4.

## Gotowe (w develop, lokalnie)

- **Konto:** rejestracja (min. 10 znaków), obowiązkowe potwierdzenie e-maila, logowanie z limitem 5 prób / 15 min (IP + e-mail), sesja HttpOnly + Bearer, wylogowanie po stronie serwera, CLI resetu hasła.
- **Ochrona danych:** szyfrowanie pól (XChaCha20-Poly1305, `USER_DATA_KEY`), HMAC e-maila, hashe tokenów, migracja CLI `bin/protect-user-data.php`.
- **Czat AI:** OVH, Z.ai (GLM), OpenAI (Responses), Anthropic (Messages), Google Gemini (tekst + generowanie obrazów); domyślnie Gemini. Wejściowe obrazy PNG/JPEG/WebP. Historia przypisana do dostawcy.
- **Odporność czatu:** ponowienie nieudanej odpowiedzi (`/retry`, `chat_reply_attempts`), jeden budżet 300 s dla wszystkich dostawców z kodem `provider_timeout`, Nginx 360 s.
- **WWW:** MCP `web_search` (SearXNG) w trybie ASK, badanie wybranych źródeł (checkboxy, szyfrowany koszyk, modal, „Kontynuuj z wybranymi stronami”), bezpieczny fetcher publicznych stron.
- **Infrastruktura:** migracje przed startem FPM (`bin/migrate.php`, `docker/start-app.sh`), `dev-start.sh dev|prod`, Mailpit w dev, `/test-api` tylko w dev.
- **Jakość:** audyt S1–S3, A1–A6, K1, K3 (`docs/AUDYT-2026-10-17.md`); PHP CS Fixer (PSR-12), `composer check-all`, lokalne E2E (`docs/E2E.md`); licencja MIT.

## Otwarte / niepotwierdzone

- **P1:** `master` za `develop` — PR na GitHub (CI dziś uruchamia się tylko dla `main/master`; zadanie DT 02).
- Błąd „Nie udało się odebrać odpowiedzi” po kontynuacji badania — przyczyna niezdiagnozowana na rzeczywistym modelu (DT 16).
- Pobieranie stron słabej jakości (brak redirectów, UA blokowany 403, limit 16k znaków) — DT 04–14 (E1–E3), niezaczęte.
- Niepotwierdzone na działającej instalacji: migracja `chat_reply_attempts` i start kontenera (A5), pełny czat z rzeczywistym dostawcą po wyszukaniu, doręczenie poczty produkcyjnej.

## Następny priorytet

1. DT 02 — CI na develop + lint + test-ui, potem PR develop → master.
2. DT 03 — sprzątanie repo (za zgodą).
3. DT 04 → 14 — jakość źródeł badania (E1 → E2 → E3).
