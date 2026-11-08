---
name: komunikator
description: Use to tell Mariusz out loud via the `komunikator` CLI when a long task is done, his decision is needed, or work is blocked.
---

# Komunikator — powiadom Mariusza głosem

`komunikator` to program Mariusza na jego komputerze z Ubuntu. Mówi tekst na głos
(piper przez protokół Wyoming), odtwarza gotowe komunikaty i rozpoznaje mowę.
Cel: Mariusz nie musi pilnować ekranu. Agent woła go głosem, gdy naprawdę go potrzebuje.

## Najpierw sprawdź, czy w ogóle możesz

```bash
command -v komunikator >/dev/null || echo BRAK
```

- Jest: używaj zgodnie z tym plikiem.
- Brak (np. sesja w chmurze albo w maszynie wirtualnej): nie instaluj go, nie szukaj
  obejść. Napisz to samo zdanie w odpowiedzi tekstowej i idź dalej.

## Kiedy mówić

Tylko przy zdarzeniu, na które Mariusz musi zareagować:

1. **Skończyłem** dłuższe zadanie i czekam na kolejne polecenie.
2. **Potrzebna decyzja** — nie mogę rozsądnie iść dalej bez niego.
3. **Blokada** — błąd, brak dostępu, prośba o zgodę, coś się wysypało.

Nie mów przy każdym kroku, po krótkich odpowiedziach ani przy postępie pracy.
Jedno zdarzenie = jeden komunikat. Nie powtarzaj go w pętli.

## Jak mówić

```bash
komunikator powiedz "Mariusz, skończyłem. Czekam na Ciebie." \
  || komunikator odtworz gotowe
```

`||` daje awaryjny gotowy komunikat, gdy usługa mowy leży — `odtworz` gra nagranie
z dysku bez pipera.

Zasady treści:
- Po polsku, zaczynaj od „Mariusz,”.
- Najwyżej dwa krótkie zdania: co się stało + czego od niego chcesz.
- Powiedz, którego projektu dotyczy, jeśli pracuje kilka agentów naraz.
- Bez sekretów, haseł, tokenów, numerów kont.
- Bez szczegółów spraw sądowych, zdrowotnych i finansowych, nazwisk i kwot —
  głośnik słyszy cały pokój. Mów ogólnie, szczegóły zostaw w tekście.

Przykłady:
- `Mariusz, zadanie w Kancelarii gotowe do przeglądu.`
- `Mariusz, potrzebna Twoja decyzja w projekcie virral. Szczegóły na ekranie.`
- `Mariusz, utknąłem — brak dostępu do katalogu. Zajrzyj, proszę.`

## Kody wyjścia

| kod | znaczy | reakcja |
|---|---|---|
| 0 | powiedziane | nic |
| 1 | usługa mowy / odtwarzacz nie działa | spróbuj `odtworz`; gdy też padnie — tylko tekst |
| 2 | niewybrany głośnik albo złe polecenie | NIE wybieraj głośnika za Mariusza; napisz: „wybierz głośnik: `komunikator glosniki`, `komunikator glosnik N`” |
| 130 | przerwane | nie ponawiaj |

Błąd komunikatora nigdy nie przerywa właściwej pracy. Szczegóły:
`~/.local/state/komunikator/dziennik.log` (albo `KOMUNIKATOR_SZCZEGOLY=1`).

Nigdy nie uruchamiaj poleceń interaktywnych (`mikrofon`, `glosnik` bez numeru,
`klucz gemini`) — czekają na klawiaturę i zawieszą agenta.

## Gotowe komunikaty awaryjne (jednorazowo, za zgodą Mariusza)

Jeśli `komunikator lista` nie pokazuje tych nazw, zaproponuj ich zapisanie:

```bash
komunikator zapisz gotowe Mariusz, skończyłem. Czekam na Ciebie.
komunikator zapisz decyzja Mariusz, potrzebna Twoja decyzja.
komunikator zapisz blad Mariusz, coś poszło nie tak. Zajrzyj, proszę.
```

Dobieraj awaryjny komunikat do zdarzenia: `gotowe`, `decyzja`, `blad`.

## Inne możliwości (tylko na prośbę)

- `komunikator rozpoznaj plik.wav` — tekst z nagrania WAV (PCM) na stdout.
- `komunikator sluchaj 10` — nagrywa 10 s i wypisuje tekst; tylko gdy Mariusz wprost
  chce odpowiedzieć głosem.
- `komunikator sprawdz` — diagnostyka.

Pełna lista: `komunikator pomoc`. Dokumentacja: `INSTRUKCJA.md` w repo komunikatora.
