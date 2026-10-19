# Modele Z.ai — integracja Virral Agents

Sprawdzono w oficjalnej dokumentacji: 2026-10-13.

## Standardowe API

- Adres bazowy: https://api.z.ai/api/paas/v4/
- Czat: POST https://api.z.ai/api/paas/v4/chat/completions
- Format zgodny z OpenAI Chat Completions; uwierzytelnienie Bearer wykonywane wyłącznie przez backend.

Źródło: [Quick Start](https://docs.z.ai/guides/overview/quick-start).

## Modele do pierwszego etapu

| Nazwa | ID w API | Zastosowanie | Obrazy |
| --- | --- | --- | --- |
| GLM-5.3 | glm-5.3 | Kodowanie i zadania tekstowe | Nie włączać bez osobnego potwierdzenia |
| GLM-5.3-Flash | glm-5.3-flash | Czat i analiza wizualna | Tak, image_url z Base64 Data URL |
| GLM-5.3-FlashX | glm-5.3-flashx | Szybszy wariant multimodalny | Tak, image_url z Base64 Data URL |

Dokumentacja Flash/FlashX podaje kontekst 1M i maksymalną odpowiedź 128K tokenów. Są to limity dostawcy, nie ustawienia Virral Agents. Modele zwracają tekst; obsługa obrazów wejściowych nie oznacza generowania grafik. Thinking nie można wyłączyć w tych wariantach.

Źródła: [GLM-5.3](https://docs.z.ai/guides/llm/glm-5.3), [Flash/FlashX](https://docs.z.ai/guides/vlm/glm-5.3-flash).

## Coding Plan

Coding Plan ma oddzielną konfigurację endpointu. Jego zasady ograniczają użycie do oficjalnie wspieranych narzędzi i produktów. Nie potwierdzono uprawnienia Virral Agents do tego planu; pierwsza integracja powinna korzystać ze standardowego API.

Źródło: [Usage Policy](https://docs.z.ai/devpack/usage-policy).

## Stan aplikacji i zakres wdrożenia

Integracja kodu jest podłączona do aktywnego czatu przez istniejący rejestr modeli. Nie potwierdzono połączenia z prawdziwym kontem Z.ai.

Istnieją AiClientInterface, OpenAiCompatClient i fabryka korzystająca z rejestru modeli. Wdrożono wspólny transport Guzzle, wybór modeli Z.ai w obecnym czacie i obsługę zweryfikowanych obrazów. Bez narzędzi wykonawczych, dostępu modeli do Bash i przekazywania historii między dostawcami.

Nie wpisywać kluczy do tego dokumentu, czatu ani logów. Rejestr modeli może udostępniać UI wyłącznie bezpieczne metadane. Weryfikacja dokumentacji nie potwierdza dostępności modelu na konkretnym koncie.

## Konfiguracja i uruchomienie

### Bezpośrednia konfiguracja lokalna (2026-10-15)

- Bieżąca instalacja korzysta z `ZAI_API_KEY` w lokalnym środowisku, wczytywanym przez wspólne src/settings.php. Compose oraz PHP-FPM przekazują tę samą zmienną. Nie wymaga to dodawania dostawcy ani klucza do rejestru bazy.
- Przy skonfigurowanym kluczu lista zwraca modele `zai:...` z configured:true. To informacja o obecności konfiguracji, nie potwierdzenie uprawnień konta. Wywołania używają wyłącznie https://api.z.ai/api/paas/v4/chat/completions.
- Istniejące selektory `db:...` nadal używają przypisanego rejestru. Nowe rozmowy z selektorem `zai:...` korzystają z konfiguracji środowiska; jej usunięcie blokuje wysyłkę zamiast przekierowania do OVH lub dostawcy z bazy. Nie zapisuje się klucza środowiska w bazie.
- Publiczny katalog nadal służy walidacji identyfikatorów modeli i nie potwierdza rzeczywistego dostępu. Działający endpoint aplikacji zwrócił 8 modeli configured:true; właściciel potwierdził działanie czatu.
- PHP 87/87 (270 asercji), UI 31/31; atrapy transportu potwierdzają standardowy endpoint, obrazy/historię, odmowę nieznanego modelu, kontrolę właściciela oraz blokadę po usunięciu konfiguracji. Agent nie wysłał rzeczywistej wiadomości testowej.

### Konfiguracja przez rejestr bazy

- UI pokazuje aktywne rekordy GLM z ai_models, połączone z aktywnym dostawcą i URL standardowego API. Nazwa zaczyna się od Z.ai. Sam dokument nie dodaje rekordów modeli do bazy.
- Dla obrazów należy skonfigurować dokładnie glm-5.3-flash lub glm-5.3-flashx. Inne identyfikatory GLM pozostają tekstowe, dopóki możliwości nie zostaną potwierdzone.
- Klucz dostawcy pozostaje w istniejącym zaszyfrowanym rejestrze. DB_HASH jest przekazywany z procesu do Compose i PHP-FPM, a używany dopiero przy wyborze zarejestrowanego modelu. Agent nie odczytywał ani nie zmieniał rzeczywistej konfiguracji lub danych bazy.
- Zmiana listy zmiennych Compose/PHP-FPM wymaga odtworzenia kontenerów przez właściciela. Gdy dxrun poprzedza sudo, trzeba zachować także DB_HASH w jawnej liście nazw przekazywanych zmiennych; nigdy nie wypisywać wartości.
- Migracja przy zwykłym starcie tworzy brakujące tabele rejestru, bez nadpisywania istniejących rekordów i bez dodawania pustych kluczy.
- Wyboru dostawcy dokonuje się przed rozpoczęciem rozmowy. Zapisany identyfikator wiąże ją z rekordem, dostawcą, modelem API i endpointem. Zmiana tego powiązania lub usunięcie rekordu blokuje dalszą wysyłkę; nie przenosi historii do OVH.
- Niedostępność katalogu OVH nie blokuje wyboru skonfigurowanych modeli Z.ai. Domyślny model aplikacji nadal jest modelem OVH; wybór GLM jest jawny.

## Weryfikacja

2026-10-13: pojedyncze uruchomienie zatwierdzone przez użytkownika, filtr ChatService|OvhAiClient|ChatImage|ZaiChatIntegration: 32/32 testy, 92 asercje. Baza w pamięci i atrapy HTTP. Sprawdzono format obrazu, historię jednej rozmowy, własność rozmowy, zmianę powiązania, usunięcie dostawcy, odrzucenie Coding Plan i neutralne błędy transportu. Brak testu rzeczywistego Z.ai oraz nowego testu przeglądarki. Nie wdrożono pełnej izolacji wykonawczej ani uniwersalnego maskowania treści czatu.

## Aktualizacja katalogu sieciowego — 2026-10-13

ZaiModelCatalogue pobiera https://docs.z.ai/openapi.json i odczytuje enum modelu schematów ChatCompletionTextRequest oraz ChatCompletionVisionRequest. Nie wysyła poświadczeń ani danych projektu. Potwierdzono w UI 8 identyfikatorów i przywrócenie wyboru po przeładowaniu. Bez dostępnego rejestru połączenia katalog nadal jest widoczny, ale rozmowa pozostaje zablokowana. Przy skonfigurowanym połączeniu nowe modele są rejestrowane wyłącznie jako metadane; sekrety i rekordy dostawców pozostają bez zmian. Uprawnienia konta nie są potwierdzone przez publiczną specyfikację.
