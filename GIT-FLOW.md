# GIT-FLOW.md — zasady pracy Git

## Złota zasada

Przed każdym commitem pełny zestaw testów wymagany przez repozytorium musi być zielony. Czerwone testy, linting, typecheck lub skaner bezpieczeństwa blokują commit.

## Start zadania

1. Przeczytaj: `README.md`, `STATUS.md`, `GIT-FLOW.md`, `AGENTS.md`, `HANDOFF.md`.
2. Zwykłe zadanie wykonuj na nowym branchu utworzonym z aktualnego `origin/develop`, potem scalasz i `push` tylko z `branch-a develop`!
3. Praca bezpośrednio na `develop` jest dozwolona wyłącznie po wyraźnej zgodzie Mariusza albo dla zadania oznaczonego przez niego jako `fire`.
4. Nie pracuj bezpośrednio na `master`.

## W trakcie zadania

1. Wykonuj tylko zakres zlecony przez Mariusza.
2. Nie dodawaj zależności, nie aktualizuj pakietów i nie refaktoryzuj pobocznych elementów bez zgody.
3. Nie odczytuj ani nie modyfikuj sekretów oraz nie analizuj dotenvx i skryptów środowiska — obowiązuje `AGENTS.md`.
4. Testy i kontrole jakości uruchamiaj poleceniami opisanymi w `README.md` i `REPO.md`, zgodnie z zasadami `AGENTS.md`.

## Commit

1. Przed commitem uruchom wymagane testy.
2. Nie commituj przy błędach.
3. Commit nie wymaga wyraźnej zgody Mariusza, o ile nie powie inaczej.
4. Nie zmieniaj `git config user.name` ani `git config user.email`.
5. Nie przywracaj plików bezpieczeństwa przez `git restore`, `git checkout --`, `git reset --hard` ani podobne mechanizmy.
6. Podpisuj się pod commit wzór `Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>`
7. Po commit z końcem zadania robisz scalanie lokalnego branch do develop. Po udanym push usuwasz branch lokalny

## Push i master

1. `git push` wymaga osobnej, wyraźnej zgody Mariusza w bieżącej rozmowie.
2. PUSH tylko develop żadnych innych branchy nie robimy push
3 `master` przyjmuje zmiany wyłącznie przez PR z zielonymi wymaganymi kontrolami i tylko na github się wykonuje.
4  Deploy wymaga osobnej zgody Mariusza.

## Dokumentacja po zadaniu

Jeżeli istnieją i zmiana tego wymaga, aktualizuj kolejno:

1. `HANDOFF.md` — krótka pałeczka dla następnej sesji.
2. `CHANGELOG.md` — trwała historia, append-only.
3. `STATUS.md` — aktualny stan i następny priorytet.
