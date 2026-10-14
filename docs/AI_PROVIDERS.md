# Dostawcy czatu

Każda rozmowa zapisuje wybrany model wraz z identyfikatorem dostawcy. Brak dostępu,
konfiguracji lub modelu powoduje błąd; historia nie przechodzi do innego dostawcy.
Odpowiedzi modeli pozostają tekstem. Integracje nie udostępniają narzędzi wykonawczych.

## OpenAI

- Klucz: `OPENAI_API_KEY`, w istniejącym lokalnym środowisku właściciela.
- Katalog: `GET https://api.openai.com/v1/models`; filtrowane są modele do rozmów,
  bez modeli audio, generowania obrazów i embeddingów. Bez klucza lista zgłasza brak konfiguracji.
- Rozmowa: `POST https://api.openai.com/v1/responses`, `store:false`, limit odpowiedzi
  4096 tokenów. Klient zbiera bloki `output_text`, ignoruje rozumowanie i wywołania narzędzi.
- Obrazy używają istniejącego sprawdzania załączników aplikacji i formatu `input_image`.
  Obsługa obrazów oznaczona dla rodzin GPT-4o, GPT-4.1 i GPT-5/6 dostępnych w katalogu konta.
- Identyfikatory zapisanych rozmów: `openai:<model>`. Listę pobiera backend; klucze
  i odpowiedzi błędów dostawcy nie trafiają do przeglądarki ani logów.
- Compose i PHP-FPM przekazują nazwę zmiennej. Zmiana środowiska kontenera wymaga
  odtworzenia przez właściciela; agent nie odczytuje plików środowiska.

Źródła sprawdzone 2026-10-15:
[Responses](https://developers.openai.com/api/docs/guides/text),
[obrazy](https://developers.openai.com/api/docs/guides/images-vision),
[wyłączenie przechowywania](https://developers.openai.com/api/docs/guides/migrate-to-responses),
[katalog modeli](https://platform.openai.com/docs/api-reference/models/list).

Testy używają fikcyjnych kluczy, atrap HTTP i SQLite w pamięci. Nie potwierdzają
uprawnień rzeczywistego konta ani płatnej odpowiedzi modelu.
