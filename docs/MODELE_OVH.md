# Modele OVHcloud AI Endpoints

Stan sprawdzenia publicznej dokumentacji: **2026-10-13**.
Źródło: [katalog OVHcloud](https://www.ovhcloud.com/en/public-cloud/ai-endpoints/catalog/).
Zestawienie obejmuje modele uwzględnione w kodzie VirralAI oraz kandydatów do dalszych integracji. Obecność w katalogu nie oznacza potwierdzonego połączenia z naszego konta.

## Adresy API

Baza klienta OpenAI-compatible: `https://oai.endpoints.kepler.ai.cloud.ovh.net/v1`

| Skrót w tabelach | Pełny endpoint |
| --- | --- |
| CZAT | `POST https://oai.endpoints.kepler.ai.cloud.ovh.net/v1/chat/completions` |
| WEKTORY | `POST https://oai.endpoints.kepler.ai.cloud.ovh.net/v1/embeddings` |
| TRANSKRYPCJA | `POST https://oai.endpoints.kepler.ai.cloud.ovh.net/v1/audio/transcriptions` |
| GENEROWANIE | `POST https://oai.endpoints.kepler.ai.cloud.ovh.net/v1/images/generations` |
| LISTA | `GET https://oai.endpoints.kepler.ai.cloud.ovh.net/v1/models` |

Model wskazujemy identyfikatorem w polu `model`; nie tworzymy osobnego URL z nazwy modelu. Identyfikatory są wrażliwe na zapis znaków.

## Czat i analiza obrazów — uwzględnione w kodzie

„Domyślny” oznacza aktualny wybór w kodzie, nie potwierdzenie działania na koncie OVH. Opisy zastosowań to nasz proponowany dobór.

| Nazwa | ID modelu w API | Endpoint | Krótki opis / zastosowanie |
| --- | --- | --- | --- |
| [Qwen3.6 27B](https://www.ovhcloud.com/en/public-cloud/ai-endpoints/catalog/qwen-3-6-27b/) | `Qwen3.6-27B` | CZAT | Domyślny w VirralAI. Tekst, analiza obrazów, rozumowanie; kontekst 262K. |
| [Qwen3.8 27B](https://www.ovhcloud.com/en/public-cloud/ai-endpoints/catalog/qwen-3-8-27b/) | `Qwen3.8-27B` | CZAT | Kandydat do kodowania i zadań wieloetapowych; także obrazy, 262K. |
| [Qwen3.5 397B A17B](https://www.ovhcloud.com/en/public-cloud/ai-endpoints/catalog/qwen-3-5-397b/) | `Qwen3.5-397B-A17B` | CZAT | Duży model MoE do trudniejszych analiz, kodu i obrazów; 262K. |
| [Qwen3.5 9B](https://www.ovhcloud.com/en/public-cloud/ai-endpoints/catalog/qwen-3-5-9b/) | `Qwen3.5-9B` | CZAT | Mniejszy model tekstowo-obrazowy do prostszych zadań; 262K. |
| [Qwen2.5 VL 72B](https://www.ovhcloud.com/en/public-cloud/ai-endpoints/catalog/qwen-2-5-vl-72b-instruct/) | `Qwen2.5-VL-72B-Instruct` | CZAT | Alternatywa do analizy obrazów; kontekst 32K. |
| [GPT OSS 120B](https://www.ovhcloud.com/en/public-cloud/ai-endpoints/catalog/gpt-oss-120b/) | `gpt-oss-120b` | CZAT | Tekst i rozumowanie, 131K; kandydat do planowania i analizy. |
| [GPT OSS 20B](https://www.ovhcloud.com/en/public-cloud/ai-endpoints/catalog/gpt-oss-20b/) | `gpt-oss-20b` | CZAT | Mniejszy model tekstowy z rozumowaniem; 131K. |
| [Llama 3.3 70B Instruct](https://www.ovhcloud.com/en/public-cloud/ai-endpoints/catalog/llama-3-3-70b-instruct/) | `Meta-Llama-3_3-70B-Instruct` | CZAT | Wielojęzyczny czat tekstowy, 131K; alternatywa dla Qwena. |

Qweny oznaczone jako Vision przyjmują tekst i obrazy, a zwracają tekst. **Nie generują obrazków.**
Obsługa function calling przez dostawcę nie włącza narzędzi ani Bash w VirralAI. GPT OSS w OVH nie jest usługą ChatGPT.

## Warte uwagi — osobne integracje do przygotowania

| Nazwa | ID modelu w API | Endpoint | Krótki opis / zastosowanie |
| --- | --- | --- | --- |
| [Qwen3 Embedding 8B](https://www.ovhcloud.com/en/public-cloud/ai-endpoints/catalog/qwen3-embedding-8b/) | `Qwen3-Embedding-8B` | WEKTORY | Wektory tekstu do wyszukiwania dokumentów i RAG. |
| [BGE M3](https://www.ovhcloud.com/en/public-cloud/ai-endpoints/catalog/bge-m3/) | `bge-m3` | WEKTORY | Wielojęzyczne wyszukiwanie semantyczne; dobry kandydat dla dokumentów. |
| [BGE multilingual Gemma2](https://www.ovhcloud.com/en/public-cloud/ai-endpoints/catalog/bge-multilingual-gemma2/) | `bge-multilingual-gemma2` | WEKTORY | Alternatywa embeddingów z bieżącego katalogu. |
| [Qwen3Guard Gen 8B](https://www.ovhcloud.com/en/public-cloud/ai-endpoints/catalog/qwen-guard-gen-8b/) | `Qwen3Guard-Gen-8B` | CZAT | Moderacja treści wejścia i odpowiedzi; beta. |
| [Qwen3Guard Gen 0.6B](https://www.ovhcloud.com/en/public-cloud/ai-endpoints/catalog/qwen-guard-gen-06b/) | `Qwen3Guard-Gen-0.6B` | CZAT | Mniejszy wariant moderacji; beta. |
| [Whisper large v3](https://www.ovhcloud.com/en/public-cloud/ai-endpoints/catalog/whisper-large-v3/) | `whisper-large-v3` | TRANSKRYPCJA | Zamiana nagrań głosowych na tekst. |
| [Whisper large v3 turbo](https://www.ovhcloud.com/en/public-cloud/ai-endpoints/catalog/whisper-large-v3-turbo/) | `whisper-large-v3-turbo` | TRANSKRYPCJA | Alternatywa transkrypcji do rozważenia dla czatu głosowego. |
| [Stable Diffusion XL](https://www.ovhcloud.com/en/public-cloud/ai-endpoints/catalog/stable-diffusion-xl/) | `stable-diffusion-xl-base-v10`* | GENEROWANIE | Generowanie nowych obrazów z opisu; oddzielny przepływ od analizy Qwenem. |

*Dokumentacja SDXL jest niespójna: katalog i przykład Python podają `stable-diffusion-xl-base-v10`, a przykład curl `stabilityai/stable-diffusion-xl-base-1.0`. Przed integracją potwierdzić ID przez LISTA. Udokumentowany endpoint dedykowany SDXL: `POST https://stable-diffusion-xl.endpoints.kepler.ai.cloud.ovh.net/api/text2image`.

Guard jest filtrem moderacji; nie zastępuje izolacji modeli, kontroli uprawnień ani ochrony przed prompt injection.

## Nazwy historyczne w repo

`Qwen3-Coder-30B-A3B-Instruct`, `Qwen3-32B`, `Mistral-7B-Instruct-v0.3`, `Mistral-Nemo-Instruct-2407`, `Mistral-Small-3.2-24B-Instruct-2506` i `Llama-3.1-8B-Instruct` nie występują w sprawdzonym katalogu. Nie traktować ich jako aktualnej listy dostępnych modeli.

Stary Qwen Coder został zastąpiony w wyborze domyślnym przez `Qwen3.6-27B`. Data wycofania starego modelu nie została potwierdzona. Nie przywracać jego dedykowanego endpointu jako domyślnego.

## Używanie tej listy

Przed zmianą modelu sprawdzić aktualny wynik LISTA dla konta. Ten dokument zapisuje publiczny katalog i nasze wybory; nie przechowuje kluczy, poświadczeń ani wyników rozmów. Audio, embeddingi, Guard i generowanie obrazów nie są obecnie osobnymi działającymi funkcjami VirralAI.
