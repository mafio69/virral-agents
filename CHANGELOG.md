# CHANGELOG

## 2026-08-30 — start tasku: multi-AI providers (branch `feature/multi-ai-providers`)

Zaczynam: system wielu dostawców AI (OVH + Z.AI + …) z modelami i dostawcami w SQLite.
Sekrety providerów zaszyfrowane w bazie (sodium secretbox, klucz `DB_HASH` z `.env`).
Fabryka `AiClientFactory` tworzy klientów OpenAI-compatible (`openai-php/client`)
na podstawie rekordu modelu z bazy. Przebudowa `chats.model` (string) → `chats.model_id` (FK).
Endpoint `/api/ai-models` będzie serwował modele z bazy (bez sekretów).

---

## 2026-10-12 — ciemny czat z projektu Design/chat.html

- Nowy widok `/chat`, podłączony do istniejących endpointów czatu i modeli; link ze strony głównej.
- Osobne moduły transportu, stanu rozmowy, Markdown i obsługi widoku. Enter wysyła, Shift+Enter łamie linię.
- Ściąga Markdown zachowuje stan; lokalne Marked 18.1.0 i DOMPurify 3.4.16.
- Blokada podwójnego wysyłania, odzyskiwanie zapisu po błędzie, neutralne komunikaty błędów.
- Testy jednostkowe: 16/16; składnia src/routes.php poprawna. Test wizualny i pełna rozmowa z API nie zostały wykonane.
- Przełączanie dostawców, izolacja wykonawcza i maskowanie sekretów w backendzie pozostają do wdrożenia. Bez commitu i push.

### Archiwum poprzedniego STATUS (deklaracje z 2026-08-30, bez ponownego potwierdzania)

> # STATUS
>
> **Ostatnia aktualizacja:** 2026-08-30
> **Aktualny branch:** `feature/multi-ai-providers` (z `master`)
>
> ## W trakcie
>
> ### Wielu dostawców AI (OVH + Z.AI + ...) — modele w bazie, szyfrowane sekrety
> - Cel: system wielodostawcowy OpenAI-compatible; dostawcy i modele w SQLite,
>   sekrety zaszyfrowane w bazie (klucz `DB_HASH` z `.env`), fabryka klientów AI.
> - Plan szczegółowy: `docs/plan-multi-ai-providers.md`
>
> ## Etapy
> 1. [x] Plan zaakceptowany, branch utworzony, CHANGELOG zainicjowany
> 2. [x] Dokumentacja planu w `docs/`
> 3. [x] Zadania zarejestrowane w DevBrain TODO
> 4. [x] Migracja bazy (ai_urls, ai_providers, ai_models, chats.model_id) + seed
> 5. [x] Moduł szyfrowania (SecretService, sodium, DB_HASH)
> 6. [x] DTO + Repozytoria (AiUrl, AiProvider, AiModel) z soft delete
> 7. [x] AiClientInterface + OpenAiCompatClient (openai-php/client) + AiClientFactory
> 8. [x] ChatService/ChatController na model_id + endpoint /api/ai-models z bazy
> 9. [x] Testy + quality gates (phpunit)
> 10. [x] HANDOFF.md
>
> ## Znane ograniczenia
> - Seed providerów ma puste sekrety (do uzupełnienia przez ownera przez API/SQL —
>   sekret musi być zaszyfrowany SecretService, nigdy plaintext w bazie).
> - Wybór modelu w UI czatu jeszcze nie czyta /api/ai-models (kolejny task).
>

## 2026-10-12 — ściąga Markdown w narożniku

- Przeniesiono przycisk Markdown do lewej strony nagłówka czatu. Ściąga otwiera się jako kafelek wysuwany z lewej; zamyka ją ponowne kliknięcie tytułu.

## 2026-10-12 — wspólny styl istniejących ekranów

- Jedna paleta w public/ui/theme.css, współdzielona przez czat, stronę główną, logowanie/rejestrację i panel /test-api.
- Ciemne powierzchnie, spójne kolory komunikatów, pól i metalizowanych przycisków. Bez nowych widoków i przebudowy układów.
- Sprawdzono dołączenie stylu i zgodność skryptu strony głównej; bez testu wizualnego w przeglądarce.

## 2026-10-12 — gładkie przyciski

- Usunięto pionową teksturę ze wspólnego motywu; przyciski zachowują gładki metaliczny gradient.

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
# 2026-10-14 — inicjalizacja pustej szyfrowanej bazy

## Wylogowanie po stronie serwera

- Naprawiono wylogowanie: backend unieważnia przekazane sesje i wygasza ciasteczko HttpOnly. Wcześniej UI próbowało usuwać je przez JavaScript, pozostawiając aktywną sesję.
- UI raportuje nieudane wylogowanie i obsługuje niedostępny localStorage. Pozostałe sesje konta są zachowane.
- PHP 82/82 (250 asercji), UI 28/28, składnia i kontrole diff/Composer poprawne.

## Lokalny reset hasła

- Interaktywny reset hasła korzysta ze wspólnej konfiguracji HTTP/CLI i fabryki szyfratora; nieszyfrowany lokalny plik środowiska nie wymaga ręcznego eksportowania klucza.
- Zachowano standardowe hashowanie PHP, transakcję i unieważnienie sesji. Ograniczono komunikaty wyjątków konfiguracji i uzupełniono instrukcję.
- PHP 80/80 (240 asercji), UI 26/26, składnia i kontrole diff/Composer poprawne. Nie wykonywano resetu na rzeczywistym koncie.

## Wspólna konfiguracja HTTP i CLI

- Migrator wczytuje nieszyfrowany lokalny plik środowiska przez ten sam src/settings.php co HTTP i używa istniejącej fabryki szyfratora DI. Usunięto powieloną walidację kluczy oraz obowiązek dxrun dla bieżącej instalacji.
- Dodano bezpieczne kody błędów migracji i poprawiono instrukcje uruchamiania. Nie odczytywano rzeczywistych sekretów ani nie wykonywano migracji bazy.
- PHP 80/80 (240 asercji), UI 26/26, składnia trzech zmienionych PHP i kontrole diff/Composer poprawne; dotychczasowe ostrzeżenie o braku licencji.

- Naprawiono start nowej bazy: znacznik ochrony danych jest przygotowywany atomowo tylko przy braku danych użytkowników i sesji. Istniejące dane nie są migrowane przy żądaniu HTTP.
- Dodano bezpieczny kod etapu i przyczyny błędu do logu 503 oraz X-App-Error, a także testy idempotencji, odmowy dla istniejących danych, błędnego klucza, rollback i zamkniętej listy kodów diagnostycznych.
- Poprawiono przykład preserve-env o USER_DATA_KEY. Lokalna odpowiedź dev potwierdziła brak tego klucza w aplikacji; nie odczytywano jego wartości.
- Walidacja: PHP 80/80 (240 asercji), UI 26/26 przed kolejnymi zmianami backendu, składnia sześciu zmienionych PHP poprawna. Bez operacji na rzeczywistej bazie, sekretach, kontenerach i zdalnym repozytorium.
# 2026-10-15 — minimalistyczna strona startowa i konto

## Domyślne Gemini i nazwy konfiguracji właściciela

- Zmienne OPEN_AI_KEY i GEMINI_KEY zastępują wcześniejsze nazwy w kodzie i Compose/FPM.
- Gemini domyślne przy braku zapamiętanego wyboru dostawcy; istniejące rozmowy zachowują powiązanie. PHP 108/108, UI 35/35, składnia i diff-check poprawne.

## Google — osobna integracja

- Gałąź codex/integration-google: Gemini generateContent, katalog konta z paginacją, GEMINI_API_KEY, obsługa obrazów i historii google:... .
- Testy wszystkich trzech dostawców sprawdzają routing i rozdzielenie historii. PHP 108/108 (403 asercje), UI 34/34; bez płatnych wywołań i push.

## Anthropic — osobna integracja

- Gałąź codex/integration-anthropic: natywne Messages API, katalog konta z paginacją i metadanymi obrazów, ANTHROPIC_API_KEY w ustawieniach/Compose/FPM. Historia anthropic:... zachowuje dostawcę.
- System prompt, konwersja obrazów na base64/MIME, tekstowe odpowiedzi i odrzucanie zdalnych obrazów. Bez tools.
- PHP 100/100 (347 asercji), UI 33/33; składnia i diff/Composer poprawne. Testy offline, bez płatnych wywołań i push.

## OpenAI — osobna integracja

- Dodano katalog konta i Responses API z obrazami, store:false oraz limitem odpowiedzi. OPENAI_API_KEY w ustawieniach i Compose/FPM; osobny selektor openai:... zachowuje dostawcę rozmowy.
- Wspólny transport i rejestr przez interfejsy, neutralne błędy dostawcy i obsługa nowych dostawców w selektorze historii.
- Gałąź codex/integration-openai; PHP 94/94 (316 asercji), UI 32/32, składnia i diff/Composer poprawne. Bez płatnych zapytań i push.

## Połączenie Z.ai z lokalnego środowiska

- Naprawiono brak połączenia dla ZAI_API_KEY: konfiguracja HTTP i DI używa klucza środowiska do standardowego API Z.ai. Compose/FPM przekazują tę zmienną.
- Selekcje zai:... działają bez tworzenia dostawcy w bazie; istniejące db:... zachowują powiązania. Usunięcie konfiguracji blokuje wysyłkę, a nie zmienia dostawcy.
- PHP 87/87 (270 asercji), UI 31/31; składnia i diff/Composer poprawne. Katalog runtime: 8 skonfigurowanych modeli. Właściciel potwierdził działanie; agent nie wysłał rzeczywistej wiadomości testowej.

- Dodano możliwość obejrzenia tego samego formularza w jasnej i ciemnej kolorystyce przez przełącznik w nagłówku, bez utrwalania preferencji. Układ i dane formularza pozostają zachowane; czat bez zmian. PHP 82/82, UI 31/31; podglądy obu wersji w artifacts/account-compare-*-2026-10-15.jpg.

- Białe tło, tekstowe akcje bez dekoracyjnych przycisków i ikon; pola z cienką dolną linią. Zmiana ograniczona do landing.html, bez zmiany czatu i panelu API.
- Natywny submit i Enter dla logowania/rejestracji, walidacja przeglądarki oraz powiązane etykiety i autocomplete.
- PHP 82/82 (250 asercji), UI 30/30; kontrole diff/Composer poprawne. Rzeczywista weryfikacja przeglądarkowa wyglądu i Enter na fikcyjnych danych.
