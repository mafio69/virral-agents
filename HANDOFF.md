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

## 2026-11-22 — Listy wyszukiwań (historyczne + doraźne), Smart UX czatu i przycisk `+`

- **Gałąź:** `develop` (scalone `feat/smart-search-lists` i pushnięte na `origin/develop`, otwarty PR do `master`). Walidacja: formatter 0/200, PHPUnit 326/326 (1482 asercji), UI 118/118.
- **Zrobione w tej sesji:**
  - Backend list wyszukiwań (`ResearchService`, `ResearchRepository`, `ResearchInputMapper`, `ResearchReplyGenerator`): `state['lists']` z trybami `pool`/`ephemeral` + `saved`, cache pobranych stron (`pages`), operacje na listach w `PUT /api/research/sources` (checkbox listy, utrwalenie, kosz `delete_list`), `clearEphemeral()` po odpowiedzi AI zamiast kasowania wszystkiego.
  - Smart UX czatu (`chat.html`, `chat-research.css`, `chat-research.js`, `chat-research-modal.js`, `chat-app.js`): domyślnie zwinięty panel wyszukiwania (`#search-panel`) i wszystkie filtry pod `Filtry`, automatyczne chowanie UX przy wysyłce/odpowiedzi AI bez kasowania wpisanego promptu, karty list z checkboxem, `Zapisz / Utrwal` i koszem `🗑 Usuń`, lżejszy modal (`data-mode="approval"` 460 px / wyniki 640 px) z 3 akcjami w stopce (`Nowa sesja` → `POST /api/research/new-chat`, `Nowy prompt`, `Kontynuuj`).
  - Przycisk `+` w polu promptu (`chat-controller.js`, `chat-app.js`): obraz (PNG/JPEG/WebP dla vision) lub plik tekstowy (do 12 KB tekstu dla każdego modelu) z zachowaniem tekstu promptu.
  - Porządek zmiennych środowiska: jedno `APP_PORT` zamiast `APP_HOST_PORT`, usunięte zbędne `LANGFUSE_HOST`, `LANGFUSE_RELEASE` i `PHP_IDE_CONFIG` z `docker-compose.yml`, `php-fpm-env.conf` i `src/settings.php`.
  - Dostawcy AI (`OpenRouter`, `DeepSeek`, `OpenAI`, `Anthropic`): minimum `8192` tokenów wyjścia trwale w kodzie + w `OpenRouterProvider` scalanie sąsiednich wiadomości tej samej roli (Perplexity Sonar) i mapowanie kodów błędów JSON.
- **Od czego zacząć:**
  1. Po zielonym CI na PR `develop` → `master` i akceptacji Mariusza: merge PR na GitHub, sprawdzenie `APP_PORT` / `LANGFUSE_BASE_URL` w `.env` produkcji i deploy (osobna zgoda).
  2. Kolejne z `STATUS.md`: **Faza 4** (UI panel „Dostawcy AI"), **Faza 3** (generalizacja rejestru DB), naprawa `TaskController` (`int $id`).
- **Pułapki:**
  - W nieśledzonych plikach roboczych na dysku były wcześniej `agents.code-workspace`, `docs/INSTRUCTIONS.md`, `docs/INSTRUKCJA.md`, `docs/Makefile`, `favicon.svg` — nie dodawać ich przypadkiem do commita (`git add` wyłącznie zmienionych plików zadania).
  - Po zmianach w `public/ui/` podbito `?v=20261122-smart-ux` w `chat.html` i `chat-app.js`.
  - `ControllerInvoker` (PHP-DI) nie rzutuje stringa `{id}` na `int` — używać `(int)$request->getAttribute('id')` (latent bug w `TaskController`).
  - Push wyłącznie dla `develop` (za zgodą Mariusza); zwykłe gałęzie scalamy lokalnie, `develop` → `master` tylko przez PR na GitHub.
