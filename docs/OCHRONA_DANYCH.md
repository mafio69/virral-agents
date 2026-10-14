# Ochrona danych użytkowników

## Zakres

Email, tytuły rozmów, treść wiadomości, obrazy, tytuły i opisy zadań są szyfrowane
przed zapisem do SQLite. Repozytoria odszyfrowują je w pamięci na potrzeby aplikacji.
Hasła pozostają hashami `password_hash`. Tokeny sesji i potwierdzenia emaila są
zapisywane jako SHA-256 losowych tokenów, nigdy jako oryginalne wartości.

Email w kolumnie `users.email` jest indeksem HMAC, pozwalającym odnaleźć konto i
zachować unikalność. Oryginalny adres znajduje się w `email_ciphertext`.
Rozróżnianie wielkości liter w emailach pozostaje zgodne z dotychczasowym zachowaniem.

XChaCha20-Poly1305 uwierzytelnia szyfrogram. Każdy zapis ma losowy nonce. Kontekst
wiąże szyfrogram z wersją formatu, tabelą, polem, rekordem oraz — odpowiednio —
właścicielem zadania/rozmowy lub rozmową i rolą wiadomości. Kopiowanie szyfrogramów
między rekordami, zmiana właściciela lub roli powodują odmowę odczytu.
Klucz indeksowania emaili jest oddzielnie wyprowadzany przez HKDF.

Nie jest to szyfrowanie całego pliku SQLite. ID, relacje, daty, status zadań,
role wiadomości, modele i długości szyfrogramów pozostają widoczne. Serwer ma dostęp
do odszyfrowanych wartości; wybrany dostawca AI otrzymuje treść rozmowy zgodnie
z działaniem czatu. To nie jest szyfrowanie end-to-end ani ochrona przed przejęciem
procesu aplikacji i jego klucza.

## Osobny klucz

Właściciel konfiguruje `USER_DATA_KEY`: 32 losowe bajty zapisane jako 64 znaki HEX,
w swoim istniejącym, bezpiecznym mechanizmie środowiska uruchamianego przez `dxrun`.
Klucz ma być niezależny od `DB_HASH`; aplikacja i migrator odrzucają identyczne
wartości tych kluczy. Nie wpisuj klucza do konfiguracji PHP, kodu, dokumentacji,
argumentów polecenia ani czatu. Nie generuj nowego przy każdym uruchomieniu.

Zachowaj klucz poza bazą i jej kopiami. Utrata klucza oznacza utratę dostępu do
danych. Rotacja klucza wymaga osobnej migracji; zmiana samej zmiennej środowiska
nie dokonuje rotacji. Migrator weryfikuje istniejący klucz i nie szyfruje ponownie
przy użyciu innego klucza. Kluczy dostawców AI ani mechanizmu `DB_HASH` nie zmieniono.

## Aktywacja przez właściciela

Zatrzymaj aplikację przed migracją i zapewnij wyłączny dostęp do bazy. Istniejący
plik musi być dostępny i zapisywalny dla procesu CLI. Nie używaj nowej pustej
bazy zamiast istniejącego pliku przez pomyłkę.

Po ustawieniu klucza uruchom z katalogu projektu:

```bash
dxrun php bin/protect-user-data.php --apply
```

Migrator nie odczytuje plików środowiska. Pobiera klucz wyłącznie z procesu
uruchomionego przez właściciela. Zachowuje dane, identyfikatory i hashe haseł.
W jednej transakcji szyfruje istniejące wartości, hashuje tokeny potwierdzeń emaila,
unieważnia wszystkie dotychczasowe sesje i zapisuje uwierzytelniony znacznik migracji.
Niepowodzenie transakcji wycofuje wszystkie te zmiany. Ponowne wykonanie z tym samym
kluczem nie zmienia istniejących szyfrogramów.

Skrypt następnie wykonuje checkpoint WAL i VACUUM; podczas migracji włączony jest
`secure_delete`. Błąd porządkowania po zatwierdzeniu migracji nie cofa szyfrowania:
zachowaj ten sam klucz, zatrzymaj inne procesy i uruchom skrypt ponownie.
Te operacje nie usuwają dawnych kopii bazy, snapshotów, zewnętrznych kopii WAL ani
nie gwarantują fizycznego wymazania bloków SSD. Dawne kopie zawierające jawne dane
pozostają osobnym zadaniem właściciela.

Compose i PHP-FPM przekazują `USER_DATA_KEY`. Odtworzenie kontenerów wykonuje
właściciel istniejącym sposobem uruchamiania; jeżeli używa `sudo`, musi zachować
również tę zmienną wraz z pozostałymi wymaganymi zmiennymi aplikacji.
Po migracji i odtworzeniu kontenerów zaloguj się ponownie.

Nowa baza bez danych użytkowników jest przygotowywana automatycznie przy starcie,
jeżeli proces otrzyma poprawny `USER_DATA_KEY`. Inicjalizacja zapisuje znacznik
ochrony w transakcji, bez migracji istniejących treści. Baza zawierająca użytkowników,
rozmowy, wiadomości, obrazy, zadania lub sesje nadal wymaga jawnej migracji CLI.
Nie trzeba usuwać istniejącej bazy, aby wykonać migrację.

Bez klucza, z błędnym kluczem albo bez znacznika migracji istniejących danych aplikacja odmawia pracy
z danymi i zwraca 503. Nie przechodzi na zapis jawny i nie migruje danych użytkowników
automatycznie przy żądaniu HTTP. Migracja struktury tabel przy starcie nadal działa.
Interaktywny reset hasła również wymaga `dxrun` i poprawnego `USER_DATA_KEY`.

Przy HTTP 503 log inicjalizacji zawiera wyłącznie bezpieczny kod etapu `stage`;
ten sam kod jest dostępny w nagłówku odpowiedzi `X-App-Error`:
`database_schema` oznacza połączenie lub strukturę bazy, `user_data_key` konfigurację
klucza, a `user_data_readiness` przygotowanie pustej bazy lub weryfikację migracji
i jej klucza. Kod etapu nie zawiera klucza, danych, treści wyjątku ani stosu wywołań.
Dokładniejsze kody konfiguracji to `user_data_key_missing` (brak klucza),
`user_data_key_invalid` (niepoprawny format), `user_data_key_reused` (użyto klucza
rejestru dostawców) oraz `sodium_unavailable` (brak rozszerzenia PHP).

## Testy

Testy korzystają wyłącznie z losowych kluczy testowych i SQLite w pamięci.
Sprawdzają nieobecność jawnych wartości w rekordach, odczyt przez repozytoria,
ścieżki batch/update, null i Unicode, losowe nonce, podmianę kontekstu/właściciela,
uszkodzenie szyfrogramu, odmowę zapisu bez migracji, hashowanie tokenów,
zachowanie danych podczas migracji, idempotencję, błędny klucz i rollback.
Agent nie uruchamia migracji rzeczywistej bazy ani nie odczytuje rzeczywistego klucza.
