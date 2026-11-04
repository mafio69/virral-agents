# HANDOFF

## Jak prowadzić

- Pałeczka na **jedną** kolejną sesję. Na starcie sesji przeczytaj, na końcu **wyzeruj i napisz od nowa** — bez dopisywania pod spodem.
- Maksymalnie ~30 linii. Tylko to, czego następna sesja nie odczyta szybko z kodu, `STATUS.md` ani `git status`.
- Historia trafia do `CHANGELOG.md`, stan projektu do `STATUS.md`, trwałe mechanizmy do `REPO.md`.
- Bez sekretów, haseł, tokenów i ścieżek do nich.

Szablon:

```markdown
## RRRR-MM-DD — <temat sesji>
- Gałąź / ostatni commit:
- Zrobione w tej sesji (niezacommitowane lub niezweryfikowane):
- Od czego zacząć:
- Pułapki:
```

---

## 2026-11-03 — T3.2 pomiar zrobiony (czeka na commit + push)

- **Stan:** develop zawiera T6.1+T6.2+T3.1 (wszystko pushnięte). T3.2 (#62-520) wykonane z właścicielem: A/B 12 pytań, ostatnia odpowiedź 1465/55 → 1331/61 (−134 tok. wej., −9,2%); wpisy STATUS+CHANGELOG **niezacommitowane**. Aplikacja lokalnie znowu na develop (bind-mount), kontener po restarcie z serii B.
- **Nowy bug (priorytet 1 w STATUS):** Gemini 3.8 Flash → `finishReason ≠ STOP` (prawdopodobnie `MAX_TOKENS`: sztywny `maxOutputTokens=4096` u `GeminiProvider.php:132` współdzielony z tokenami myślenia) → cała odpowiedź odrzucana jako `invalid_response`. Naprawa: obsługa thinking (thinkingConfig / wyższy limit / akceptacja partial text przy `MAX_TOKENS` + testy na atrapie).
- **Od czego zacząć:**
  1. Commit dokumentów T3.2 (STATUS/CHANGELOG/HANDOFF), ff do develop (jesteśmy na develop — zwykły commit), push przez `git -c credential.helper='!gh auth git-credential' push origin develop`, `devbrain_todo_done` 520 + notatka.
  2. Zaproponować właścicielowi zadanie DT na bug Gemini 3.8 i je zrobić (albo #62-486 E2.2 — też wysoki priorytet).
- **Pułapki:**
  - Pomiar A/B odbywał się na `git switch --detach 276ef52` (A) i develop (B) + `sudo docker compose restart app` — kod jest bind-mountowany, opcache domyślnie waliduje pliki. Właściciel restartuje kontener (sudo jest jego).
  - Właściciel nie może kopiować z terminala opencode → wszystko do skopiowania ląduje w pliku (ostatnio `var/t.txt`; do usunięcia razem z `var/TEST-T3.2-instrukcja.txt`).
  - Wczytanie z bazy przez agenta odrzucone przez właściciela — licznik UI („wej./wyj.") wystarcza.
  - Model w pomiarze niepotwierdzony nazwą (właściciel nie podał); założenie: ten sam w obu seriach (instrukcja to podkreślała).
