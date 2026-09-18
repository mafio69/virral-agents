# HANDOFF.md — standard prowadzenia (obowiązuje we wszystkich repo)

> Wzorzec, jak prowadzić plik `HANDOFF.md`. Po co: żeby nikt (nawet Mariusz) nie musiał się domyślać, co to jest.

---

## Czym jest HANDOFF.md (jednym zdaniem)

**Pałeczka sztafetowa na JEDNĄ sesję.** „Wchodzisz w projekt — przeczytaj to i od razu wiesz, od czego zacząć TERAZ, jak odpalić i na co uważać." Nic więcej.

- **Wymazywany do zera i pisany od nowa** na koniec (albo początek) każdej sesji. Nie rośnie, nie ma historii.
- To, co warte zapamiętania na stałe → przenieś do `CHANGELOG.md` (historia) albo `STATUS.md` (stan), **zanim wymażesz HANDOFF**.

### Trzy pliki — kto do czego (żeby nie mylić)
- `CHANGELOG.md` — **historia**, append-only, rośnie.
- `STATUS.md` — **stan na teraz**, nadpisywany, krótki.
- `HANDOFF.md` — **start następnej sesji**, wyzerowany i pisany od nowa.

## Jak prowadzić (zasady twarde)

1. **Zeruj i pisz świeżo** — jedna sesja, jedna zawartość. Stare treści nie zostają.
2. **Zanim wyzerujesz** — cokolwiek trwałego (decyzje, zrobione rzeczy) przenieś do CHANGELOG/STATUS.
3. **Kto:** wykonawca („młody") na koniec swojej pracy. Mózg pilnuje spójności.
4. **Pierwsza linia:** `Sesja: YYYY-MM-DD (autor/model)`.
5. **Piszesz DLA NASTĘPNEGO** (człowiek albo AI, który nie zna kontekstu) — konkretnie, po polsku (nazwy techniczne EN).
6. **Sekcje wymagane:**
   1. **Co właśnie zrobiłem** — 3–6 punktów.
   2. **Gdzie dokładnie jestem** — branch, plik, ostatni commit/hash.
   3. **Od czego zacząć (następny krok)** — jeden, konkretny.
   4. **Jak odpalić / zweryfikować** — komendy, URL, port, jak sprawdzić że działa.
   5. **Pułapki / uwagi** — co może wysadzić, na co uważać (np. „restart serwera, bo tsx nie przeładowuje backendu").

### Szablon (kopiuj)

```markdown
# HAND-OFF

Sesja: 2026-08-24 (haiku)

## Co właśnie zrobiłem
- ...

## Gdzie dokładnie jestem
- branch: `...`, ostatni commit `...`, plik `...`

## Od czego zacząć
1. ...

## Jak odpalić / zweryfikować
- `komenda` → oczekiwany wynik.

## Pułapki
- ...
```
