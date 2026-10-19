# dashboard_mcp — serwer MCP dla Live Dashboard

Mały, osobny serwer (nie ruszamy Symfony/devbrain backendu), który udostępnia
dane z dashboardu (karteczki, projekty, obserwowane domeny e-mail) każdemu
klientowi MCP (Model Context Protocol — protokół, którym różne AI/narzędzia
rozmawiają z zewnętrznymi usługami): Claude, inne AI, inne aplikacje.

Dziś te dane siedzą tylko w `localStorage` przeglądarki na karcie dashboardu —
czyli są prywatne dla jednej przeglądarki i nikt/nic z zewnątrz ich nie widzi.
Ten serwer trzyma te same dane w pliku SQLite (`dashboard.db`, tworzy się
automatycznie przy pierwszym uruchomieniu, zasiany domyślnymi danymi z
dashboardu), więc każdy klient podłączony do tego serwera widzi i może
zmieniać to samo, wspólne źródło.

## Co udostępnia (narzędzia / tools)

Odczyt (read-only):
- `dashboard_list_notes` — lista karteczek
- `dashboard_list_projects` — lista projektów ("żywe repo")
- `dashboard_list_watched_domains` — lista obserwowanych domen
- `dashboard_list_todos` — zadania TODO z głównej aplikacji devbrain (Symfony), patrz niżej
- `dashboard_get_snapshot` — wszystko naraz, jednym wywołaniem (zawiera też TODO)

Zapis (read/write):
- `dashboard_add_note`, `dashboard_delete_note`
- `dashboard_add_project`, `dashboard_update_project`, `dashboard_delete_project`
- `dashboard_add_watched_domain`, `dashboard_remove_watched_domain`

## Uruchomienie lokalnie

```bash
cd mcp-server
python3 -m venv venv        # venv = środowisko wirtualne (izolowane od reszty systemu)
source venv/bin/activate
pip install -r requirements.txt
python3 dashboard_mcp.py
```

Serwer gada przez stdio (standard input/output — standardowe wejście/wyjście,
najprostszy sposób komunikacji dla lokalnych narzędzi), więc nie zobaczysz
nic w konsoli poza ciszą — to normalne, czeka na połączenie od klienta MCP.
Zatrzymanie: Ctrl+C.

## Podłączenie jako connector (łącznik) w Claude Desktop / Claude Code

W pliku konfiguracyjnym Claude (Claude Desktop: Settings → Developer → Edit
Config; Claude Code: `claude mcp add`) dodaj wpis:

```json
{
  "mcpServers": {
    "dashboard_mcp": {
      "command": "python3",
      "args": ["/pełna/ścieżka/do/devbrain/mcp-server/dashboard_mcp.py"]
    }
  }
}
```

Albo z linii komend (Claude Code):

```bash
claude mcp add dashboard_mcp -- python3 /pełna/ścieżka/do/devbrain/mcp-server/dashboard_mcp.py
```

Po restarcie klienta narzędzia `dashboard_*` pojawią się na liście dostępnych
narzędzi — możesz poprosić dowolne AI z dostępem do tego serwera: "pokaż mi
moje karteczki z dashboardu" albo "dodaj notatkę: klucz X, wartość Y".

## Podłączenie w Cowork

W Cowork połączenia zewnętrzne (connectory) dodaje się przez `/mcp` w
interaktywnej sesji albo w ustawieniach aplikacji (Settings → Capabilities) —
tego kroku nie da się zrobić automatycznie z tej (nieinteraktywnej) sesji,
musisz go wykonać sam, podobnie jak przy autoryzacji GitHuba. Po dodaniu
serwera jako connectora, artefakt (widget) `live-dashboard.html` będzie
mógł wywoływać jego narzędzia przez `window.cowork.callMcpTool(...)` —
wtedy przepniemy kafelki Karteczki / Projekty / Obserwowane domeny z
`localStorage` na ten serwer, żeby dane były wspólne, a nie tylko w
Twojej przeglądarce.

## TODO z głównej aplikacji devbrain (`dashboard_list_todos`)

Ten serwer czyta (TYLKO DO ODCZYTU, `mode=ro`) zadania z bazy głównej
aplikacji devbrain (Symfony, `backend/`, plik `../data/devbrain.db` względem
tego skryptu) — bez kopiowania danych do własnej `dashboard.db`. Dzięki temu
dashboard może pokazać żywy stan TODO (np. listę "DevBrain — budowa") bez
dublowania źródła prawdy.

**Ważne — to jest podgląd, nie edycja:** ten serwer nigdy nie zapisuje do
`devbrain.db`. Żeby dodać/zarezerwować/oznaczyć/skomentować zadanie, użyj
OSOBNEGO serwera `devbrain-todo-mcp-server` (katalog `../devbrain-todo-mcp-server/`
w tym repo, narzędzia `devbrain_todo_*`) — opisany w `instrukcja-devbrain-todo.md`.
Dwa serwery = dwa różne cele: ten czyta i pokazuje na dashboardzie, tamten
zarządza zadaniami zgodnie z protokołem (numeracja, [CLAIMED], [PROPOZYCJA]).

Front-end (`dashboard/live-dashboard.html`) ma już kafelek TODO — woła
`dashboard_list_todos` przez `window.cowork.callMcpTool(...)`, tak samo jak
reszta kafelków, po podłączeniu tego serwera jako connectora (patrz sekcja
"Podłączenie w Cowork" wyżej). Dopóki connector nie jest podłączony, kafelek
pokazuje komunikat "serwer TODO nie jest jeszcze podłączony", zamiast się
wywalać.

## Ograniczenia dziś

- Transport to stdio — działa lokalnie, na jednej maszynie. Żeby było
  dostępne przez sieć (np. z telefonu albo z serwera devbrain.virral.tech),
  trzeba by przełączyć na transport `streamable_http` (obsługiwany przez
  FastMCP jedną linijką: `mcp.run(transport="streamable_http", port=8000)`)
  i wystawić port — to osobny, następny krok, jeśli będzie potrzebny.
- Baza `dashboard.db` (karteczki/projekty/domeny) nie jest jeszcze backupowana
  ani synchronizowana z bazą devbraina — to są dwa osobne pliki, i tak ma
  zostać: `devbrain.db` to źródło prawdy dla TODO, `dashboard.db` dla reszty
  kafelków. `dashboard_list_todos`/`dashboard_get_snapshot` łączą oba tylko
  przy odczycie, nic nie kopiują na stałe.

## Uwaga — ten katalog jest nieśledzony przez git

`mcp-server/` (i `dashboard/`) nie są jeszcze dodane do repozytorium git.
Raz już zdarzyło się, że operacje gita innego agenta pracującego równolegle
na tym samym folderze (checkout/reset między branchami) skasowały te pliki,
bo git nie chronił ich jako nieśledzonych. Zalecane: `git add mcp-server/
dashboard/ && git commit` przy najbliższej okazji, żeby to się nie powtórzyło.
