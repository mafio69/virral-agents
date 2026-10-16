# REPO.md — mapa techniczna repozytorium

> **Cel:** skrócić wejście w projekt. Zapisuj tu tylko trwałe fakty: architekturę, sposób uruchamiania, istotne mechanizmy, świadome decyzje i pułapki. Dzięki temu agent lub programista nie musi ponownie przeszukiwać kodu ani powtarzać starych błędów.
>
> To nie jest instrukcja polityk ani dziennik prac: zasady pracy są w `AGENTS.md` / `GIT-FLOW.md`, bieżący stan w `STATUS.md`, przekazanie sesji w `HANDOFF.md`, a historia zmian w `CHANGELOG.md`.

## 1. Przeznaczenie

- **Problem rozwiązywany:** [jedno zdanie]
- **Główni użytkownicy:** [kto]
- **Najważniejszy przepływ:** [wejście → przetworzenie → wynik]
- **Granice systemu:** [co świadomie nie należy do tego repozytorium]

## 2. Uruchamianie

| Cel | Polecenie | Istotna uwaga |
|---|---|---|
| Instalacja | `[komenda]` | `[warunek / wersja]` |
| Development | `[komenda]` | `[port, URL, zachowanie watch/HMR]` |
| Pełne środowisko | `dxrun -- [komenda]` | Używaj dla procesów wymagających zmiennych środowiskowych |
| Testy kompletne | `[komenda]` | Musi przejść przed commitem |
| Build | `[komenda]` | `[gdzie jest wynik]` |
| Produkcja | `[komenda lub procedura]` | `[wyłącznie faktycznie dozwolony sposób]` |

Nie wpisuj tu sekretów, prywatnych kluczy ani ich ścieżek. Opisz jedynie bezpieczny sposób uruchomienia, np. `dxrun -- [komenda]`.

## 3. Architektura

```text
[klient / CLI / integracja]
        ↓
[punkt wejścia / API / worker]
        ↓
[domena / usługi]
        ↓
[baza / pliki / zewnętrzne API]
```

| Obszar | Lokalizacja | Odpowiedzialność |
|---|---|---|
| Wejście aplikacji | `[plik/katalog]` | `[co inicjuje]` |
| Interfejs / API | `[plik/katalog]` | `[co obsługuje]` |
| Logika domenowa | `[plik/katalog]` | `[reguły biznesowe]` |
| Dane | `[plik/katalog]` | `[źródło danych i trwałość]` |
| Integracje | `[plik/katalog]` | `[z czym i w jakim celu]` |
| Testy | `[plik/katalog]` | `[zakres]` |

## 4. Mechanizmy krytyczne

Zapisuj tylko rzeczy, których nie widać od razu po nazwie pliku i których błędne zrozumienie powoduje błąd.

| Mechanizm | Jak działa | Gdzie szukać |
|---|---|---|
| `[np. autoryzacja]` | `[krótki przebieg i warunek sukcesu]` | `[plik/moduł]` |
| `[np. kolejka]` | `[kiedy zapis, retry, sukces]` | `[plik/moduł]` |
| `[np. stan runtime]` | `[co przetrwa restart, co nie]` | `[katalog/moduł]` |
| `[np. integracja]` | `[granica odpowiedzialności]` | `[plik/moduł]` |

## 5. Decyzje i pułapki

| Temat | Przyjęte rozwiązanie | Dlaczego / czego nie robić |
|---|---|---|
| `[np. konfiguracja]` | `[konkret]` | `[typowy błąd i skutek]` |
| `[np. dane]` | `[konkret]` | `[ograniczenie lub ryzyko]` |
| `[np. uruchamianie]` | `[konkret]` | `[co nie zadziała]` |
| `[np. Git/deploy]` | `[konkret]` | `[czego nie usuwać / nie omijać]` |

Dodaj wpis tutaj dopiero, gdy informacja jest trwała, zweryfikowana i oszczędzi kolejnym osobom realnego szukania lub awarii.

## 6. Stałe operacyjne

- **Porty / endpointy publiczne:** `[tylko niejawne adresy i porty]`
- **Limity / timeouty / retry:** `[wartość + miejsce definicji]`
- **Dane runtime:** `[lokalizacja, czy są wersjonowane, czy przetrwają restart]`
- **Języki / formaty / konwencje UI:** `[tylko istotne wyjątki]`
- **Zależności infrastrukturalne:** `[wymagane usługi bez danych dostępowych]`

## Aktualizacja

### MCP Search w czacie

- `ChatService` deleguje odpowiedź do `ChatReplyGeneratorInterface`. Implementacja
  `ChatReplyGenerator` wykonuje zatwierdzone wyszukanie, przekazuje niezaufane wyniki
  do wybranego klienta AI i dopisuje URL źródeł do szyfrowanego tekstu odpowiedzi.
- `McpSearchClient` obsługuje Streamable HTTP (JSON/SSE), inicjalizację, negocjację
  protokołu, sesję, `tools/list`, `tools/call` i zamknięcie sesji; limit odpowiedzi 1 MiB,
  timeout wywołania/odczytu 45 s. Nie otrzymuje kluczy dostawców AI.
- Domyślny tryb WWW to ASK. Zgoda dotyczy jednego jawnego zapytania i nie przechodzi
  na kolejną wiadomość. Wyszukiwanie jest oddzielne od wiadomości; checkboxy zbierają źródła. Fetch wymaga osobnego Allow dla każdego adresu.
- `MCP_SEARCH_URL`: aplikacja/Compose/FPM domyślnie używają `http://mcp-search:8000/mcp`.
  PHP uruchomione na hoście wymaga jawnego `http://127.0.0.1:8890/mcp`.
  Samodzielny skrypt diagnostyczny nadal domyślnie używa adresu hosta.
  Wymagana wspólna sieć Docker `ai`; działające kontenery trzeba do niej podłączyć.
- Chroniony `GET /api/search/status` sprawdza tylko inicjalizację i narzędzie, bez WWW.
  `search.status: unavailable` zawiera bezpieczne kody `reason`/`stage`; te same kody
  są logowane bez zapytań, historii, sesji i surowych wyjątków.
- `bin/check-mcp-search.php` sprawdza protokół bez wczytywania konfiguracji,
  sekretów i bazy. Wyszukanie wymaga jawnego `--query` i `--approve-search`.
- Szczegóły: `docs/MCP-USAGE.md`. Testy: `McpSearchClientTest`, `ChatSearchTest`,
  `ProviderRoutingTest` i `tests/ui/chat-search.test.mjs`.

Aktualizuj `REPO.md` w tym samym commicie, gdy zmienia się architektura, uruchamianie, trwały mechanizm, limit, struktura katalogów albo znana pułapka. Nie wpisuj statusu zadania, doraźnych obejść ani sekretów.

### Badanie wybranych źródeł

- `ResearchService` zarządza wyborem i jednorazowymi decyzjami, a `ResearchReplyGenerator` przekazuje wyłącznie treść pobranych, zaznaczonych źródeł. Kontroler tylko mapuje wejście.
- `ResearchRepositoryInterface` i szyfrowana tabela `research_workspaces` przechowują wyniki, koszyk oraz decyzje per użytkownik/rozmowa; scope 0 jest szkicem przenoszonym przy utworzeniu czatu.
- `PublicWebPageFetcher` sprawdza publiczny IPv4 i przypina połączenie do niego; obsługuje HTML/tekst bez przekierowań, cookies i JS. Limit 1 MiB / 16 tys. znaków na stronę; 20 źródeł / 100 tys. znaków kontekstu.
- `WebToolClientInterface` daje natywne prośby AI o search/fetch; wywołanie tworzy oczekującą decyzję, nigdy zgodę. Modele generujące obrazy Gemini nie otrzymują tools.
- `chat-research.js` zbiera źródła między wyszukiwaniami; checkbox nie oznacza zgody na fetch. Szczegóły API i testów w `docs/MCP-USAGE.md`.
