# STATUS

## 2026-10-16 — hotfix widocznej zgody WWW

- Zastąpiono natywny `window.confirm` panelem zgody w stronie: dokładne zapytanie, odbiorcy, Allow / Deny. Przeglądarka osadzona nie musi otwierać systemowego popupu. Odmowa daje jawny komunikat.
- Blokada ponownego kliknięcia podczas zgody, ponowna walidacja rozmowy/modelu/zapytania przed wysyłką i widoczny komunikat nieoczekiwanego błędu. Zmiana rozmowy unieważnia starą zgodę.
- PHP 120/120 (497 asercji), UI 46/46, Composer, składnia JS i diff-check poprawne. Test w przeglądarce w aplikacji z prawdziwym panelem: widoczna zgoda, zero wysyłek przed decyzją i po Deny, jedna po Allow. API atrapowe, bez WWW i rzeczywistego modelu. Zrzut: `artifacts/search-inline-consent-2026-10-16.png`.

## 2026-10-16 — hotfix wyszukiwania bez wiadomości

- Osobna gałąź `codex/fix-search-query-only`. Przycisk WWW zależy od poprawnego zapytania, sesji i modelu, a nie od treści dolnego pola. Zmiana zapytania natychmiast aktualizuje dostępność przycisku.
- Po zgodzie samo zapytanie jest treścią wiadomości, jeśli użytkownik nie wpisał osobnego polecenia. Deny nie tworzy rozmowy ani nie wysyła wiadomości. Busy, brak modelu/sesji i niepewny zapis nadal blokują wysłanie.
- PHP 120/120 (497 asercji), UI 44/44; Composer, składnia JS/PHP i diff-check poprawne (istniejące ostrzeżenie licencji). Izolowany test przeglądarkowy z prawdziwymi modułami i atrapami API potwierdził aktywację, Deny i jedno wysłanie po Allow. Chrome niedostępne narzędziowo; użyto przeglądarki w aplikacji.
- Dowód: `artifacts/search-query-hotfix-2026-10-16.png`. Bez rzeczywistych wywołań WWW lub AI. Rozpoczęte zmiany zbierania źródeł zachowane w lokalnym stashu, poza hotfixem.

## 2026-10-16 — MCP Search i jawna zgoda ASK

- `ChatService` korzysta z `ChatReplyGeneratorInterface`; jedno zatwierdzone zapytanie MCP poprzedza odpowiedź wybranego modelu. Wyniki sprawdzone z atrapami natywnych OpenAI, Anthropic i Gemini oraz OVH. Bez automatycznej pętli narzędzi i bez fetch.
- UI ma osobne zapytanie WWW i przycisk „Wyszukaj i wyślij…”. Dokładna treść/odbiorcy są pokazywani przy każdej zgodzie. Zwykłe Wyślij nie uruchamia WWW. Backend wymaga zgody, zapytania i limitu 1; zgoda nie jest zapamiętywana.
- MCP obsługuje JSON/SSE, sesję, schemat narzędzia, błędy i walidację URL. Brak wyników/awaria nie uruchamiają zastępczej odpowiedzi modelu. Źródła są dopisywane do szyfrowanego tekstu historii.
- Compose dołącza aplikację do zewnętrznej sieci `ai`; `MCP_SEARCH_URL` przekazywane do FPM. Diagnostyka bez konfiguracji/sekretów: `bin/check-mcp-search.php`.
- Weryfikacja: PHP 120/120, 497 asercji; UI 41/41 (`--test-isolation=none`), składnia zmienionych PHP i JS oraz diff-check poprawne. Composer validate/platform poprawne; istniejące ostrzeżenie o braku licencji.
- Ponowna końcowa walidacja: PHP 120/120 (497 asercji), UI 41/41 i kontrole Composer/diff poprawne. Lokalny commit implementacji; push wykonuje właściciel.
- Po uruchomieniu kontenera przez właściciela test rzeczywistego MCP zakończył się sukcesem: `php bin/check-mcp-search.php --query='MCP Streamable HTTP' --approve-search` potwierdził `web_search` i zwrócił 3 wyniki `source: searxng`. Jednorazowa zgoda została wykorzystana; bez Perplexity i bez fetch stron. Nie wykonywano wywołania rzeczywistego modelu AI ani odczytu sekretów. Zastosowanie nowej konfiguracji kontenera aplikacji i pełna odpowiedź w UI pozostają do potwierdzenia przez właściciela.

## 2026-10-15 — generowanie obrazów Gemini

- Gałąź codex/gemini-image-generation. Osobny kontrakt odpowiedzi multimodalnej, metadane generowania w katalogu, odbiór jednej grafiki do 8 MiB i szyfrowany zapis przez istniejące repozytorium. Brak migracji.
- Podgląd i pobieranie grafiki asystenta, odrzucanie zdalnych źródeł/SVG, błędnego MIME, zbyt dużych obrazów i niekompletnych odpowiedzi. Historia grafiki nie jest odsyłana bez obsługi thought signatures; edycja poprzedniej grafiki pozostaje kolejnym etapem.
- Testy obejmują protokół, szyfrowany zapis/odczyt i renderowanie/pobieranie. Bez rzeczywistego wywołania Gemini. OCR/PDF i pozostałe dokumenty pozostają do następnego etapu.
- Pełna walidacja: PHP 111/111 (419 asercji), UI 36/36; składnia dziewięciu PHP i dwóch JS, diff-check i kontrole Composer poprawne. Dotychczasowe ostrzeżenie Composer o braku licencji.

## 2026-10-15 — nazwy kluczy i domyślne Gemini

- Ustawienia, Compose i PHP-FPM używają OPEN_AI_KEY oraz GEMINI_KEY, zgodnie z nazwami właściciela. Zaktualizowano instrukcje; bez odczytu i zmiany wartości kluczy.
- Nowy użytkownik czatu domyślnie wybiera Google/Gemini. Jawny zapamiętany wybór oraz dostawca istniejącej rozmowy są zachowane.
- PHP 108/108 (403 asercje), UI 35/35, składnia ustawień/kontrolera i diff-check poprawne. Generowanie obrazów pozostaje osobnym etapem; obecna integracja obsługuje tekst i analizę obrazów wejściowych.

## 2026-10-15 — integracja Google i domknięcie trzech dostawców

- Gałąź codex/integration-google: natywne Gemini generateContent, katalog z paginacją, GEMINI_API_KEY w DI i Compose/FPM. Klucz wyłącznie w nagłówku, systemInstruction i obrazy inlineData; bez narzędzi.
- Testy wspólnego routingu sprawdzają osobne historie OpenAI, Anthropic i Google oraz odmowę bez klucza przed siecią. PHP 108/108, 403 asercje; UI 34/34. Testy z atrapami HTTP, bez rzeczywistych zapytań dostawców.
- Każdy dostawca ma własną gałąź i commit; lokalne scalenia do develop. Push wykonuje właściciel. Weryfikacja rzeczywistych uprawnień kont pozostaje lokalna.

## 2026-10-15 — integracja Anthropic

- Osobna gałąź codex/integration-anthropic z develop, po lokalnym domknięciu etapu OpenAI. Natywny klient Messages i katalog konta z paginacją, przez wspólne interfejsy i transport.
- ANTHROPIC_API_KEY w ustawieniach, DI i Compose/FPM. Selektor anthropic:..., system prompt osobno, obrazy base64, tylko odpowiedź tekstowa. Bez wykonywania narzędzi.
- Testy protokołu, paginacji, metadanych obrazu, odrzucenia zdalnego URL i zapętlenia kursora: PHP 100/100, 347 asercji; UI 33/33. Składnia pięciu PHP i diff/Composer poprawne; istniejące ostrzeżenie o licencji i niedostępnym cache Composer. Brak prawdziwych zapytań API, odczytu sekretów i push.

## 2026-10-15 — integracja OpenAI

- Osobna gałąź codex/integration-openai z lokalnego develop po domknięciu poprzednich zmian. Właściciel wybrał użycie istniejącego klucza; agent nie odczytał jego wartości.
- OPENAI_API_KEY w ustawieniach, DI i Compose/FPM. Responses API, katalog konta, obrazy i historia. Wspólny transport oraz rejestr dostawców przez interfejsy przygotowane do kolejnych integracji.
- Selekcja openai:... zapisywana w rozmowie; kontrola właściciela, brak zastępowania dostawcy, błędy bez odpowiedzi/wyjątków dostawcy. Instrukcja: docs/AI_PROVIDERS.md.
- Weryfikacja: PHP 94/94 (316 asercji), UI 32/32, składnia 10 PHP i kontrole diff/Composer poprawne; dotychczasowe ostrzeżenie o licencji. Brak płatnych zapytań i dostępu do rzeczywistych sekretów/bazy. Bez push.

## 2026-10-15 — działające Z.ai z konfiguracji środowiska

- Podłączono potwierdzoną przez właściciela nazwę ZAI_API_KEY w src/settings.php, DI, Compose i PHP-FPM. Klient używa standardowego endpointu Z.ai; bez zapisu klucza do kodu, bazy i odpowiedzi API.
- Rejestr obsługuje selektory zai:... z konfiguracji procesu oraz wcześniejsze db:... z bazy. Walidacja katalogu i własności zachowana; brak klucza blokuje wysyłkę bez przekierowania historii do OVH lub innego połączenia.
- PHP 87/87, 270 asercji; UI 31/31; składnia pięciu PHP i kontrole diff/Composer poprawne (dotychczasowe ostrzeżenie o licencji). Lokalny katalog zwrócił 8 modeli configured:true; właściciel potwierdził działanie czatu.
- Agent nie odczytywał kluczy ani danych bazy i nie wysłał rzeczywistej wiadomości testowej. Osobną kartę testową zamknięto bez wysyłania. Bez push; nie dodawano nieśledzonego katalogu chronionych danych do commitu.

## 2026-10-15 — porównanie jasnego i ciemnego tła

- Właściciel chce zobaczyć oba warianty przed wyborem kolorystyki. Landing ma przełącznik Ciemne tło / Jasne tło: ten sam układ i formularz, zmieniają się wyłącznie zmienne kolorów. Bez zapisywania preferencji i bez zmiany czatu.
- Przełączanie zachowuje wpisane dane i nie wysyła formularza. PHP 82/82 (250 asercji), UI 31/31, kontrole diff/Composer poprawne (istniejące ostrzeżenie o licencji).
- Zapisano rzeczywiste podglądy przeglądarkowe: artifacts/account-compare-light-2026-10-15.jpg oraz artifacts/account-compare-dark-2026-10-15.jpg. Nie dokonywano logowania/rejestracji ani operacji na danych użytkownika. Bez push.

## 2026-10-15 — jasny start i formularze konta

- Na wyraźne życzenie właściciela zmiana obejmuje wyłącznie landing, panel startowy oraz logowanie/rejestrację. Czat i panel testowania API zachowują swój wygląd; wspólnego theme.css nie zmieniono.
- Białe tło, bez ikon i metalicznych/kafelkowych dekoracji; akcje jako prosty tekst, pola z pojedynczą dolną linią. Zachowano semantykę przycisków i widoczny fokus klawiatury.
- Logowanie/rejestracja korzystają z natywnych form/submit, Enter i walidacji przeglądarki. Dodano etykiety for, autocomplete i required. Przyciski zamknięcia/przełączenia nie wysyłają formularza.
- PHP 82/82, 250 asercji; UI 30/30; diff-check i kontrole Composer poprawne (dotychczasowe ostrzeżenie o licencji). Przeglądarka: białe tło, dolna linia 1px, Enter na fikcyjnych danych wywołuje logowanie, pusty formularz zatrzymuje walidacja. Bez tworzenia kont i emaili.
- Podgląd: artifacts/account-light-2026-10-15.jpg. Nie zmieniano sekretów ani hasła właściciela. Bez push.

## 2026-10-14 — wylogowanie unieważniające sesję

- POST /api/auth/logout usuwa sesje wskazane przez cookie i nagłówek Bearer, wygasza ciasteczko HttpOnly i zwraca 204/no-store. Wylogowanie jest idempotentne; pozostałe sesje konta pozostają aktywne.
- Landing wylogowuje przez backend. Awaria nie ukrywa panelu ani nie udaje wylogowania; niedostępny localStorage nie blokuje poprawnego wylogowania.
- PHP 82/82, 250 asercji; UI 28/28. Składnia, diff-check i kontrole Composer poprawne; istniejące ostrzeżenie o licencji. Testy HTTP na fikcyjnych kontach i SQLite w pamięci.
- Nie wykonywano wylogowania/resetu rzeczywistego konta ani połączeń dostawców. Reset hasła właściciela pozostaje do lokalnego wykonania. Bez push.

## 2026-10-14 — lokalny reset hasła z konfiguracją aplikacji

- bin/reset-password.php korzysta ze wspólnego src/settings.php i fabryki szyfratora DI, tak jak HTTP oraz migrator. Właściciel uruchamia zwykłe php w interaktywnym terminalu, bez sudo.
- Zachowano ukryty odczyt hasła, standardowy password_hash, transakcję zmiany hasha oraz unieważnienie sesji wyłącznie wybranego konta. Ograniczono ujawnianie komunikatów wyjątków konfiguracji.
- PHP 80/80, 240 asercji; UI 26/26; składnia skryptu, diff-check i kontrole Composer poprawne (dotychczasowe ostrzeżenie o licencji).
- Agent nie uruchamiał resetu, nie odczytywał sekretów ani rekordów bazy. Reset na rzeczywistym koncie i ponowne logowanie wymagają wykonania przez właściciela. Bez push.

## 2026-10-14 — wspólna konfiguracja HTTP i migratora CLI

- Właściciel sprostował sposób uruchamiania: bieżący plik środowiska nie jest szyfrowany i nie używa dxrun. Poprzednie instrukcje wynikały z dokumentacji historycznej.
- Ładowanie lokalnej konfiguracji przez Dotenv przeniesiono z public/index.php do wspólnego src/settings.php. Migrator CLI korzysta z tych samych ustawień i fabryki szyfratora DI; nie wymaga eksportowania wartości z pliku przed zwykłym php.
- Migrator podaje bezpieczny kod etapu/przyczyny, bez treści wyjątku, ścieżek i kluczy. Instrukcje obejmują zwykłe php i Compose; wariant dxrun dotyczy wyłącznie instalacji z dotenvx.
- Walidacja: PHP 80/80, 240 asercji; UI 26/26; składnia trzech PHP, diff-check i wymagania platformy Composer poprawne. Composer validate: poprawny z dotychczasowym ostrzeżeniem o licencji.
- Agent nie odczytał pliku środowiska, nie uruchomił migratora na rzeczywistej bazie i nie potwierdził migracji właściciela. Kolejny krok lokalny: zatrzymać aplikację, php bin/protect-user-data.php --apply, uruchomić ponownie i zweryfikować HTTP.

## 2026-10-14 — start pustej bazy dev

- Gałąź `codex/fix-dev-database-startup`, utworzona za zgodą z lokalnego develop bez fetch.
- Start automatycznie przygotowuje znacznik szyfrowania tylko przy braku danych użytkowników i sesji, z poprawnym kluczem. Transakcja blokuje równoczesną inicjalizację; istniejące dane nadal wymagają jawnej migracji CLI.
- Log i nagłówek X-App-Error zawierają zamknięty kod etapu/przyczyny, bez danych i treści wyjątku. Rozróżniane są brak klucza, błędny format, użycie klucza dostawców i brak sodium.
- Weryfikacja przed commitem: PHP 80/80, 240 asercji; UI 26/26; składnia sześciu zmienionych PHP, diff-check i wymagania platformy Composer poprawne. Composer validate: poprawne pliki, dotychczasowe ostrzeżenie o braku licencji.
- Lokalny HEAD na 127.0.0.1:8181 potwierdził 503 i user_data_key_missing. Poprawiono brak USER_DATA_KEY w przykładzie preserve-env w docs/KONFIGURACJA.md. Konfigurację klucza i odtworzenie kontenerów musi wykonać właściciel zgodnie z zakazem odczytu/modyfikacji sekretów.
- Nie odczytywano sekretów ani rzeczywistej bazy, nie wykonywano migracji, odtworzenia kontenerów ani push. Właściciel zatwierdził domykanie etapów commitem po testach. Istniejące dane nadal wymagają jawnej migracji.

Ostatnia aktualizacja: 2026-10-14. Gałąź pracy: `security/protect-user-data`; docelowa gałąź lokalnego scalenia: `develop`.

Aktualne wyniki są w sekcji „ochrona danych użytkowników w SQLite” na końcu pliku. Starsze sekcje zachowano jako zapis poprzednich sesji.

## Gotowe

- Widok `/chat` korzysta z Design/chat.html i istniejącego API czatu.
- Historia rozmów, nowe rozmowy, wybór modelu przed rozpoczęciem, Enter/Shift+Enter.
- Markdown oczyszczany przez lokalny DOMPurify; ściąga otwierana i zamykana nagłówkiem.
- Ochrona przed powtórnym wysłaniem podczas odpowiedzi i po niepewnym wyniku żądania.
- 16/16 testów jednostkowych; poprawna składnia src/routes.php.

## Do sprawdzenia i dalsze etapy

- Sprawdzenie widoku w przeglądarce i rzeczywistej odpowiedzi AI przez użytkownika.
- Backend czatu korzysta z OVH oraz skonfigurowanych modeli GLM z rejestru Z.ai; rzeczywiste połączenie Z.ai wymaga potwierdzenia.
- Pełna izolacja modeli, osobny kanał sekretów i maskowanie czatu/logów nie są wdrożone.
- Inspekcja nowego modułu w PhpStorm przekroczyła limit czasu.
- Bez commitu i push. Poprzedni stan dokumentacji zachowany w CHANGELOG.md.

- Wspólna paleta i przyciski wszystkich istniejących ekranów: public/ui/theme.css; bez nowych widoków.

## 2026-10-13 — katalog OVH

- Lista modeli jest pobierana przez klienta OVH z autoryzacją; serwis nie tworzy osobnego klienta HTTP.
- Usunięto statyczną listę awaryjną: niedostępność katalogu zwraca HTTP 503.
- Dodano metadane obsługi obrazów dla zweryfikowanych modeli Qwen i etykiety w wyborze modelu; Qwen3.8-27B oznaczony jako model kodowania.
- Testy katalogu korzystają z atrap HTTP; usunięto test wykonujący prawdziwe połączenie OVH.
- Testy PHP wykonane za zgodą: `php vendor/bin/phpunit --filter 'ChatService|OvhAiClient'` — 15/15 testów, 49 asercji, wynik OK (2026-10-13).
- Załączniki obrazów, zmiana domyślnego modelu konfiguracji i kolejni dostawcy pozostają do wdrożenia. Bez commitu i push.

## 2026-10-13 — analiza obrazów w czacie

- Przycisk Obraz, podgląd i usuwanie załącznika; jedna grafika PNG/JPEG/WebP na wiadomość.
- Przeglądarka zmniejsza obraz do 1600 px i ponownie koduje JPEG bez oryginalnej nazwy i metadanych; limit wejścia 20 MB, wysyłki 512 KiB.
- Backend sprawdza format, MIME, rozmiar i wymiary, odrzuca zdalne URL i SVG oraz modele bez obsługi obrazów.
- Obraz jest zapisywany atomowo z wiadomością w chat_message_images; idempotentna migracja dodaje tabelę podczas zwykłego startu aplikacji. Nie uruchamiano migracji na rzeczywistej bazie przez agenta.
- Kolejne wiadomości zawierają obraz w historii tego samego czatu; dostęp nadal wymaga właściciela rozmowy. Markdown modelu nie może osadzać obrazów ani HTML.
- Wyjątki HTTP klienta OVH nie ujawniają już odpowiedzi dostawcy ani poprzedniego wyjątku; brak pełnego maskowania wszystkich sekretów i brak oceny odporności na prompt injection.
- Testy wykonane za zgodą: PHP 26/26 (70 asercji), UI 21/21. Brak testu prawdziwego OVH i przeglądarki; bez commitu/push.

## 2026-10-13 — naprawa konfiguracji OVH i modelu domyślnego

- Jeden domyślny model Qwen3.6-27B; stara nazwa Qwen3-Coder-30B-A3B-Instruct jest rozwiązywana do domyślnego również w wcześniejszych rozmowach.
- Compose przekazuje konkretne zmienne konfiguracji, a nowy docker/php-fpm-env.conf udostępnia je workerom PHP przez listę dopuszczonych nazw.
- settings.php odczytuje konfigurację procesową i ignoruje zaszyfrowane wartości; agent nie odczytywał ani nie zmieniał plików środowiska.
- Klient normalizuje stary dedykowany endpoint Qwen Coder do wspólnego API OVH; rozróżnia brak konfiguracji, odmowę dostępu, model/endpoint 404, limit, błąd sieci i niepoprawną odpowiedź.
- UI pokazuje wyłącznie komunikaty z zamkniętej listy; diagnostyka jest przekazywana kodem w X-AI-Error, bez odpowiedzi dostawcy, wyjątków i trace.
- Obraz można dodać także przy wyborze domyślnego modelu, gdy katalog potwierdza obsługę obrazów.
- Zmiany Compose wymagają odtworzenia kontenerów; przy dxrun przed sudo trzeba zachować konkretne zmienne OVH, bo sudo może je usuwać.
- Próba odczytu publicznych tras localhost z narzędzia nie nawiązała połączenia; nie potwierdzono przyczyny na działającym serwerze.
- Na prośbę użytkownika nie uruchamiano ponownie testów. Poprzednie zielone wyniki dotyczą wcześniejszej wersji. Oczekiwania istniejących testów uaktualnione; bez commitu i push.

## 2026-10-13 — integracja Z.ai / GLM

- OpenAiCompatClient korzysta z istniejącego Guzzle; OVH deleguje do wspólnego transportu. Bez nowej zależności i bez ujawniania odpowiedzi błędów dostawcy.
- ZaiChatModelRegistry przez ChatModelRegistryInterface podłącza aktywne modele GLM ze standardowego API do istniejącego wyboru czatu. Flash/FlashX 5.3 obsługują obecne załączniki obrazów.
- Selektor rozmowy wiąże rekord, dostawcę, model API i endpoint; zmiana powiązania blokuje wysyłkę historii. Własność sprawdzana przed dostępem do klienta.
- Idempotentne tworzenie brakujących tabel rejestru. Brak odczytu rzeczywistej bazy, zmiany sekretów i automatycznego dodawania providerów/modeli.
- Runtime DB_HASH dodany do listy Compose/PHP-FPM; fabryka uruchamiana leniwie. Odtworzenie kontenerów i konfiguracja rekordów należą do właściciela; prawdziwe połączenie niepotwierdzone.
- Dokumentacja i instrukcje: docs/MODELE_ZAI.md. Coding Plan wyłączony z tej integracji.
- Jedna zatwierdzona weryfikacja PHP: 32/32 testy, 92 asercje. Atrapy HTTP i baza w pamięci, bez sieci. Nie powtarzano testów UI; bez commitu/push.

## 2026-10-13 — jawna konfiguracja PHP

- config/app.php zawiera zwykłe ustawienia; src/settings.php wczytuje tablicę i dołącza wyłącznie OVH_AI_ENDPOINTS_ACCESS_TOKEN, DB_HASH oraz wrażliwy MAIL_DSN.
- Usunięto przekazywanie nazw modeli, endpointu, timeoutu, adresu aplikacji i poziomu logów przez środowisko Compose/PHP-FPM. UID:GID ustawiany jawnie w Compose (dotychczasowe wartości domyślne 1000:1000).
- Pliki .env, rzeczywiste klucze, tokeny, ścieżki do sekretów i baza pozostały bez odczytu lub zmian. Wcześniejsze niestandardowe ustawienia instalacji trzeba przenieść ręcznie do konfiguracji, bez ujawniania wrażliwych wartości.
- Składnia obu plików PHP sprawdzona za zgodą: poprawna. Bez kolejnej serii testów, odtworzenia kontenerów, commitu i push. Instrukcja: docs/KONFIGURACJA.md.

## 2026-10-13 — działające wybory połączenia i modelu

- Wybór połączenia OVH / Z.ai jest podłączony do AJAX /api/ai-models?connection=...; lista przygotowanych integracji ma /api/ai-connections. ChatGPT i inni dostawcy nie są jeszcze przygotowani.
- Ostatni poprawny wybór połączenia i modelu zapisywany w pamięci przeglądarki, osobno dla każdego połączenia. Pierwszy model jest wybierany, gdy brak zapamiętanego lub został usunięty. Stare odpowiedzi AJAX nie nadpisują nowszego wyboru.
- Po rozpoczęciu rozmowy selektory są zablokowane, aby nie przekazywać historii do innego dostawcy. Nowa rozmowa odblokowuje wybór.
- Moduły public/ui/chat przeniesione z .mjs na .js, bez kopii; importy uaktualnione. Widok zaczął wykonywać skrypty po tej zmianie. Dodano package.json z type:module dla narzędzi lokalnych.
- Przy nieudanym pobraniu lista połączeń nadal jest dostępna, model pokazuje Brak dostępnych modeli i przycisk Pobierz modele. Wysłanie bez dostępnego modelu jest blokowane.
- Przeklikano w przeglądarce: zmianę OVH na Z.ai, ponowienie pobierania, podpowiedź Funkcja w PHP, Shift+Enter, rozwinięcie/zamknięcie Markdown, zwijanie historii i Nowa rozmowa (czyści pole).
- Narzędzie przeglądarkowe blokuje otwarcie localhost /api/ai-connections (ERR_BLOCKED_BY_CLIENT); żądania API czatu także nie powiodły się. Brak potwierdzenia rzeczywistego katalogu, przywrócenia modelu po przeładowaniu i wysłania wiadomości. Nie traktować błędu narzędzia jako dowodu awarii serwera.
- Zgodnie z poleceniem użytkownika nie uruchamiano testów jednostkowych ani poleceń systemowych; testy na tym etapie to interakcje UI. Dotychczasowe wyniki 32/32 dotyczą wcześniejszego kodu. Bez commitu/push.

## 2026-10-13 — naprawa AJAX i przepływu konta

- Zidentyfikowano i poprawiono błędny kontekst natywnego fetch w ChatApi. To blokowało żądania czatu przed dojściem do serwera. Wcześniejsza diagnoza ogólnej blokady API przez narzędzie była błędna; bezpośrednia nawigacja do /api była osobnym ograniczeniem.
- Wykonano rzeczywisty test UI: Z.ai pobrało 8 modeli z publicznej specyfikacji OpenAPI przez backend PHP. Pierwszy model został wybrany automatycznie; po wyborze glm-5.3-flash i przeładowaniu wybór został przywrócony.
- Katalog publiczny nie potwierdza uprawnień konta. Brak skonfigurowanych modeli dostawcy w rejestrze Z.ai jest oznaczony configured:false; próba rozmowy z takim wyborem jest blokowana przed zapisem, bez przekazywania do OVH.
- Rzeczywisty katalog OVH zwrócił provider_auth; nie odczytywano klucza, nie potwierdzono przyczyny odmowy.
- MailService tworzy transport dopiero przy wysyłaniu. AuthService po zapisie konta obsługuje błąd maila oddzielnie; API zwraca confirmation_sent, a UI nie zapewnia o wysłaniu, jeśli ono się nie udało. Nie zmieniono zasad dopuszczania istniejących kont do logowania.
- Usunięto adresy email z komunikatów logów AuthService; błąd transportu poczty nie jest zapisywany z linkiem potwierdzającym ani wyjątkiem.
- Formularze mają blokadę podwójnego wysłania i komunikaty błędów połączenia. Link /#login otwiera teraz właściwy formularz.
- Przeklikano puste formularze: rejestracja otrzymała walidację Email is required, logowanie komunikat Sprawdź email i hasło. Nie testowano utworzenia nowego konta, dostarczenia emaila ani zalogowania prawdziwymi danymi; użytkownik został poproszony o wpisanie danych wyłącznie lokalnie.
- Dodana na potrzeby diagnozy bezpieczna diagnostyka została usunięta po znalezieniu błędu fetch. Dodano wersję URL modułów, aby wczytać zmiany z pominięciem starej pamięci przeglądarki.
- Inspekcje PhpStorm trzech plików przekroczyły limit czasu; nie potwierdzają poprawności. Bez uruchamiania testów jednostkowych, poleceń systemowych, commitu lub push.

## 2026-10-13 — lokalny reset hasła właściciela

- Dodano bin/reset-password.php: wyłącznie CLI i interaktywny terminal, ukryty odczyt nowego hasła dwa razy, password_hash(PASSWORD_DEFAULT), transakcja zmiany hasha i unieważnienia sesji wybranego konta.
- Nie odczytuje starego hasha ani plików środowiska; nie tworzy nowej bazy ani konta. Brak publicznego endpointu resetu.
- Agent nie uruchomił skryptu i nie odczytywał rzeczywistej bazy lub hasła. Właściciel uruchamia lokalnie php bin/reset-password.php z katalogu projektu. Reset nie został jeszcze wykonany ani zweryfikowany.


## 2026-10-14 — minimalna naprawa logowania i rejestracji

- Zachowano istniejące niezacommitowane zmiany użytkownika na codex/chat-dark; bez przełączania gałęzi, commitu i push. REPO.md nie istnieje.
- Poprawiono nieistniejący register-result w showForm: rejestracja używa reg-result.
- Niedostępny localStorage nie przerywa żądań formularzy. Inicjalizacja sprawdza również sesję cookie HttpOnly; panel otwiera się po potwierdzeniu /api/auth/me.
- Login zwraca Set-Cookie w odpowiedzi PSR-7, z HttpOnly, terminem ważności i Secure dla HTTPS. Nie korzysta już z globalnego setcookie.
- RegisterInputMapper przycina email przed walidacją. Zachowano wcześniejsze oddzielenie awarii poczty od zapisu konta i uaktualniono oczekiwanie komunikatu duplikatu w teście.
- Testy autoryzacji: PHP 17/17, 48 asercji; formularze 5/5 (wykonanie skryptu z atrapą DOM, bez rzeczywistej przeglądarki). Nowe testy HTTP używają rzeczywistych tras/middleware, bazy SQLite w pamięci i atrapy poczty.
- Pełny PHPUnit: 57 testów, 152 asercje; pozostają wcześniejsze 1 failure i 1 error w ZaiChatIntegrationTest. Pełne testy UI wskazały 14 błędów istniejących testów czatu: brak listConnections w atrapach API. Nie zmieniano tego modułu.
- Składnia zmienionych plików PHP i diff-check plików tej poprawki poprawne. Pełny diff-check zgłasza wcześniejsze spacje w CHANGELOG.md i public/ui/index.html; pozostawiono je bez zmian.
- PhpStorm: mapper bez ostrzeżeń; inspekcja kontrolera anulowana. Inspekcja nowego testu wskazuje brak ext-pdo w composer.json, brak dialektu SQL oraz mock final MailService; projekt używa DG\BypassFinals przez rozszerzenie PHPUnit, a testy wykonują się poprawnie.
- Nie czytano sekretów ani rzeczywistej bazy. Nie potwierdzono dostarczenia poczty i logowania do istniejącego konta w działającej instalacji. Pełny zestaw nie jest zielony — bez commitu zgodnie z GIT-FLOW.md.


## 2026-10-14 — bieżący wynik porządkowania przed commitem

- Zachowano wcześniejszą pracę nad czatem, OVH/Z.ai, obrazami, wyglądem, konfiguracją i kontem. Nie zmieniano istniejącej konfiguracji opencode.json.
- Uzupełniono wspólną atrapę połączeń AI oraz oczekiwania asynchronicznego wyboru modeli w testach UI. Testy Z.ai jawnie wybierają zai i sprawdzają publiczny katalog configured:false oraz odmowę użycia usuniętego/niedozwolonego połączenia.
- Usunięto martwy fragment listy awaryjnej w ChatService. Poprawiono app_url na http://localhost:8181 zgodnie z Compose; PHP nie rozwija ${APP_PORT:-8585} w konfiguracji.
- Cache PHPUnit wyłączono ze śledzenia. Cache oraz Design/*.zip i public/ui/favicons.zip pozostają na dysku jako ignorowane pliki; wyodrębnione materiały i zasoby aplikacji zachowano.
- Pełne testy: PHP 57/57, 161 asercji; UI 26/26. Składnia 37 plików PHP, 12 JS/MJS, 4 skryptów HTML, 6 JSON; kontrole diff i wymagań platformy Composer: OK.
- Composer validate --no-check-publish: poprawne pliki, ostrzeżenie o nieokreślonej licencji. Nie wybierano licencji za właściciela. Kontrola typowych wzorców poświadczeń w plikach do commitu nie wykazała trafień; nie jest pełnym audytem bezpieczeństwa.
- Komendy testów: php vendor/bin/phpunit --do-not-cache-result; node --test --test-isolation=none tests/ui/*.test.mjs. Wszystkie testy UI wykonano bez izolowanych procesów w ograniczonym środowisku agenta.
- Poprzedni reset konta testowego wykonano i zweryfikowano na wyraźne polecenie właściciela; sesje unieważniono. Hasła ani hasha nie zapisano w kodzie lub dokumentacji. Rzeczywista baza pozostaje ignorowana.
- Po fetch potwierdzono brak develop na origin i wspólną bazę HEAD/master/origin/master. Utworzono lokalny develop oraz chore/cleanup-pending-changes z develop; develop nie śledzi origin/master. Commit po zielonych testach, potem lokalne scalenie do develop. Push wykonuje właściciel, tylko develop w normalnym trybie. Nie wykonywano deploy.
- Dalszy krok: właściciel wykonuje push develop, następnie CI i PR develop → master zgodnie z GIT-FLOW.md. Prawdziwe połączenia AI, doręczenie poczty i pełny test przeglądarkowy pozostają do weryfikacji. Zmiany Compose/PHP-FPM wymagają odtworzenia kontenerów przez właściciela.


## 2026-10-14 — ochrona danych użytkowników w SQLite

- Osobny USER_DATA_KEY, niezależny od DB_HASH; żadnego rzeczywistego klucza nie odczytywano ani nie zapisywano przez agenta. Compose, PHP-FPM i kontener aplikacji obsługują nową zmienną. Identyczny klucz dostawców jest odrzucany niezależnie od wielkości liter HEX.
- DataCipherInterface i wspólny ProtectedStore: XChaCha20-Poly1305 z losowym nonce oraz powiązaniem szyfrogramu z tabelą, polem, ID rekordu i odpowiednimi relacjami właściciela/rozmowy/roli. Bez jawnego zapisu, także w batch i update.
- Szyfrowane emaile, tytuły rozmów, wiadomości, obrazy oraz tytuły/opisy zadań. HMAC emaila służy wyszukiwaniu i unikalności, z oddzielnie wyprowadzonym kluczem indeksowania. Dotychczasowe rozróżnianie wielkości liter adresów zachowano.
- Tokeny sesji i potwierdzeń emaila zapisywane jako hashe; hashe nie działają jako tokeny do logowania/potwierdzenia. Hashe haseł użytkowników zachowano.
- Transakcyjna i idempotentna migracja zachowuje dane i ID, unieważnia wcześniejsze sesje i weryfikuje klucz znacznikiem migracji. CLI wykonuje secure_delete, checkpoint WAL i VACUUM. Nie usuwa dawnych backupów/snapshotów; pełne wymazanie SSD nie jest gwarantowane.
- Bez klucza, z błędnym kluczem lub bez migracji repozytoria i aplikacja odmawiają pracy; HTTP 503 bez diagnostyki i danych. Brak automatycznej migracji treści przy żądaniu HTTP. Public/index.php nie loguje już wyjątku inicjalizacji bazy. Odpowiedzi API mają Cache-Control: no-store.
- Testy pełne: PHP 74/74, 211 asercji; UI 26/26. Pokrycie m.in. braku jawnych wartości w rekordach, aktualizacji/batch, Unicode/null, podmiany rekordów i właściciela, modyfikacji szyfrogramu, błędnego klucza, wiring DI, idempotencji i rollback. Wyłącznie dane fikcyjne i bazy w pamięci.
- Składnia 26 zmienionych/nowych PHP: OK. Kontrole diff, Composer validate i check-platform-reqs: OK (dotychczasowe ostrzeżenie o braku licencji). Inspekcje PhpStorm dla szyfratora i migratora bez błędów.
- Właściciel potwierdził, że sam skonfiguruje klucz i uruchomi migrację lokalnie. Nie potwierdzono jej wykonania. Obecna baza nie została zmigrowana przez agenta. Instrukcja: docs/OCHRONA_DANYCH.md; polecenie właściciela: dxrun php bin/protect-user-data.php --apply po zatrzymaniu aplikacji. Następnie odtworzyć kontenery, przekazując także USER_DATA_KEY, i zalogować się ponownie.
- To szyfrowanie wybranych pól, nie całego pliku SQLite. Metadane pozostają jawne; serwer i wybrany dostawca AI mają dostęp do treści podczas działania. Bez ochrony przed przejęciem procesu/klucza i bez szyfrowania end-to-end.
