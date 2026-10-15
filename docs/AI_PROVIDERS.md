# Dostawcy czatu

## Google / Gemini

- Klucz: `GEMINI_KEY`, przesyłany nagłówkiem `x-goog-api-key`, bez umieszczania w URL.
- Katalog `GET https://generativelanguage.googleapis.com/v1beta/models` uwzględnia
  paginację i modele Gemini obsługujące generateContent; pomija warianty generowania obrazów i audio.
- Rozmowa: `POST /v1beta/models/<model>:generateContent`, limit odpowiedzi 4096 tokenów.
  System trafia do systemInstruction, rola assistant do model, obrazy do inlineData.
- Selektor `google:<model>` zachowuje dostawcę rozmowy. Odpowiedź obejmuje tekst,
  z pominięciem bloków thought i wywołań funkcji. Narzędzia nie są udostępniane.
- Testy używają atrap; dostęp rzeczywistego konta wymaga lokalnego sprawdzenia.

Źródła sprawdzone 2026-10-15:
[katalog](https://ai.google.dev/api/models),
[generateContent](https://ai.google.dev/api/generate-content),
[autoryzacja](https://ai.google.dev/gemini-api/docs/generate-content/text-generation).

Każda rozmowa zapisuje wybrany model wraz z identyfikatorem dostawcy. Brak dostępu,
konfiguracji lub modelu powoduje błąd; historia nie przechodzi do innego dostawcy.
Odpowiedzi modeli pozostają tekstem. Integracje nie udostępniają narzędzi wykonawczych.

## OpenAI

- Klucz: `OPEN_AI_KEY`, w istniejącym lokalnym środowisku właściciela.
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

## Anthropic / Claude

- Klucz: `ANTHROPIC_API_KEY`. Natywne `POST https://api.anthropic.com/v1/messages`,
  nagłówki `x-api-key` i `anthropic-version: 2023-06-01`. To bezpośrednie API Anthropic.
- Katalog konta z `GET /v1/models` uwzględnia paginację i pomija modele wycofane.
  Możliwość analizy obrazów pochodzi z `capabilities.image_input`, a przy braku tego
  pola z udokumentowanych rodzin Claude. Nie ma statycznej listy dostępnych modeli.
- Wiadomości systemowe trafiają do pola `system`, historia zachowuje role user/assistant,
  obrazy są wysyłane jako bloki base64 z MIME. Zdalne URL obrazów są odrzucane.
- Limit odpowiedzi: 4096 tokenów. Odbierane są wyłącznie bloki tekstowe;
  rozumowanie i wywołania narzędzi nie są wykonywane ani prezentowane jako odpowiedź.
- Selektor rozmowy: `anthropic:<model>`. Klucz trafia z ustawień do klienta backendu
  i jest przekazywany przez Compose/PHP-FPM.

Źródła sprawdzone 2026-10-15:
[Messages API](https://platform.claude.com/docs/en/api/messages/create),
[katalog](https://platform.claude.com/docs/en/api/models/list),
[możliwości modeli](https://platform.claude.com/docs/en/models/overview).
