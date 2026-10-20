# Lokalne E2E

Testy nie wczytują `.env`, `src/settings.php`, rzeczywistej bazy ani prywatnej historii.
Chromium obsługuje rzeczywisty frontend; backend używa produkcyjnego kontenera DI,
tras, middleware, serwisów, szyfrowanych repozytoriów i SQLite. Poczta przechodzi przez
rzeczywisty SMTP do testowego Mailpita `mail-trap.dom`. Transporty Gemini i MCP wykonują HTTP do
lokalnych serwerów kontraktowych. Pobieranie stron ma lokalny adapter testowy.
Nie są to testy dostępności ani jakości odpowiedzi rzeczywistych dostawców AI/WWW.

## Przygotowanie

Z katalogu repozytorium, po instalacji zależności Composer:

```bash
npm ci --ignore-scripts --no-audit --no-fund
PLAYWRIGHT_BROWSERS_PATH=tests/e2e/runtime/browsers npx playwright install chromium
```

Poczta trafia do zewnętrznego Mailpita `mail-trap.dom` (ten sam co w dev, utrzymywany
poza repozytorium): SMTP `smtp://mail-trap.dom:1025`, API `http://mail-trap.dom/api/v1`.
Przechwytuje każdą wiadomość, nic nie wychodzi na zewnątrz. Test tylko czyta skrzynkę
i szuka wiadomości po unikalnym adresie `e2e-<czas>@example.invalid`; niczego nie usuwa.
Inny serwer: `E2E_MAILBOX_URL` (API HTTP) i `E2E_SMTP_DSN` (SMTP). Nie używaj prywatnej
skrzynki. Niedostępne API Mailpita powoduje jawny błąd przygotowania, a nie pominięcie testów.

## Uruchomienie

```bash
npm run test:e2e
npm run test:local
```

`test:local` wykonuje kolejno `composer check-all` i E2E. Testy uruchamiają własne
serwery na loopback 18181 i 18182; zajęty port jest błędem, istniejący serwer nie jest
przejmowany. Każdy przebieg zaczyna od usunięcia wyłącznie własnej syntetycznej bazy
i liczników w `tests/e2e/runtime`. Nie uruchamiaj dwóch przebiegów jednocześnie.

Scenariusze przeglądarkowe są kolejnymi etapami jednej sesji testowej:

1. Walidacja hasła, rejestracja, brak logowania przed potwierdzeniem, dostarczenie
   poczty, link potwierdzający, logowanie Enterem, odświeżenie, sesja cookie bez
   localStorage i wylogowanie z odmową dostępu do prywatnych API.
2. Wybór modelu, wysłanie wiadomości, jedna wysyłka, odpowiedź przez natywny transport
   Gemini, zapis i odtworzenie historii.
3. Odmowa ASK bez wyszukania, zgoda, cztery wyniki, odznaczenie jednej strony,
   pobranie trzech wybranych, błąd jednej z nich, opracowanie dostępnych tekstów,
   odświeżenie i zachowanie wcześniejszych źródeł po drugim wyszukaniu.
4. Poprawny pusty wynik MCP i awaria dostawcy jako odrębne stany UI.

Przeglądarka blokuje wszystkie żądania poza loopback. Nieobsłużony błąd JavaScript
lub próba wyjścia poza loopback powoduje błąd testu. Logowanie requestów serwerów,
trace i wideo są wyłączone; nie zapisujemy sesji jako storageState.

Raport: `tests/e2e/runtime/report/index.html`. Zrzut scenariusza źródeł:
`tests/e2e/runtime/research-e2e.png`. Wszystkie dane i raporty runtime są ignorowane
przez Git. Baza i skrzynka zawierają wyłącznie wygenerowane dane testowe.

## Odczytowy smoke test działającej instalacji

```bash
npm run test:e2e:deployment
```

Ten oddzielny test zakłada **prod na localhost:8181**. Nie loguje się, nie tworzy
danych i nie wywołuje modeli ani wyszukiwania. Wymaga strony głównej 200, prywatnych
API 401 oraz `/test-api` i dawnego `/ui/index.html` 404. Nie zastępuje pełnego E2E.
Jeśli instalacja celowo działa w dev, oczekiwanie 404 dla panelu dev nie jest właściwe;
najpierw potwierdź tryb z operatorem, zamiast zmieniać asercję pod otrzymany wynik.

## Sprzątanie

Nie ma kontenerów do zatrzymania. Syntetyczne wiadomości zostają w `mail-trap.dom`
do czasu wyczyszczenia skrzynki przez właściciela. Przeglądarkę i zależności testowe
pozostawia się do kolejnych przebiegów.
