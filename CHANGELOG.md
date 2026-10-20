# CHANGELOG

## 2026-10-17 — audyt S3: panel testowy tylko w development

- S2 `de0bf00` scalony lokalnie fast-forward do develop; nowa gałąź `codex/audit-s3-dev-test-api`.
- `/test-api` rejestrowane wyłącznie dla jawnego `environment=dev`; domyślnie `prod`. APP_ENV przekazywane przez Compose/PHP-FPM, bez odczytu lub zmiany plików środowiska i bez odtworzenia kontenerów.
- Szablon przeniesiony z public/ui/index.html do resources/views/test-api.html, aby adres pliku statycznego nie obchodził blokady. Dodane testy prod/dev/braku/nieznanego środowiska i dawnych ścieżek.
- Właściciel doprecyzował cykl: każdy punkt ma pełne testy, commit, lokalne scalenie do develop, push wyłącznie develop i usunięcie zakończonej gałęzi po udanym push. Pełna walidacja S3: PHP 164/164 (717 asercji), UI 57/57; Composer strict/no-check-publish, wymagania platformy i diff poprawne. Testy izolowane, bez sekretów, rzeczywistej bazy i odtworzenia kontenerów.
- S3 przygotowane do commitu i lokalnego scalenia; następnie push develop obejmujący S1–S3 i usunięcie trzech lokalnych gałęzi audytu. Kolejny punkt: A1 (wspólne AuthMiddleware). Nie modyfikować ani nie odczytywać chronionych plików. Zastosowanie ustawień Compose/FPM należy do operatora.


## 2026-10-17 — audyt S2: obowiązkowe potwierdzenie e-maila

- Gałąź `codex/audit-s2-email-confirmation` z lokalnego develop po scaleniu przetestowanego S1 (`d2b7c63`); bez push.
- AuthService po sprawdzeniu hasła wymaga email_confirmed_at. Niepotwierdzone konto otrzymuje 403 / email_confirmation_required / no-store bez nowej sesji, ciasteczka i identyfikatorów w odpowiedzi. Błędne hasło pozostaje 401, a limity S1 obowiązują również przy odmowie potwierdzenia.
- Walidacja sesji także sprawdza potwierdzenie, więc wcześniejsze sesje nie obchodzą wymogu; wylogowanie pozostaje dostępne. Nie zmieniano potwierdzeń ani sesji w rzeczywistej bazie.
- Istniejący link potwierdzający odblokowuje konto; ponowne użycie i niepoprawny link zwracają 422. Formularz wyjaśnia 403 oraz brak możliwości logowania po niedostarczeniu poczty. Nie dodano ponownej wysyłki linku ani automatycznego zatwierdzania starszych kont.
- Pełne testy: PHP 156/156 (694 asercje), UI 57/57; Composer strict/no-check-publish, wymagania platformy i diff poprawne. Testy na fikcyjnych kontach, poczcie atrapowej i SQLite w pamięci. Rzeczywiste doręczenie poczty niezweryfikowane; bez sekretów, migracji i operacji na rzeczywistych kontach.
- Kolejny punkt audytu: S3 (ograniczenie /test-api do development). S2 pozostaje lokalnym commitem na osobnej gałęzi; bez scalenia do develop i bez push.


## 2026-10-17 — audyt S1: limit logowania

- Gałąź `codex/audit-s1-login-limits`, utworzona z aktualnego `origin/develop`.
- Limit 5 nieudanych prób / 15 minut osobno na IP i e-mail; szósta próba zwraca 429, Retry-After i no-store. Udane logowanie usuwa tylko własną rezerwację, nie kasuje wcześniejszych niepowodzeń.
- Wspólna tabela SQLite i atomowa rezerwacja przed weryfikacją hasła chronią przed równoległymi próbami. Przeterminowane wpisy są usuwane przy kolejnym logowaniu. E-mail i IP zapisane wyłącznie jako HMAC z oddzielnymi prefiksami, bez haseł, sesji i jawnych identyfikatorów.
- IP wyłącznie z REMOTE_ADDR; brak zaufania do X-Forwarded-For. Za reverse proxy limit dotyczy widocznego adresu peer, dopóki operator nie skonfiguruje zaufanego przekazywania IP. Brak IP używa wspólnego zachowawczego limitu.
- Rejestracja wymaga minimum 10 znaków, także Unicode; formularz pokazuje wymaganie i osobny komunikat blokady logowania. Hasła istniejących kont nie są zmieniane.
- Walidacja: PHP 153/153 (656 asercji), UI 55/55; Composer strict/no-check-publish, wymagania platformy i diff poprawne. Testy tylko na danych fikcyjnych i SQLite w pamięci, bez rzeczywistej bazy, poczty i sekretów. Nową tabelę utworzy istniejący mechanizm migracji przy uruchomieniu; agent nie wykonał migracji na instalacji.
- Bez push i scalenia; S2 (obowiązkowe potwierdzanie e-maila) jest kolejnym osobnym zadaniem. Cudze nieśledzone pliki i katalog chroniony pozostawiono nietknięte.


## 2026-10-17 — podsumowanie zakończonego etapu źródeł

- `2e48daf`: wybór stron checkboxami, trwały koszyk i opracowanie wybranych treści z uprawnieniami ASK.
- `4daae48`: Fetch all, jedna jawna zgoda na wyświetloną listę adresów.
- `fef7463`: nieudany fetch nie zatrzymuje pozostałych pobrań ani oczekującego polecenia.
- `998916e`: MIT w composer.json i LICENSE; ścisła walidacja Composer przechodzi bez ostrzeżenia licencji.
- Weryfikacja lokalna: PHP 139/139, UI 54/54. Nowy wynik GitHub Actions i rzeczywiste opracowanie po poprawce fetch pozostają niepotwierdzone.


## 2026-10-17 — licencja MIT i walidacja CI

Właściciel wybrał MIT. Dodano deklarację w composer.json i pełny tekst LICENSE. Walidacja musi używać `composer validate --strict --no-check-publish`, jak GitHub Actions; poprzednia kontrola bez --strict przeoczyła brak licencji. Pełne testy PHP 139/139 (601 asercji), UI 54/54, wymagania platformy i diff poprawne. Bez push; lokalne PHP 8.5, wersja 8.4 wymaga ponownego CI.

## 2026-10-17 — kontynuacja po błędzie strony

Błąd pobrania strony nie zatrzymuje Fetch all ani oczekującego polecenia. AI otrzymuje dostępne treści i status failed dla niepobranych stron. Niepewny stan po utracie połączenia z aplikacją nadal wymaga odświeżenia, bez automatycznego ponawiania. Testy regresji: pojedynczy nieudany fetch i błąd pierwszej strony paczki; UI 54/54, PHP 139/139 (601 asercji). Bez rzeczywistego WWW i push.

## 2026-10-17 — Fetch all

Przy oczekujących pobraniach dodano Fetch all z podglądem dokładnej listy i jednym jawnym Allow dla tej listy. Bez zgody nic nie pobiera; nie obejmuje nowych stron ani wyszukiwania, zatrzymuje się po błędzie. Testy UI 53/53; PHP 139/139 (601 asercji), Composer i składnia poprawne. Bez rzeczywistego WWW i push.

## 2026-10-17 — wybór źródeł do badania

Checkboxy wyników i szyfrowany koszyk zachowują wybór między wyszukiwaniami oraz po odświeżeniu. Wyszukiwanie nie wymaga wiadomości. Pobranie każdej strony oraz prośby AI wymagają osobnego Allow / Deny. Opracowanie otrzymuje treść wyłącznie wybranych i pobranych stron; jawne odmowy, błędy i skrócenia. HTML/tekst publicznych stron, bez PDF/JS/przekierowań.

## 2026-10-17 — jawna diagnostyka błędów MCP

- `status: unavailable` zachowuje bezpieczną przyczynę i etap zamiast ukrywać szczegóły każdego błędu. Dodano komunikaty o DNS/połączeniu i logowanie wyłącznie dozwolonych kodów.
- Chroniony test połączenia MCP z procesu aplikacji nie wykonuje wyszukiwania. Domyślny adres backendu odpowiada środowisku Docker zamiast wskazywać loopback kontenera.
- Potwierdzono połączenie z procesu PHP po dołączeniu aplikacji do wspólnej sieci `ai`: inicjalizacja i narzędzie `web_search` dostępne. Bez powtarzania wyszukania WWW.

## 2026-10-16 — piesek podczas wyszukiwania

- Dodano lokalną animację SVG i tekst statusu dla trwającego wyszukiwania/odpowiedzi. Piesek zatrzymuje się po zakończeniu lub błędzie; ograniczenie ruchu jest respektowane.

## 2026-10-16 — zgoda WWW bez natywnego popupu

- „Wyszukaj i wyślij…” pokazuje panel Allow / Deny w stronie. Usunięto zależność od `window.confirm`, którego brak powodował zakończenie handlera bez żądania.
- Odmowa jest widoczna w statusie; równoczesne kliknięcia i zgody dla zmienionej rozmowy nie uruchamiają wyszukiwania. Testy UI 46/46 oraz izolowany test w przeglądarce z faktycznymi kliknięciami panelu.

## 2026-10-16 — naprawa zablokowanego przycisku WWW

- Zapytanie w polu WWW wystarcza do odblokowania „Wyszukaj i wyślij…”, bez dodatkowej wiadomości. Po zgodzie jest używane jako polecenie dla modelu; osobno wpisane polecenie zachowuje pierwszeństwo.
- Testy regresji sprawdzają zdarzenia kontrolek, odmowę bez wysyłki, pojedyncze wysłanie oraz zachowanie blokad sesji/modelu. Wersje importów JS zmienione, aby przeglądarka pobrała poprawiony kod.

## 2026-10-16 — wyszukiwarka MCP w czacie, tryb ASK

- Wspólny serwis odpowiedzi łączy zatwierdzone wyszukiwanie MCP z wybranym dostawcą AI; wyniki nie wymagają natywnego function calling danego dostawcy.
- Osobne publiczne zapytanie i zgoda na każdą operację WWW. Zwykłe wysłanie wiadomości nie korzysta z wyszukiwarki; fetch nie jest udostępniany.
- Obsługa sesji MCP, JSON/SSE, walidacji wyników, niedostępności i pustych odpowiedzi; źródła zachowane w szyfrowanej historii.
- Konfiguracja DI/Compose/FPM, diagnostyka bez sekretów, testy protokołu i przepływu do trzech natywnych API. Po uruchomieniu kontenera potwierdzono rzeczywiste `web_search`: 3 wyniki SearXNG dla zatwierdzonego zapytania, bez fetch i Perplexity. Pełnego czatu z rzeczywistym API modelu nie wywoływano.

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

## Obrazy generowane przez Gemini

- Dodano obsługę odpowiedzi tekst/obraz, walidację grafiki, szyfrowany zapis i podgląd z pobieraniem w historii czatu.
- Modele generujące obrazy są oznaczone w katalogu; dotychczasowi dostawcy zachowują kontrakt tekstowy. Bez nowych zależności i migracji bazy.

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

## 2026-10-17 — lokalna skrzynka Mailpit do potwierdzania kont

- Dodano profil dev z oficjalnym obrazem axllent/mailpit:v1.31.6, HTTP 127.0.0.1:8025 i SMTP 127.0.0.1:1025, limit 100 wiadomości, bez trwałego wolumenu i bez konfiguracji relay/forward.
- docker-compose.dev.yml przełącza app na APP_ENV=dev i smtp://mailpit:1025; wiadomości z linkiem potwierdzającym ogląda się lokalnie i odklikuje w przeglądarce. Bazowy Compose zachowuje dotychczasowy transport; samego profilu dev nie utożsamiać z przełączeniem SMTP.
- Testy PHP 164/164 (717 asercji), UI 57/57, Composer strict/no-check-publish i wymagania platformy poprawne. Oba modele Compose sprawdzone z wyłączonym wczytywaniem plików środowiska i izolowanym środowiskiem procesu, bez poświadczeń. Poprzedni test MailService na lokalnej atrapie SMTP potwierdził wysyłkę i obsługę odmowy; nie stanowi testu działającego Mailpita.
- Próba uruchomienia samej usługi mailpit nie powiodła się: brak uprawnień do Docker API. Nie używano sudo, nie pobrano/uruchomiono obrazu ani nie restartowano aplikacji. Właściciel uruchamia komendę z docs/KONFIGURACJA.md; pełny test rejestracja → Mailpit → kliknięcie → logowanie pozostaje do wykonania przez właściciela.
- Nie odczytywano ani nie zmieniano sekretów, prawdziwej bazy i plików środowiska. Lokalna cudza zmiana GIT-FLOW.md nie należy do tego commitu. Po zielonej walidacji: commit, scalenie do develop, push wyłącznie develop i usunięcie gałęzi zgodnie z poleceniem właściciela.

## 2026-10-17 — jeden skrypt startowy dev/prod z sudo

- dev-start.sh [dev|prod] [--dry-run] w głównym katalogu repo; bez argumentu tylko APP_ENV=dev procesu wybiera dev, inaczej prod. Skrypt nie odczytuje plików środowiska; dla wartości zapisanej wyłącznie w takim pliku właściciel podaje jawny argument.
- Wszystkie wywołania Docker mają sudo. Dev wybiera override z Mailpitem; nowy docker-compose.prod.yml wymusza APP_ENV=prod. W prod po starcie zatrzymywana jest sama skrzynka Mailpit.
- Najpierw start app, w dev także mailpit; potem Nginx uruchamiany bez odtwarzania i bez dotykania zależności. Reload odświeża upstream po zmianie app. Nieudany start app przerywa dalsze kroki; brak down i kasowania danych.
- Walidacja: PHP 164/164 (717 asercji), JS/skrypt 62/62, bash -n, oba modele Compose, Composer strict/no-check-publish, platforma i diff poprawne. Compose sprawdzony z wyłączonym wczytywaniem plików środowiska i bez danych dostępowych. Testy uruchamiania używają wyłącznie atrapy sudo; skryptu nie wykonano na działających usługach.
- Cudza zmiana GIT-FLOW.md pozostaje nietknięta i poza commitem. Sekretów nie czytano ani nie zmieniano. Procedura właściciela: ./dev-start.sh dev albo ./dev-start.sh prod. Po testach commit, lokalne scalenie, push wyłącznie develop i usunięcie gałęzi.

## 2026-10-18 — kontynuacja z domyślnie wybranymi wynikami

- Dodano widoczny przycisk „Kontynuuj z wybranymi stronami”. Na jawne polecenie właściciela kliknięcie pobiera zaznaczoną listę bez dodatkowych pytań fetch i przekazuje treści do AI. Wyszukiwanie nadal wymaga Allow/Deny; sam checkbox nie uruchamia sieci.
- Wyniki domyślnie wybrane do limitu 20; odznaczone adresy nie są pobierane ani ponownie automatycznie zaznaczane (ostatnich 80 wykluczeń). Własne polecenie pozostaje w użyciu; przy pustym polu aplikacja sama dodaje kontynuację poprzedniego zadania lub opracowanie nowych źródeł.
- Testy: PHP 166/166 (731 asercji), UI/skrypt 69/69; Composer strict/no-check-publish, platforma i diff poprawne. Pokrycie: wybór po search, wykluczenia/limit, fetch bez dodatkowej zgody po kliknięciu, pominięcie odznaczonych, utrata odpowiedzi, zmiana rozmowy i podwójne kliknięcie. Dane fikcyjne, bez rzeczywistego WWW i płatnego AI.
- Bez odczytu sekretów i rzeczywistej historii. Cudza zmiana GIT-FLOW.md zachowana poza commitem. Wersje URL modułów JS odświeżone. Po walidacji commit, scalenie do develop, push wyłącznie develop i usunięcie gałęzi.

## 2026-10-18 — widoczna blokada kontynuacji do zakończenia żądania AI

- ResearchPanel utrzymuje blokadę podczas fetch i całego oczekiwania na onContinue. Przycisk ma disabled, aria-busy oraz opisy „Pobieram wybrane strony…” / „AI przygotowuje odpowiedź…”, zachowane również po odrysowaniu panelu.
- Osobny stan kontynuacji blokuje edycję wiadomości, wysyłkę i zmianę modelu/rozmowy również pomiędzy kolejnymi operacjami źródeł. Nie dodano pytań o fetch ani ponawiania żądań.
- Pełna walidacja: PHP 166/166 (731 asercji), UI/skrypt 70/70, składnia JS, Composer strict/no-check-publish, platforma i diff poprawne. Test kontroluje opóźniony fetch i odpowiedź AI, odrysowanie i kolejne kliknięcia — tylko jedna wysyłka. Bez rzeczywistego WWW lub dostawcy AI.
- Wcześniej zgłoszony błąd „Nie udało się odebrać odpowiedzi” po kontynuacji pozostaje niezdiagnozowany: potwierdzono tylko moment wystąpienia, brak nazwy modelu i statusu HTTP. Nie twierdzić, że ta poprawka usuwa jego przyczynę. Cudza zmiana GIT-FLOW.md nietknięta; bez odczytu sekretów.

## 2026-10-18 — źródła w niezależnym modalu

- Wyniki i źródła przeniesiono poza układ czatu do natywnego dialogu. Przewija się jego treść, nagłówek i pojedynczy przycisk „Kontynuuj” pozostają widoczne. Zamknięcie, Escape i kontynuacja nie usuwają źródeł; boczna lista pozwala je przywołać.
- Kontynuacja zamyka modal i zachowuje blokadę podczas fetch oraz oczekiwania na AI. Pobiera tylko zaznaczone strony, ponownie używa zapisanych tekstów. Backend przekazuje JSON z tytułem, URL, statusem, tekstem, czasem pobrania i informacją o skróceniu; treści stron traktuje jako niezaufane dane.
- Zachowano istniejący szyfrowany magazyn wyników i pobranych tekstów per użytkownik/rozmowa, bez jawnego localStorage. Po reload należy otworzyć tę samą rozmowę z historii. Kolejne wyszukiwanie zachowuje wcześniejsze wybrane źródła; nie jest archiwum wszystkich niewybranych wyników.
- Walidacja: PHP 166/166 (731 asercji), UI/skrypt 73/73, Composer strict/platform, składnia JS/PHP i diff poprawne. Ręcznie w przeglądarce na syntetycznym routerze: 4 wyniki → odznaczenie 1 → kontynuacja z 3 → zamknięcie modalu, długa odpowiedź i przewijanie góra/dół, reload i odtworzenie 3 pobranych treści, przywołanie z boku, Escape, drugie wyszukanie z 2 nowymi i 3 zachowanymi źródłami. Dowody: artifacts/research-modal-2026-10-18.png i artifacts/research-chat-layout-2026-10-18.png. Bez rzeczywistego WWW/AI i prywatnej historii. Serwer atrapowy wymagał ponownego uruchomienia podczas testu kolejnego wyszukania; po wznowieniu przepływ poprawny.
- Wcześniejszy zgłoszony błąd odbioru odpowiedzi z rzeczywistego modelu pozostaje niezdiagnozowany. Nie przypisywać tej zmianie naprawy jego przyczyny. Cudza zmiana GIT-FLOW.md poza zakresem i commitem. Push wyłącznie develop po zielonej walidacji.


## 2026-10-18 — audyt A1–A5 i kontrakt MCP, praca w toku

- Gałąź docs/audyt-2026-10-17 uaktualniona zatwierdzonym rebase na develop; S1–S3 już zawarte. A1: wspólny resolver sesji i AuthMiddleware/SessionAuthenticatorInterface, jedna walidacja dla prywatnych tras. A2–A4: ModelCatalogInterface/Service, wymagane zależności ChatService, ModelSelector.
- A5: bin/migrate.php, DatabaseInitializer i start-app.sh przed FPM; HTTP wyłącznie assertReady. Testy na SQLite w pamięci i atrapach procesu potwierdzają idempotencję, zachowanie istniejących jawnych danych i zatrzymanie startu po błędzie. Nie uruchamiano rzeczywistej migracji ani kontenerów.
- K1: jawne empty/null/[] akceptowane, błędy dostawcy nadal odrzucane. K3: lista wersji w stałej i testy negocjacji; zgodność z przypiętą biblioteką drugiego repo pozostaje niezweryfikowana.
- Wynik: PHP 173/173 (786 asercji), UI/skrypty 75/75; Composer strict/no-check-publish i platforma poprawne, składnia 48 PHP i skryptu startowego, diff-check poprawne. Bez WWW/API modeli i sekretów.
- A6 w toku: konfiguracja php-cs-fixer.php i żądana zasada czytelności w AGENTS.md przygotowane; oczekiwanie na odpowiedź właściciela o dodaniu PHP CS Fixer jako zależności i pobraniu pakietów. Formatter nie został uruchomiony. Brak commitu/scalenia/push. Po A6 ponownie pełne wymagane kontrole; scalenie do develop dopiero na koniec, push wyłącznie develop i osobno zatwierdzony. P1 przez PR na GitHub po zielonych kontrolach, bez lokalnego merge do master.


## 2026-10-18 — zamknięcie lokalnych poprawek audytu A1–A6

- A6: za zgodą właściciela dodano friendsofphp/php-cs-fixer ^3.95 (lock 3.95.27), bez aktualizowania dotychczasowych pakietów. Konfiguracja php-cs-fixer.php obejmuje warstwy objęte audytem, bez sekretów, ustawień środowiska, zależności i cache. PSR-12 oraz wymagane klamry; sformatowano 19 plików. Dodano composer lint, format, test-ui i check-all; polecenia opisano w README.
- Końcowe composer check-all: formatter 0/46 plików do poprawy, PHP 173/173 (786 asercji), UI/skrypty 75/75. Composer strict/no-check-publish, platforma i diff poprawne. Lokalnie PHP 8.5.4; sprawdzenie na PHP 8.4 pozostaje dla CI. Składnia PHP i shell poprawna w poprzedniej kontroli; formatowanie używa nierozszerzających składni reguł PSR-12.
- A1–A6 i klient K1 gotowe lokalnie. K3 ma testy negocjacji obsługiwanych wersji, ale przypiętej biblioteki i wspólnego JSON drugiego repo nie zweryfikowano. A5 sprawdzono na fikcyjnych bazach i atrapach procesów; rzeczywisty start kontenera i migracja instalacji pozostają po stronie operatora.
- Etap przeznaczony do commitu i końcowego lokalnego scalenia do develop po zielonych kontrolach. Gałąź audytu zachować. Bez push i operacji na master; P1 wymaga osobnego procesu PR na GitHub. Nie odczytywano plików środowiska ani sekretów.


## 2026-10-18 — przekazane potwierdzenie kontraktu searxng

Właściciel przekazał syntetyczne odpowiedzi serwera: poprawny brak wyników ma status empty, source null, results [] i errors []; awaria ma status error, source null, results [] i błędy searxng_timeout/perplexity_no_key. Klient przyjmuje pierwszy wariant i odrzuca drugi; dokładny wariant awarii dodano do testu pełnej sekwencji MCP bez ponawiania. Dokładny wariant empty jest już objęty istniejącym testem.

Według raportu przekazanego przez właściciela serwer przeszedł 3/3 testy kontraktu przez pełne sesje MCP, Ruff, 46 testów backendu, 18 frontendu, build i kontrole Git. Raport po stronie drugiego repo: docs/contract/MCP-COMPATIBILITY.md. Agent tego pliku nie odczytywał i nie wykonywał tam testów. To potwierdzenie kodu na syntetycznych danych, bez rzeczywistych wyszukiwań i bez potwierdzenia działającego wdrożenia. W przekazanym fragmencie brakuje przypiętej wersji biblioteki MCP i protocolVersion zwracanego przy initialize, więc K3 pozostaje częściowo otwarte. Lokalna walidacja po dodaniu przekazanej awarii: composer check-all — formatter czysty, PHP 173/173 (788 asercji), UI/skrypty 75/75; Composer strict/platform i diff-check poprawne.


## 2026-10-18 — K3: zgodność wersji MCP potwierdzona

Właściciel przekazał potwierdzenie drugiego repo: `mcp==1.30.0` przypięte w `mcp/requirements.txt`. Testy rzeczywistej lokalnej inicjalizacji w `tests/test_mcp_protocol.py` potwierdzają negocjację: żądane `2024-11-05`, `2025-03-26`, `2025-06-18` i `2025-11-25` są zwracane bez zmiany; żądanie nieobsługiwanej wersji otrzymuje `2025-11-25`. Wszystkie te odpowiedzi akceptuje klient WirralAI. Agent otrzymał wyniki od właściciela, bez odczytu drugiego repo i bez wykonywania tam testów.

K3 zamknięte na poziomie zgodności kodu i przekazanych testów serwera; K1 ma zgodne syntetyczne przykłady pustego wyniku i awarii. Nie potwierdzono działającego wdrożenia ani rzeczywistego wyszukania. P1 nadal wymaga procesu PR develop → master na GitHub z zielonymi kontrolami; bez push lub operacji na master przez agenta.


## 2026-10-19 — porządek dokumentacji projektu

- `HANDOFF.md` wyzerowany do zasad prowadzenia, szablonu i jednej bieżącej pałeczki (gałąź `codex/chat-retry-timeout` z niezacommitowanym ponawianiem odpowiedzi, timeoutem i E2E). Dotychczasowe wpisy były duplikatem historii zachowanej w tym pliku.
- `STATUS.md` przepisany jako stan na teraz: gotowe funkcje, otwarte/niepotwierdzone punkty, kolejne priorytety. Historia sesji pozostaje wyłącznie w CHANGELOG.
- `REPO.md`: wypełnione sekcje 1–6 (przeznaczenie, uruchamianie, architektura, mechanizmy krytyczne, decyzje i pułapki, stałe operacyjne); dotychczasowe sekcje tematyczne bez zmian.
- `README.md` uaktualniony: pięciu dostawców AI, wyszukiwanie i badanie źródeł, pełna lista endpointów (w tym logout, verify-email, retry, research), aktualna struktura, polecenia jakości i mapa dokumentacji.
- Zmiana wyłącznie dokumentacji; bez zmian kodu, testów, sekretów i bez commitu.


## 2026-10-19 — DT 01: ponawianie odpowiedzi, wspólny timeout i lokalne E2E

- `POST /api/chats/{id}/messages/{messageId}/retry` ponawia odpowiedź na ostatnią wiadomość bez odpowiedzi; stan prób w `chat_reply_attempts` (`running/failed/done`), powtórzone ponowienie z utraconą odpowiedzią zwraca istniejącą parę, zgoda na wyszukanie nie jest używana ponownie. UI: przycisk „Ponów”, rozpoznanie trwającej odpowiedzi po reload, szczegół błędu dostarczenia (HTTP/format/połączenie).
- Jeden budżet 300 s dla wszystkich dostawców: `config/app.php` (OVH), `OpenAiCompatClient` i `ProviderTransport` (Gemini/OpenAI/Anthropic, wcześniej na sztywno 120 s). cURL 28 oraz HTTP 408/504 dają `provider_timeout`, bez treści transportu. Nginx `fastcgi_read_timeout 360s`.
- Lokalne E2E Playwright (`tests/e2e`, `docs/E2E.md`, `package.json`): konto z potwierdzeniem przez Mailpit, czat przez natywny transport, badanie źródeł z częściowym błędem fetch, puste wyniki i awaria MCP.
- Walidacja: `composer check-all` — formatter 0/46, PHP 179/179 (822 asercje), UI 81/81; `npm run test:e2e` 4/4. Migracja `chat_reply_attempts` na rzeczywistej instalacji nie była wykonywana przez agenta.
- Dokumentacja: odnośniki do usuniętego przez właściciela `TODO-zrodla.md` zastąpione listą DevBrain-Todo „virralAI” (zadania 01–25). Usunięte `MCP-INSTRUKCJA-DLA-AI.md` i `TASKS.md`; dodane instrukcje DT i MCP Search w `docs/`.


## 2026-10-19 — DT 02: CI sprawdza develop, formatter i testy UI

- `.github/workflows/test.yml` uruchamia się dla push i PR na `develop`, `main`, `master` (wcześniej tylko `main/master`).
- Kroki jak lokalne `composer check-all`: `composer validate --strict --no-check-publish`, `check-platform-reqs`, `composer lint`, PHPUnit oraz testy UI na Node 22. PHP 8.4 z `pdo_sqlite` i `sodium`. E2E pozostaje lokalne (wymaga Mailpita).
- Lokalnie: formatter 0/46, PHP 179/179 (822 asercje), UI 81/81. Wynik na GitHub Actions będzie znany po push `develop`.


## 2026-10-19 — #62-482: sprzątanie repozytorium

- Usunięte nieużywane pliki: katalogi notatek agentów `.devin/` i `.junie/przykazaniaAi.md`, skrypt `przyklad_szyfrowania_deszyfrowania_używania.php`, szkic `src/Infrastructure/AI/GroundedClientInterface.php` (brak odwołań w `src/` i `tests/`).
- `STATUS.md` i `HANDOFF.md`: P1 zamknięte (master zaktualizowany przez PR właściciela), numeracja zadań jako `#62-<id>` z DevBrain-Todo.
- Walidacja: formatter 0/46, PHP 179/179 (822 asercje), UI 81/81. Trzy stare stashe pozostają do usunięcia przez właściciela.


## 2026-10-19 — poprawki z przeglądu Copilota (PR #5) i diagnostyka migratora

- #62-505 (High): `AuthController` otrzymuje środowisko z `settings['environment']` (domyślnie `prod`) zamiast `$_ENV['APP_ENV']` z domyślnym `dev`. Ciasteczko sesji bez `Secure` tylko przy jawnym `dev` na HTTP. Test: prod przez HTTP ustawia `SameSite=Strict; Secure`.
- #62-506: komunikat blokady logowania pokazuje czas z nagłówka `Retry-After` (s/min) albo „później”, zamiast stałych 15 minut.
- #62-507: nazwa projektu „WirralAI” w nagłówku `TODO-zrodla.md`.
- #62-508: jeden neutralny komunikat rejestracji na istniejący adres, bez ujawniania stanu potwierdzenia konta.
- `bin/migrate.php` podaje bezpieczną przyczynę błędu: kod z zamkniętej listy ochrony danych albo nazwę klasy wyjątku, bez treści i wartości. Pozwoliło to zdiagnozować 502 (`InvalidFileException` — błąd składni w pliku środowiska, poprawiony przez właściciela).
- Walidacja: formatter 0/46, PHP 180/180 (826 asercji), UI 81/81.


## 2026-10-19 — #62-483: kody przyczyn nieudanego pobrania strony

- `PublicWebPageFetcher` rzuca `SearchException` z kodem z zamkniętej listy `FETCH_REASONS` (etap `fetch`): `dns_failed`, `private_address`, `timeout`, `connection_failed`, `http_blocked`, `http_not_found`, `http_error`, `redirect`, `unsupported_type`, `unsupported_encoding`, `too_large`, `charset_error`, `empty_content`. Bez treści odpowiedzi serwera i komunikatów transportu.
- `ResearchService` zapisuje kod w `page.reason`; evidence dla modelu ma pole `reason` przy statusie `failed`, a instrukcja systemowa trybu badania każe nie zgadywać treści i zaproponować inne źródło.
- UI badania pokazuje czytelny opis przyczyny (np. „strona odmówiła dostępu”, „brak tekstu (strona wymaga JavaScript)”).
- Testy: każdy kod poza `charset_error` (iconv z `//IGNORE` praktycznie nie zwraca błędu) na atrapach HTTP, zapis i evidence w `ResearchServiceTest`, etykiety UI. Formatter 0/46, PHP 182/182 (862 asercje), UI 82/82.


## 2026-10-19 — dokumentacja po #62-483

- `STATUS.md`: aktualne wyniki (PHP 182/182, UI 82/82), commity czekające na push, zmiany konta z #62-505/506/508, kody przyczyn fetch, następny priorytet #62-484.
- `HANDOFF.md`: nowa pałeczka — start od #62-484, pułapka z nazwami zmiennych w pliku środowiska (przyczyna 502).
- `REPO.md`, `docs/MCP-USAGE.md`: kod przyczyny nieudanego fetch w stanie, evidence i panelu źródeł.


## 2026-10-19 — CI: Node 24 dla testów UI

- GitHub Actions używał Node 22, który nie zna flagi `--test-isolation=none` (`composer test-ui` kończył się kodem 9). `setup-node` podniesiony do 24, zgodnie z lokalnym środowiskiem.


## 2026-10-19 — #62-509: nazwa repo i produktu Virral Agents

- Repo GitHub: `mafio69/virral-agents` (remote `origin` zaktualizowany).
- Nazwa produktu „Virral Agents” w UI (landing, czat, panel API), dokumentacji i projektach w `Design/`; `package.json` `virral-agents-e2e`, `clientInfo` MCP `virral-agents`, User-Agent fetchera `VirralAgents-Research/1.0`, nadawca poczty `noreply@virral-agents.local`, kontener Mailpit w `docs/E2E.md`.
- Bez zmian celowo: stałe kontekstu szyfrowania `virralAI/user-data/v1` i `virralAI/email-index/v1` (zmiana uniemożliwiłaby odczyt danych), historia w CHANGELOG, nazwa listy DT „virralAI”.
- Walidacja: formatter 0/46, PHP 182/182 (862 asercje), UI 82/82.

## 2026-10-19 — #62-484: snippet i tytuł wyniku zawsze w evidence

- `ResearchService::evidence()` dodaje do każdego źródła `snippet` z wyszukiwarki (≤ 400 znaków) i przycina `title` do 300 znaków, także przy statusach `failed`/`denied`; fetch bez wyszukania ma pusty snippet.
- Prompt badania: `title`/`snippet` pochodzą z wyszukiwarki i nie są przeczytaną treścią strony.
- Dokumentacja: `REPO.md`, `docs/MCP-USAGE.md`.
- Walidacja: formatter 0/46, PHP 183/183 (872 asercje), UI 82/82.

## 2026-10-19 — #62-485: bezpieczne przekierowania w fetcherze

- `PublicWebPageFetcher` śledzi maks. 3 przekierowania (301/302/303/307/308), także na inny host. Każdy hop: `PublicWebUrl::normalize`, świeże DNS, kontrola publicznego IPv4, połączenie przypięte do IP, nagłówki budowane od zera (bez cookies i `Authorization`). Guzzle nadal z `allow_redirects=false`.
- Nowe kody w `FETCH_REASONS`: `too_many_redirects`, `redirect_loop`, `insecure_redirect` (https→http); `redirect` oznacza teraz brak lub nieprawidłowy `Location`. Etykiety w panelu źródeł.
- Jeden limit 15 s na cały łańcuch przekierowań; wynik zawiera końcowy adres.
- Dokumentacja: `REPO.md`, `docs/MCP-USAGE.md`.
- Walidacja: formatter 0/46, PHP 185/185 (905 asercji), UI 82/82.

## 2026-10-19 — Poprawki z inspekcji Qodana

- Dodany `qodana.yaml` (profil `qodana.starter`, PHP 8.4, linter `jetbrains/qodana-php:2026.2`).
- `ConnectionFactory`: tworzenie katalogu bazy odporne na wyścig (`!mkdir(...) && !is_dir(...)`), czytelny wyjątek, gdy katalogu nie da się utworzyć.
- `SearchOptions::fromInput()` i `PublicWebUrl::normalize()`: jawne nawiasy przy mieszaniu `&&` i `||` (bez zmiany logiki).
- Walidacja: formatter 0/46, PHP 185/185 (905 asercji), UI 82/82.

## 2026-10-19 — Testy obsługi błędów dla poprawek Qodana

- `ConnectionFactoryTest`: tworzenie zagnieżdżonego katalogu bazy, ponowne użycie istniejącego, błąd utworzenia (plik w miejscu katalogu) z ogólnym komunikatem bez ścieżki.
- `ChatSearchTest`: `SearchOptions::fromInput()` odrzuca tekst, `null`, liczbę i listy, akceptuje pusty obiekt z wartościami domyślnymi.
- `PublicWebPageFetcherTest`: `PublicWebUrl::normalize()` przyjmuje tylko porty 80/443, odrzuca dane logowania, inne schematy, hosty lokalne i adresy IP.
- Walidacja: formatter 0/46, PHP 190/190 (931 asercji), UI 82/82.

## 2026-10-20 — Qodana: odpowiedzi JSON i czytelność

- `JsonResponder` koduje z `JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE`: niepoprawny UTF-8 (np. z odpowiedzi dostawcy AI) nie zamienia już odpowiedzi w 500, a niekodowalne dane rzucają wyjątek zamiast pustego body. Middleware błędów korzysta z `JsonResponder` (dochodzi `Cache-Control: no-store`).
- Zagnieżdżone ternary → `match (true)` (`bin/reset-password.php`, `ChatService`, `SearchDiagnostics`); zbędna zmienna w `dependencies.php`; `$raw` w fetcherze inicjalizowane przed `try`.
- `qodana.yaml`: wykluczony `public/ui/lib` (zminifikowana biblioteka marked, 151 z 317 zgłoszeń).
- Świadomie bez zmian: `chat-controller.js:192` (fałszywy alarm — flaga chroni przed podwójnym wysłaniem), lokalne `throw` w obsłudze błędów UI, „Non-optimal if conditions” (kolejność warunków to strażnicy typów).
- Walidacja: formatter 0/46, PHP 192/192 (936 asercji), UI 82/82.

## 2026-10-20 — #62-510: integracja OpenRouter

- `OpenRouterProvider` (selektor `openrouter:<vendor>/<model>`): katalog `GET /api/v1/models` (tylko wyjście tekstowe, poprawny identyfikator), opcjonalna lista `OPENROUTER_MODELS`; rozmowa przez `chat/completions` z limitem 4096 tokenów.
- Prywatność: każde żądanie ma `provider.data_collection: "deny"`; bez nagłówków atrybucji (`HTTP-Referer`, tytuł aplikacji).
- Obrazy wejściowe wg `architecture.input_modalities`; narzędzia WWW (ASK) tylko dla modeli z `tools` w `supported_parameters`.
- `ProviderTransport`: 402 (brak środków) → `provider_limit`. Błąd w body przy statusie 200 → `invalid_response`; 503 (brak dostawcy spełniającego `data_collection`) → `connection_failed`.
- Konfiguracja: `OPENROUTER_API_KEY`, `OPENROUTER_MODELS` w `src/settings.php`, `docker-compose.yml`, `docker/php-fpm-env.conf`; dokumentacja `docs/AI_PROVIDERS.md`, `docs/KONFIGURACJA.md`, `REPO.md`.
- Walidacja: formatter 0/46, PHP 199/199 (986 asercji). UI 77/82 — 5 testów `dev-start.sh` pada przez lokalne, niezacommitowane usunięcie `docker-compose.dev.yml` po stronie właściciela (poza tym commitem).

## 2026-10-20 — Usunięcie Mailpita z projektu

- Usunięte: usługa `mailpit` z `docker-compose.yml` i `docker-compose.dev.yml` (decyzja właściciela). Poczta w dev i prod idzie przez `MAIL_DSN` bez nadpisań w Compose; testową pocztę odbiera zewnętrzny `mail-trap.dom` poza repozytorium.
- `dev-start.sh`: eksportuje wybrany tryb jako `APP_ENV` i wywołuje `sudo --preserve-env=APP_ENV docker compose` (tryb ma pierwszeństwo przed powłoką i plikiem środowiska); prod nadal dodaje `docker-compose.prod.yml`. Bez startu/zatrzymywania Mailpita; komunikat nazywa brakujący plik Compose.
- Testy `tests/ui/dev-start.test.mjs` przepisane (w tym przekazanie trybu do Compose przez sudo).
- Dokumentacja: `README.md`, `REPO.md`, `docs/KONFIGURACJA.md`. E2E bez zmian — nadal używa osobnego, ręcznie uruchamianego kontenera Mailpit (`docs/E2E.md`).
- Walidacja: formatter 0/46, PHP 199/199 (986 asercji), UI 83/83.

## 2026-10-20 — dev-start: kontrola działania app przed Nginxem

- Przyczyna „host not found in upstream app”: błąd składni pliku środowiska (Dotenv `InvalidFileException`) zatrzymywał migrator, kontener app kończył pracę zaraz po starcie, a skrypt 10 razy próbował przeładować Nginxa (każda próba z sudo/FIDO). Plik środowiska poprawił właściciel.
- `dev-start.sh` po `up -d app` sprawdza `ps --status running --services`; gdy app nie działa, kończy się od razu z podpowiedzią (`docker compose logs app`), bez operacji na Nginxie. Czas oczekiwania `DEV_START_APP_WAIT` (domyślnie 2 s, testy 0).
- Walidacja: formatter 0/46, PHP 199/199 (986 asercji), UI 84/84.

## 2026-10-20 — Pulpit bez martwych linków poza dev

- Karty „Test API” i „Zadania” prowadzą do `/test-api`, który istnieje tylko przy `APP_ENV=dev` (S3); w prod dawały 404. Trasa `/` usuwa blok `<!-- dev-only -->…<!-- /dev-only -->` z `landing.html` poza dev; w dev pulpit bez zmian.
- Testy: `TestApiRouteTest` (pulpit w każdym środowisku innym niż dokładne `dev` i w `dev`).
- Walidacja: formatter 0/46, PHP 207/207 (1010 asercji), UI 84/84.
