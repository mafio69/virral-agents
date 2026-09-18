# STATUS.md — standard prowadzenia (obowiązuje we wszystkich repo)

> Wzorzec, jak prowadzić plik `STATUS.md` w każdym repo. Po co: żeby nie tłumaczyć tego za każdym razem.

---

## Czym jest STATUS.md (i czym NIE jest)

- **STATUS = zawsze aktualny, KRÓTKI stan „gdzie jesteśmy teraz".** Jeden ekran. To pierwszy plik, który czyta ktokolwiek wchodzący w projekt (człowiek albo AI).
- **NADPISYWANY, nie dopisywany.** Zawsze prawda na dziś. Historia NIE tu — od tego jest `CHANGELOG.md`.
- Różnica od pozostałych:
  - `CHANGELOG.md` — **historia**, rośnie, append-only, „co się wydarzyło". tylko dodajemy kolejne lienie tekstu nigdy nadpisywanie!
  - `STATUS.md` — **teraźniejszość**, nadpisywany, „gdzie jesteśmy i co dalej".
  - `HANDOFF.md` — **pałeczka na jedną sesję**, wymazywany do zera i pisany od nowa, „od czego konkretnie zaczyna następny".

## Jak prowadzić (zasady twarde)

1. **Nadpisuj, trzymaj krótko** — maks ~1 ekran. Za długie → skróć, nadmiar do CHANGELOG.
2. **Pierwsza linia zawsze:** `Ostatnia aktualizacja: YYYY-MM-DD (autor/model)`.
3. **Aktualizuj po każdym `git push`** (Zasada #5 z AGENTS.md) i przy każdej zmianie planu.
4. **Kto:** wykonawca („młody"). Mózg pilnuje spójności.
5. **Sekcje wymagane** (pomiń pustą, ale trzymaj kolejność):
   1. **STAN (skrót)** — 5–8 punktów: co zrobione, co w toku.
   2. **Gałęzie** — branch bieżący, co na `develop`/`master`.
   3. **Następne kroki** — kolejka, w kolejności.
   4. **Czeka na Mariusza** — blokery po stronie człowieka (klucze, włączenie API, decyzje).
   5. **Wskazówki** — dokąd po szczegóły (`CHANGELOG.md`, `docs/…`, `HANDOFF.md`).
6. **Bez historii i bez opowieści** — punktowo, konkretnie, po polsku (nazwy techniczne EN).

### Szablon (kopiuj)

```markdown
# Status — <nazwa projektu>

Ostatnia aktualizacja: 2026-08-24 (haiku)

## Stan (skrót)
- ...

## Gałęzie
- `master` — ...
- `develop` — ...
- bieżący branch: `...`

## Następne kroki
1. ...

## Czeka na Mariusza
- ...

## Szczegóły
- Historia → `CHANGELOG.md`. Start następnej sesji → `HANDOFF.md`.
```
