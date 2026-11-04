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

## 2026-11-04 — koniec sesji; jutro #62-486 (E2.2)

- **Stan:** develop = `5fb0697` (pushnięty): T6.1+T6.2 (filtry wyszukiwania), T3.1+T3.2 (kompakt historii), fix Gemini MAX_TOKENS (częściowe odpowiedzi modeli rozumujących). DT: 519/520/524/525 done z notatkami; 513/514/515 odhaczone na prośbę właściciela (notatki nie weszły — WAF blokuje pythona, curl z polskimi znakami zwracał zły JSON; done flagi są).
- **Jutro (zlecane przez właściciela): #62-486 (E2.2)** — realistyczny User-Agent i Accept-Language w `PublicWebPageFetcher`, wartości domyślne w `config/app.php` przez DI (bez sekretów); nadal bez cookies/JS/subresource. Plan + DoD w `TODO-zrodla.md` (E2.2). Branch `feature/T6-486`? — konwencja: `feature/E2.2` wg TODO-zrodla ("branch z origin/develop"). `[CLAIMED]` na DT przed startem.
- **Po E2.2 w kolejce:** #62-490 (E3.1 ekstrakcja main/article — też fetcher), #62-542 (data/dzień tygodnia w prompcie systemowym — małe), #62-494 (SSE — duży, osobna sesja), T2.1–T2.3 (tryby narzędzi — świadoma decyzja właściciela o trybie auto).
- **Wstrzymane (właściciel):** rotacja MCP — serwer w repo `szukaj` nie działa; po postawieniu: `sudo docker compose up -d --force-recreate app`, weryfikacja `GET /api/search/status` (zalogowany) + jedno wyszukanie; zdjąć „WSTRZYMANA" ze STATUS. Po deployu też: manualny test filtrów w UI i `gemini-3.8-flash`.
- **Pułapki:**
  - Właściciel nie kopiuje z terminala → pliki pomocnicze w `var/` (`t.txt`, `m.txt`, `rotacja-mcp.txt`, `TEST-T3.2-instrukcja.txt` — do usunięcia).
  - E2E: `E2E_MAILBOX_URL=http://127.0.0.1:8025 E2E_SMTP_DSN=smtp://127.0.0.1:1025 npm run test:e2e`; UI lokalnie `node --test --experimental-test-isolation=none`.
  - Push: `git -c credential.helper='!gh auth git-credential' push origin develop`.
  - DT przez curl z ASCII-only w notatkach (polskie znaki potrafią zwrócić -32700); python urllib dostaje 403 (WAF).
  - Kolejka: po porządkach pozostały otwarte wysokie: 486, 490, 494, 495, 516–518, 542.
