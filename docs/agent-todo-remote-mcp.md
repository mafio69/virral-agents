# Agent w innym repo → DevBrain-Todo (DT) przez zdalny MCP

Ten plik jest napisany tak, żeby można go **skopiować 1:1 do innego repo** (analogicznie
do `GIT-FLOW.md` w tym repo) i dać agentowi AI pracującemu tam jako instrukcję: jak
podłączyć się do wspólnej tablicy zadań DevBrain-Todo (**DT**) przez zdalny endpoint MCP
i zapisać tam hurtowo uzgodnione z użytkownikiem zadania — bez dostępu do kodu/konsoli
tego repozytorium (DevBrain), wyłącznie przez sieć.

> Pełna, kanoniczna dokumentacja protokołu (wersja dla pracy *wewnątrz* repo DevBrain,
> z komendami konsolowymi `app:todo:*`) żyje w `instrukcja-devbrain-todo.md` w repo
> DevBrain. Ten plik jest jej podzbiorem — tylko to, co dotyczy zdalnego `/mcp`, bez
> komend konsolowych (agent w innym repo nie ma do nich dostępu).

---

## 1. Połączenie i autoryzacja

**URL:** adres zdalnego endpointu DevBrain, np. `https://devbrain.virral.tech/mcp`
(poproś użytkownika o dokładny adres, jeśli go nie podał). **Tylko HTTPS** — zwykłe
HTTP zwraca `400`.

**Jest tylko jeden wspierany sposób podłączenia: zdalny endpoint HTTP powyżej.**
Lokalny serwer MCP działający po stdio przez `docker exec` (`devbrain-todo-mcp-server/`
w repo DevBrain) jest **celowo zablokowany** po stronie DevBrain — nie uruchamiaj go i
nie próbuj go naprawiać. Jeśli w jakimkolwiek configu (swoim albo skopiowanym od kogoś)
zobaczysz wpis typu `"type": "local"` / `command` wskazujący na `dist/index.js` przez
`docker exec` — to jest nieaktualny/zły config, zamień go na konfigurację z sekcji
"Konfiguracja klienta MCP" poniżej.

**Token:** globalny token dostępowy MCP (`McpAccessToken`), wydany przez właściciela
instalacji DevBrain (kafelek „Tokeny" na dashboardzie, albo `app:mcp:token-create
--label="..."` po stronie DevBrain). **Poproś użytkownika o wartość — nigdy nie
zgaduj, nie generuj, nie szukaj go w plikach.** Token to czysty ciąg hex, bez dodatkowych
znaków. Nagłówek na **każdym** zapytaniu (także `initialize` i `tools/list`):

```
Authorization: Bearer <TOKEN>
```

Brak / zły / wygasły / unieważniony token → `401` z `WWW-Authenticate: Bearer`.

**Nigdy nie zapisuj plaintextu tokena do repo, do commitów, do plików konfiguracyjnych
trackowanych przez git** — tylko do lokalnej konfiguracji klienta MCP (poza repo) albo
zmiennej środowiskowej.

### Konfiguracja klienta MCP

**Claude Code:**
```bash
claude mcp add --transport http devbrain https://devbrain.virral.tech/mcp \
  --header "Authorization: Bearer <TOKEN>"
```

**opencode** (`opencode.json`):
```json
"devbrain": {
  "type": "remote",
  "enabled": true,
  "url": "https://devbrain.virral.tech/mcp",
  "headers": { "Authorization": "Bearer <TOKEN>" }
}
```

**Codex** (`~/.codex/config.toml`):
```toml
[mcp_servers.devbrain]
url = "https://devbrain.virral.tech/mcp"
http_headers = { Authorization = "Bearer <TOKEN>" }
```

**Inne klienty:** zwykły `POST` na `/mcp` z JSON-RPC 2.0 (`protocolVersion`
`2024-11-05`), bez SSE i bez sesji — każde zapytanie jest samodzielne. Metody:
`initialize`, `notifications/initialized` (odpowiedź `202`), `tools/list`, `tools/call`.

Szybki test:
```bash
curl -s -X POST https://devbrain.virral.tech/mcp \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer <TOKEN>" \
  -d '{"jsonrpc":"2.0","id":1,"method":"tools/list"}'
```
Poprawna odpowiedź: JSON z listą **8** narzędzi (sekcja 3).

---

## 2. Zasady dostępu

| Lista | Odczyt | Dodawanie zadań / notatka / done / priorytet |
|---|---|---|
| własna prywatna (należąca do właściciela tokenu) | tak | tak |
| publiczna | tak | tak |
| cudza prywatna | `404` (nie zdradza, że istnieje) | `404` |

Token działa jako jego właściciel — widzi własne listy prywatne i wszystkie publiczne.
Usuwanie/zmiana listy i usuwanie zadania nie są dostępne przez MCP (tylko REST
`/api/todo`, i tylko dla właściciela) — w zdalnym MCP nie ma narzędzi niszczących.

---

## 3. Dostępne narzędzia MCP (8 — kompletna lista)

| Narzędzie | Co robi | Argumenty (wymagane **pogrubione**) |
|---|---|---|
| `devbrain_todo_lists` | Listy dostępne dla tokenu: własne prywatne + wszystkie publiczne | — |
| `devbrain_todo_list` | Lista i jej zadania | **`listId`** |
| `devbrain_todo_create_list` | Tworzy **prywatną** listę właściciela tokenu | **`name`** (1–120 znaków), `description` (≤ 20000) |
| `devbrain_todo_create_task` | Dodaje jedno zadanie do listy | **`listId`**, **`title`** (1–200), `description` (≤ 20000), `priority` |
| `devbrain_todo_note` | Dodaje komentarz do zadania (opis zadania bez zmian); z `replyTo` — odpowiedź | **`taskId`**, **`note`** (1–4000), **`agentName`** (1–80), `replyTo` |
| `devbrain_todo_comments` | Wątek komentarzy zadania (główne + odpowiedzi) | **`taskId`** |
| `devbrain_todo_done` | Oznacza zadanie jako zrobione (`done: false` = przywraca) | **`taskId`**, `done` (bool, domyślnie `true`) |
| `devbrain_todo_set_priority` | Ustawia priorytet | **`taskId`**, **`priority`** |

Uwagi:

- `listId` / `taskId` — liczby całkowite (JSON number, **nie string**), stałe ID z bazy.
  `listId` bierzesz z `devbrain_todo_lists` (albo z odpowiedzi `devbrain_todo_create_list`).
  `taskId` bierzesz z pola `id` w odpowiedzi `devbrain_todo_list` / `devbrain_todo_create_task`.
- `priority`: `krytyczny` / `wysoki` / `normalny` / `niski` (`normalny` = brak
  priorytetu). W odpowiedzi serwer zwraca go jako `fire` / `high` / `low` / `null`.
- `arguments` musi być obiektem JSON (także pustym `{}`).
- Argumenty spoza tej listy (np. `listName`, `listToken`, `number`) → błąd `-32602`.
  Nieznane narzędzie (np. `devbrain_todo_bulk_add`, `devbrain_todo_propose`,
  `devbrain_todo_claim`) → błąd `-32601` — **te narzędzia nie istnieją w zdalnym MCP**,
  nawet jeśli pojawiają się w starszych configach lub w dokumentacji komend konsolowych
  DevBrain (`app:todo:bulk-add`, `app:todo:import` — to komendy **lokalne**, dostępne
  tylko wewnątrz repo DevBrain, **nie** przez `/mcp`).

Odpowiedź na udane `tools/call`:
```json
{"jsonrpc":"2.0","id":1,"result":{"content":[{"type":"text","text":"<JSON>"}],"isError":false}}
```
`text` to zserializowany JSON: `{"lists":[...]}` / `{"list":{...},"tasks":[...]}` /
`{"list":{...}}` / `{"task":{...}}`. Pole listy: `id`, `name`, `description`, `private`.
Pole zadania: `id`, `listId`, `title`, `description`, `done`, `priority`, `updatedAt`.

Błędy to JSON-RPC `error` (nie `isError: true`):

| Kod | Znaczenie |
|---|---|
| `-32000` + HTTP `400` | zapytanie bez HTTPS |
| `-32000` + HTTP `401` | brak/zły/wygasły token |
| `-32000` + HTTP `404` | brak zasobu albo brak uprawnień (cudza prywatna lista) |
| `-32000` + HTTP `409` | zmiana stanu zadania anulowanego |
| `-32000` + HTTP `413` | ciało zapytania > 64 KB |
| `-32000` + HTTP `422` | zła wartość pola (pusty tytuł, zły priorytet) |
| `-32602` (HTTP 200) | złe argumenty narzędzia |
| `-32601` | nieznana metoda/narzędzie |
| `-32700` | niepoprawny JSON |
| `-32603` + HTTP `500` | błąd wewnętrzny serwera |

---

## 4. Hurtowe dodanie uzgodnionych zadań — jak to zrobić w praktyce

**Ważne ograniczenie zdalnego MCP:** nie istnieje jedno wywołanie, które tworzy nową
listę **i** dodaje do niej wiele zadań naraz. `devbrain_todo_bulk_add` i
`app:todo:import` (deterministyczny import z pliku `.md`) to komendy **konsolowe**,
dostępne tylko lokalnie wewnątrz repo DevBrain — **nie są wystawione** przez zdalny
`/mcp`. Przez MCP każde zadanie to **osobne** wywołanie `devbrain_todo_create_task`.

To, co z perspektywy użytkownika wygląda jak "dodaj to hurtowo, jednym ruchem", w
praktyce agent realizuje jako **sekwencję wywołań w tej samej turze/odpowiedzi** —
użytkownik nie musi akceptować każdego zadania osobno, wystarczy że agent wykona
wszystkie wywołania jedno po drugim, bez pytania o potwierdzenie między nimi.

### Krok po kroku

1. **Sprawdź, czy docelowa lista już istnieje:**
   ```json
   {"name": "devbrain_todo_lists", "arguments": {}}
   ```
   Przeszukaj wynik po `name`. Jeśli lista o uzgodnionej nazwie już tam jest — użyj jej
   `id` jako `listId` i przejdź do kroku 3 (dopisywanie do istniejącej listy, nie
   nadpisywanie).

2. **Jeśli lista nie istnieje — stwórz ją jednym wywołaniem:**
   ```json
   {"name": "devbrain_todo_create_list", "arguments": {"name": "Nazwa listy", "description": "Opcjonalny opis"}}
   ```
   Odpowiedź zawiera `{"list":{"id": <listId>, ...}}` — zapamiętaj `listId`, będzie
   potrzebny do każdego kolejnego wywołania. Lista utworzona tym narzędziem jest
   **prywatna**, należy do właściciela tokenu.

3. **Dodaj każde uzgodnione zadanie osobnym wywołaniem `devbrain_todo_create_task`,
   jedno po drugim, w tej samej odpowiedzi:**
   ```json
   {"name": "devbrain_todo_create_task", "arguments": {"listId": 21, "title": "Zadanie 1", "description": "Opis/kryteria", "priority": "normalny"}}
   {"name": "devbrain_todo_create_task", "arguments": {"listId": 21, "title": "Zadanie 2", "description": "Opis/kryteria", "priority": "wysoki"}}
   {"name": "devbrain_todo_create_task", "arguments": {"listId": 21, "title": "Zadanie 3"}}
   ```
   - `title` max 200 znaków, `description` opcjonalny (≤ 20000 znaków).
   - Nie pomijaj żadnego uzgodnionego zadania i nie przepuszczaj treści przez
     dodatkowe przeformułowanie — tytuły i opisy powinny odpowiadać temu, co
     faktycznie uzgodniono z użytkownikiem w rozmowie.
   - Jeśli zadań jest dużo (dziesiątki), wykonuj wywołania sekwencyjnie, nie
     równolegle — serwer nie gwarantuje kolejności przy równoległych zapisach, a
     kolejność dodania zwykle ma znaczenie dla użytkownika.

4. **Na koniec zrób jedno `devbrain_todo_list`**, żeby potwierdzić użytkownikowi
   finalny stan listy (ile zadań faktycznie trafiło na tablicę):
   ```json
   {"name": "devbrain_todo_list", "arguments": {"listId": 21}}
   ```

### Dopisywanie do istniejącej listy

Krok 3 działa identycznie, niezależnie od tego, czy lista właśnie powstała, czy już
istniała — `devbrain_todo_create_task` zawsze **dopisuje** zadanie, nigdy nie nadpisuje
ani nie usuwa tego, co już jest na liście.

---

## 5. Dalsza praca na zapisanych zadaniach

- **Notatka/plan do zadania** (nie zmienia statusu wykonania):
  ```json
  {"name": "devbrain_todo_note", "arguments": {"taskId": 89, "note": "Plan: ...", "agentName": "Nazwa agenta/sesji"}}
  ```
  `agentName` powinien jednoznacznie identyfikować sesję/agenta (nie samo "Claude"/"AI" —
  patrz zasada rezerwacji zadań w sekcji 7), żeby inny agent widział, kto pisał co.
- **Odpowiedź w wątku komentarzy** (jeden poziom zagnieżdżenia):
  ```json
  {"name": "devbrain_todo_note", "arguments": {"taskId": 89, "note": "Odpowiedź", "agentName": "...", "replyTo": 12}}
  ```
- **Odczyt wątku komentarzy:**
  ```json
  {"name": "devbrain_todo_comments", "arguments": {"taskId": 89}}
  ```
- **Oznaczenie jako zrobione / przywrócenie:**
  ```json
  {"name": "devbrain_todo_done", "arguments": {"taskId": 89}}
  {"name": "devbrain_todo_done", "arguments": {"taskId": 89, "done": false}}
  ```
- **Zmiana priorytetu:**
  ```json
  {"name": "devbrain_todo_set_priority", "arguments": {"taskId": 89, "priority": "niski"}}
  ```

---

## 6. Rezerwacja zadania (jeśli więcej niż jeden agent pracuje na tej samej liście)

Przed podjęciem realnej pracy nad zadaniem zarezerwuj je komentarzem (konwencja, nie
twardy lock — przestrzegaj jej sam, nawet jeśli inny agent tego nie robi):

```json
{"name": "devbrain_todo_note", "arguments": {"taskId": 89, "note": "[CLAIMED] <podpis> <data/godzina>", "agentName": "<podpis>"}}
```

`<podpis>` musi być konkretny i możliwy do zweryfikowania (np. nazwa sesji/czatu), nie
gołe "Claude"/"AI" — w tym samym repo może działać kilka niezależnych sesji naraz. Po
zakończeniu (albo rezygnacji) — `devbrain_todo_done` albo kolejna notatka informująca,
że zadanie jest wolne.

---

## 7. Czego NIE robić

- Nie wymyślaj własnego formatu zapisu zamiast `devbrain_todo_create_task` i nie
  próbuj wywołać nieistniejących narzędzi (`devbrain_todo_bulk_add`,
  `devbrain_todo_propose`, `devbrain_todo_import`) — przez zdalny `/mcp` nie istnieją,
  dostaniesz `-32601`.
- Nie zgaduj `listId` — zawsze najpierw `devbrain_todo_lists` (albo użyj `listId` z
  odpowiedzi `devbrain_todo_create_list`, jeśli właśnie tworzyłeś listę w tej samej
  rozmowie).
- **Nigdy nie ustawiaj priorytetu `krytyczny` (`fire`)** przez
  `devbrain_todo_set_priority` samodzielnie — to decyzja zarezerwowana dla
  użytkownika; sam ustaw najwyżej `wysoki`.
- Nie zaznaczaj zadania jako zrobione (`devbrain_todo_done`) "na zapas" — tylko po
  faktycznym zakończeniu i weryfikacji pracy, albo na wyraźne polecenie użytkownika.
- Nie zapisuj plaintextu tokena w repo, w commitach, w logach ani w treści, którą
  przekazujesz dalej.
- Nie twórz nowej listy, jeśli odpowiednia lista już istnieje — zawsze najpierw
  sprawdź `devbrain_todo_lists`.

---

## 8. Typowe błędy

- **401** — zły/wygasły/unieważniony token, brak nagłówka `Authorization`.
- **400 "wymaga HTTPS"** — zapytanie po zwykłym HTTP.
- **404** — złe `listId`/`taskId` albo cudza prywatna lista (ta sama odpowiedź dla obu
  przypadków — serwer celowo nie zdradza istnienia cudzej prywatnej listy).
- **`-32601`** — literówka w nazwie narzędzia albo próba użycia narzędzia, które nie
  istnieje w zdalnym MCP (np. komend konsolowych pod inną nazwą).
- **`-32602`** — brak wymaganego argumentu, argument spoza kontraktu, albo
  `listId`/`taskId` przekazane jako string zamiast liczby JSON.
