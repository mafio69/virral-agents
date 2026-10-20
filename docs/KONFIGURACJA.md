# Konfiguracja Virral Agents

## Ustawienia zwykłe

Edytuj config/app.php. Plik zwraca tablicę PHP wczytywaną przez src/settings.php.

Zawiera adres aplikacji, endpoint OVH, model domyślny, timeout, ścieżkę bazy aplikacji, ustawienia logowania i lokalny transport poczty bez poświadczeń. Modele Z.ai nadal korzystają z istniejącego rejestru w bazie; nie pobierają nazw modeli z .env.

Nie wpisuj w konfigurację kluczy API, haseł, tokenów, prywatnych adresów ani ścieżek do sekretów. Ścieżki bazy i logów w przykładowej konfiguracji odnoszą się wyłącznie do standardowych katalogów aplikacji.

## Wartości wrażliwe

src/settings.php pobiera tylko:

- OVH_AI_ENDPOINTS_ACCESS_TOKEN — dostęp do OVH.
- DB_HASH — istniejący klucz szyfrowania rejestru dostawców; nie zastępować nowym.
- USER_DATA_KEY — osobny klucz szyfrowania danych użytkowników, zgodnie z docs/OCHRONA_DANYCH.md.
- ZAI_API_KEY — klucz standardowego API Z.ai, używany bezpośrednio przez backend bez wpisu dostawcy w bazie.
- OPEN_AI_KEY — bezpośrednie OpenAI Responses API.
- ANTHROPIC_API_KEY — bezpośrednie Anthropic Messages API.
- GEMINI_KEY — bezpośrednie Google Gemini API.
- OPENROUTER_API_KEY — OpenRouter (Chat Completions); opcjonalnie OPENROUTER_MODELS — identyfikatory modeli po przecinku, np. `anthropic/claude-sonnet-4.5,google/gemini-2.5-flash`. Bez tej listy widoczny jest cały katalog tekstowy.
- MAIL_DSN — transport poczty, jeśli zawiera poświadczenia lub prywatny adres. Gdy go nie ma, używana jest lokalna wartość z konfiguracji PHP.

HTTP i migrator CLI wczytują lokalny plik środowiska przez wspólne src/settings.php.
Bieżąca instalacja korzysta z nieszyfrowanego pliku i nie wymaga dxrun. Wartość
środowiska procesu ma pierwszeństwo przed wpisem pliku. Wpisy encrypted: nie są
traktowane jako działające poświadczenia. Kod aplikacji używa wartości w runtime;
agent nie odczytuje ich podczas konfiguracji.

Pliki .env, klucze, tokeny, ścieżki do sekretów i zaszyfrowane rekordy dostawców zostały zachowane. Agent nie odczytywał ani nie modyfikował tych danych. Dawne zmienne APP_URL, OVH_AI_BASE_URI, OVH_AI_MODEL, OVH_AI_TIMEOUT i LOG_LEVEL nie sterują już aplikacją; ich odpowiedniki są w config/app.php. Nie kopiowano starych wartości z .env, więc przy instalacji należy dopasować jawne ustawienia PHP.

## Docker

Parametry instalacji, porty, wolumeny i UID:GID są w docker-compose.yml. UID:GID ma jawną wartość 1000:1000, odpowiadającą wcześniejszym wartościom domyślnym. Jeśli instalacja używała innych APP_UID/APP_GID, ustaw właściwe liczby w Compose przed odtworzeniem kontenerów.

Compose przekazuje osiem wymienionych wartości wrażliwych; PHP-FPM ma tę samą listę. PHP_IDE_CONFIG jest technicznym ustawieniem IDE zapisanym jawnie w Compose.

Przy bieżącym nieszyfrowanym pliku środowiska Compose wczytuje wartości lokalnie;
po ich zmianie właściciel odtwarza kontener:

```bash
docker compose up -d --force-recreate app
```

Poniższy wariant dotyczy tylko instalacji używających dotenvx, dxrun i sudo;
zachowaj wtedy tylko wymagane wartości wrażliwe:

```bash
dxrun sudo --preserve-env=DB_HASH,USER_DATA_KEY,OVH_AI_ENDPOINTS_ACCESS_TOKEN,ZAI_API_KEY,OPEN_AI_KEY,ANTHROPIC_API_KEY,GEMINI_KEY,OPENROUTER_API_KEY,OPENROUTER_MODELS,MAIL_DSN docker compose up -d --force-recreate
```

Polecenie wykonuje właściciel. Zmiana ustawień tablicy PHP nie wymaga ponownego budowania obrazu. Zmiana Compose lub listy PHP-FPM wymaga odtworzenia kontenerów.

## Weryfikacja

2026-10-13: sprawdzono składnię config/app.php i src/settings.php; oba poprawne. Nie uruchamiano aplikacji, odtworzenia kontenerów ani kolejnej serii testów. Wcześniejsze wyniki testów dotyczą poprzedniego stanu konfiguracji.


## Ochrona danych użytkowników (2026-10-14)

Do trzech wcześniej opisanych wartości dołącza USER_DATA_KEY, niezależny klucz ochrony danych użytkowników. Obowiązuje instrukcja docs/OCHRONA_DANYCH.md. Przy preserve-env dodaj USER_DATA_KEY do istniejącej listy. Bez poprawnego klucza i migracji aplikacja odmawia pracy z bazą. Dotychczasowy DB_HASH pozostaje kluczem rejestru dostawców.

## Środowisko i panel testowy

`config/app.php` ustawia `environment` domyślnie na `prod`. Jawne `APP_ENV`
może nadpisać tę wartość; Compose przekazuje je z domyślnym `prod`, a PHP-FPM
udostępnia workerom. To zwykła nazwa środowiska, nie sekret.

Trasa `/test-api` istnieje wyłącznie dla dokładnej wartości `dev`. Brak wartości,
`prod` i pozostałe środowiska nie rejestrują tej trasy (404). Szablon znajduje się
w `resources/views/test-api.html`, poza katalogiem publicznym, więc dawny
`/ui/index.html` nie udostępnia panelu jako pliku statycznego. Zasoby wspólnego
stylu pozostają pod `/ui/theme.css`.

Zmiana konfiguracji Compose/PHP-FPM wymaga odtworzenia kontenera przez operatora.
Nie zmieniano plików środowiska ani działających kontenerów.

## Lokalna skrzynka Mailpit (dev)

Mailpit przechwytuje pocztę testową zamiast wysyłać ją na prawdziwe skrzynki.
Nie potrzebuje konta w zewnętrznym Mailtrapie, hasła ani klucza API.
Obraz jest przypięty do `axllent/mailpit:v1.31.6`.

Właściciel uruchamia z katalogu repo:

```bash
./dev-start.sh dev
```

Jawny plik developerski ustawia `APP_ENV=dev` i `MAIL_DSN=smtp://mailpit:1025`
wewnątrz aplikacji, niezależnie od wcześniej skonfigurowanego transportu.
Dlatego trzeba uruchomić ponownie także usługę `app`, nie samą skrzynkę.
Plików środowiska nie trzeba zmieniać. Bazowy Compose bez tego pliku nie zmienia
transportu poczty i domyślnie nie uruchamia profilu `dev`.

1. Otwórz aplikację pod `http://localhost:8181` i zarejestruj konto testowe,
   używając np. adresu w domenie `example.invalid`.
2. Otwórz `http://localhost:8025`, wybierz wiadomość „Potwierdź rejestrację”.
3. Kliknij link w treści wiadomości (lub otwórz go w nowej karcie na tym samym komputerze).
   Prowadzi do aplikacji na `localhost:8181`, nie do kontenera SMTP.
4. Po komunikacie potwierdzenia wróć do aplikacji i zaloguj się.

Potwierdzenie nadal wymaga jednorazowego linku; nie ma automatycznej aktywacji ani
obejścia zabezpieczenia S2. Nie wklejaj linków i kodów potwierdzających do czatu.
W tym trybie także panel `/test-api` jest dostępny, zgodnie z S3.

Panel HTTP (8025) i SMTP dla narzędzi hosta (1025) są przypisane wyłącznie do
`127.0.0.1`. PHP w Dockerze korzysta z nazwy `mailpit`, a PHP na hoście z
`smtp://127.0.0.1:1025`. Mailpit nie ma wolumenu danych; wiadomości są tymczasowe,
a liczba przechowywanych wiadomości jest ograniczona do 100. Nie skonfigurowano
przekazywania ani wysyłania wiadomości na zewnątrz.

Powrót do zwykłego transportu (wykonuje właściciel):

```bash
./dev-start.sh prod
```

Bazowy plik ponownie stosuje swój transport poczty i ustawienie środowiska.
Samo zatrzymanie Mailpita nie przywraca transportu w działającym procesie PHP.

Źródła: [Docker Mailpit](https://mailpit.axllent.org/docs/install/docker/),
[wydanie v1.31.6](https://github.com/axllent/mailpit/releases/tag/v1.31.6).

### Jeden skrypt startowy

`dev-start.sh` uruchamiaj z głównego katalogu repo. Wszystkie operacje Docker
wykonuje jako `sudo docker compose`; nie czyta bezpośrednio plików środowiska,
nie wyświetla konfiguracji ani sekretów. Sam Docker Compose przy rzeczywistym
starcie nadal korzysta z lokalnej konfiguracji jak wcześniej; uruchomienie
wykonuje właściciel w swoim terminalu.

```bash
./dev-start.sh dev
./dev-start.sh prod
APP_ENV=dev ./dev-start.sh
./dev-start.sh dev --dry-run
```

Argument ma pierwszeństwo przed APP_ENV procesu. Bez argumentu tylko dokładne
`APP_ENV=dev` wybiera dev; brak lub inna wartość wybiera prod. Wpisu APP_ENV
w samym pliku środowiska skrypt nie odczytuje — w takim przypadku podaj jawnie
argument. `docker-compose.prod.yml` wymusza APP_ENV=prod w kontenerze nawet wtedy,
gdy inna lokalna konfiguracja wskazuje dev. Nie trzeba przekazywać APP_ENV przez sudo.

Skrypt najpierw uruchamia app (w dev również Mailpita). Potem uruchamia Nginxa
bez odtwarzania istniejącego kontenera i bez ponownego ruszania zależności,
a następnie przeładowuje Nginxa, żeby odświeżył adres upstream po zmianie app.
Jeśli app nie wystartuje, nie przechodzi do Nginxa. Nie używa down, usuwania danych
ani force-recreate. W prod po poprawnym starcie zatrzymuje samą skrzynkę Mailpit.
Nie oznacza to wycofania ustawień innych niż jawnie nadpisane przez Compose.


## Migracje przy starcie aplikacji

Compose uruchamia `sh docker/start-app.sh`: najpierw `php bin/migrate.php`, następnie PHP-FPM. Nieudana migracja zatrzymuje start FPM. Żądania HTTP nie aktualizują już schematu; niegotowa baza zwraca 503. Operator uruchamiający aplikację poza Compose musi wcześniej wykonać `php bin/migrate.php` we własnym, prawidłowo skonfigurowanym środowisku.

Migrator inicjalizuje ochronę automatycznie tylko dla pustej bazy. Istniejące dane bez znacznika ochrony wymagają dotychczasowej jawnej procedury z `docs/OCHRONA_DANYCH.md`. Zastosowanie zmiany na instalacji wymaga ponownego uruchomienia aplikacji przez operatora. Agent nie wykonał migracji rzeczywistej bazy ani restartu usług.
