# Lokalne E2E

Testy nie wczytują `.env`, `src/settings.php`, rzeczywistej bazy ani prywatnej historii.
Chromium obsługuje rzeczywisty frontend; backend używa produkcyjnego kontenera DI,
tras, middleware, serwisów, szyfrowanych repozytoriów i SQLite. Poczta przechodzi przez
rzeczywisty SMTP do osobnego Mailpita. Transporty Gemini i MCP wykonują HTTP do
lokalnych serwerów kontraktowych. Pobieranie stron ma lokalny adapter testowy.
Nie są to testy dostępności ani jakości odpowiedzi rzeczywistych dostawców AI/WWW.

## Przygotowanie

Z katalogu repozytorium, po instalacji zależności Composer:

```bash
npm ci --ignore-scripts --no-audit --no-fund
PLAYWRIGHT_BROWSERS_PATH=tests/e2e/runtime/browsers npx playwright install chromium
sudo docker run --detach --rm --name virralai-e2e-mailpit \
  --publish 127.0.0.1:18025:8025 --publish 127.0.0.1:11025:1025 \
  --env MP_MAX_MESSAGES=100 axllent/mailpit:v1.31.6
```

Kontener nie ma wolumenów ani przekazywania poczty na zewnątrz. Nie używaj do tego
prywatnej skrzynki. Porty 18025/11025 są oddzielne od profilu dev aplikacji.
Brak Mailpita powoduje jawny błąd przygotowania, a nie pominięcie testów.

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

```bash
sudo docker stop virralai-e2e-mailpit
```

`--rm` usuwa tylko ten kontener wraz z jego syntetyczną pocztą. Nie zatrzymuj
kontenerów aplikacji ani prywatnej skrzynki. Przeglądarkę i zależności testowe
pozostawia się do kolejnych przebiegów.
