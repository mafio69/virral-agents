# Konfiguracja VirralAI

## Ustawienia zwykłe

Edytuj config/app.php. Plik zwraca tablicę PHP wczytywaną przez src/settings.php.

Zawiera adres aplikacji, endpoint OVH, model domyślny, timeout, ścieżkę bazy aplikacji, ustawienia logowania i lokalny transport poczty bez poświadczeń. Modele Z.ai nadal korzystają z istniejącego rejestru w bazie; nie pobierają nazw modeli z .env.

Nie wpisuj w konfigurację kluczy API, haseł, tokenów, prywatnych adresów ani ścieżek do sekretów. Ścieżki bazy i logów w przykładowej konfiguracji odnoszą się wyłącznie do standardowych katalogów aplikacji.

## Wartości wrażliwe

src/settings.php pobiera tylko:

- OVH_AI_ENDPOINTS_ACCESS_TOKEN — dostęp do OVH.
- DB_HASH — istniejący klucz szyfrowania rejestru dostawców; nie zastępować nowym.
- MAIL_DSN — transport poczty, jeśli zawiera poświadczenia lub prywatny adres. Gdy go nie ma, używana jest lokalna wartość z konfiguracji PHP.

Rozszyfrowana wartość środowiska procesu ma pierwszeństwo przed wpisem pliku. Wpisy encrypted: nie są traktowane jako działające poświadczenia. Kod aplikacji używa wartości w runtime; agent nie odczytuje ich podczas konfiguracji.

Pliki .env, klucze, tokeny, ścieżki do sekretów i zaszyfrowane rekordy dostawców zostały zachowane. Agent nie odczytywał ani nie modyfikował tych danych. Dawne zmienne APP_URL, OVH_AI_BASE_URI, OVH_AI_MODEL, OVH_AI_TIMEOUT i LOG_LEVEL nie sterują już aplikacją; ich odpowiedniki są w config/app.php. Nie kopiowano starych wartości z .env, więc przy instalacji należy dopasować jawne ustawienia PHP.

## Docker

Parametry instalacji, porty, wolumeny i UID:GID są w docker-compose.yml. UID:GID ma jawną wartość 1000:1000, odpowiadającą wcześniejszym wartościom domyślnym. Jeśli instalacja używała innych APP_UID/APP_GID, ustaw właściwe liczby w Compose przed odtworzeniem kontenerów.

Compose przekazuje trzy wymienione wartości wrażliwe; PHP-FPM ma tę samą listę. PHP_IDE_CONFIG jest technicznym ustawieniem IDE zapisanym jawnie w Compose.

Przy uruchamianiu przez dxrun przed sudo zachowaj tylko wymagane wartości wrażliwe:

```bash
dxrun sudo --preserve-env=DB_HASH,OVH_AI_ENDPOINTS_ACCESS_TOKEN,MAIL_DSN docker compose up -d --force-recreate
```

Polecenie wykonuje właściciel. Zmiana ustawień tablicy PHP nie wymaga ponownego budowania obrazu. Zmiana Compose lub listy PHP-FPM wymaga odtworzenia kontenerów.

## Weryfikacja

2026-10-13: sprawdzono składnię config/app.php i src/settings.php; oba poprawne. Nie uruchamiano aplikacji, odtworzenia kontenerów ani kolejnej serii testów. Wcześniejsze wyniki testów dotyczą poprzedniego stanu konfiguracji.
