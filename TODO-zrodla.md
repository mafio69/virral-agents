# Virral Agents — jakość źródeł badania (lista DevBrain-Todo)

> Kontekst: GLM 5.3 (Z.ai) w aplikacji zgłaszał strony nieładujące się i obcięte treści
> w trybie badania źródeł. Diagnoza: `src/Infrastructure/Search/PublicWebPageFetcher.php`
> (brak redirectów, UA blokowany przez portale, wąskie Content-Type, brak gzip, hardcoded
> 16 000 znaków) oraz `src/Service/ResearchService.php` (budżet 100 000 znaków, binarna
> flaga `truncated`, jeden status `failed` bez przyczyny).
>
> Zakres: etapy 1–3 zatwierdzone przez właściciela 2026-10-18 (E1 diagnostyka dla modelu,
> E2 skuteczność pobierania, E3 jakość treści).
>
> Zasady per zadanie: branch z `origin/develop`, pełne testy + `composer check-all` przed
> commitem, aktualizacja `REPO.md` przy zmianie limitów/mechanizmów, commit wg
> `GIT-FLOW.md`, push wyłącznie `develop` i tylko za zgodą właściciela.
>
> Wyszukiwanie: lokalny SearXNG przez MCP `mcp-search` (max_results ≤ 10 po stronie
> kontraktu serwera). Zadanie 000 opisuje całość przedsięwzięcia.

---

## 000 — Cel i zakres: jakość źródeł badania dla czatu AI (E1–E3)

**Priorytet: wysoki**

Cel całej listy: naprawić jakość źródeł w trybie badania — GLM 5.3 (Z.ai) zgłasza strony
nieładujące się i obcięte treści. Przyczyny zdiagnozowane w
`src/Infrastructure/Search/PublicWebPageFetcher.php` (`allow_redirects=false`,
UA `VirralAgents-Research/1.0` blokowany 403, tylko `text/html`/`text/plain`, brak gzip,
hardcoded 16 000 znaków) oraz `src/Service/ResearchService.php` (budżet 100 000,
binarna flaga `truncated`, brak kodów przyczyn `failed`).

Kolejność: E1 (diagnostyka) → E2 (skuteczność fetch) → E3 (jakość treści).

Definition of done listy: wszystkie zadania done, pełne testy PHP + UI zielone,
`composer check-all` czysty, REPO.md zaktualizowane tam, gdzie zmieniają się
limity/mechanizmy, commity scalone lokalnie do develop wg GIT-FLOW.md, push wyłącznie
develop i tylko za zgodą właściciela.

---

## Etap 1 — Diagnostyka dla modelu

### E1.1 Kody przyczyn nieudanego fetch widoczne w UI i evidence JSON

**Priorytet: wysoki**

`PublicWebPageFetcher` rzuca dziś jeden `SearchException` dla każdej przyczyny
niepowodzenia. Rozróżnić kody przyczyn (np. `dns`, `blocked_403`, `redirect`,
`unsupported_type`, `too_large`, `timeout`, `empty_js_only`, `encoding`), zapisać
w `page.reason` źródła w `ResearchService`, pokazać w ResearchPanel/UI oraz przekazać
w evidence JSON, aby model AI mógł reagować (np. wybrać inne źródło zamiast zgadywać).
Zamknięta lista kodów, bez treści odpowiedzi serwera. Testy jednostkowe na atrapach HTTP
dla każdej przyczyny.

### E1.2 Snippet wyniku wyszukiwania zawsze w evidence JSON

**Priorytet: normalny**

`ResearchService::evidence()` przekazuje wyłącznie tekst pobranych stron. Dodać snippet
(do 400 znaków) oraz `title` z wyników wyszukiwania dla każdego źródła także przy
statusach `failed`/`denied`, żeby model miał minimum kontekstu nawet bez pobrania strony.
Bez zmian szyfrowanego magazynu poza rozszerzeniem struktury evidence.

---

## Etap 2 — Skuteczność pobierania

### E2.1 Bezpieczne śledzenie redirectów (max 3 hop-y, pełna rewalidacja SSRF)

**Priorytet: wysoki**

`PublicWebPageFetcher` ma `allow_redirects=false`, więc każdy redirect (http→https,
www→apex, kanoniczne) kończy się błędem — główna przyczyna „strona się nie wczytała".
Wdrożyć śledzenie maksymalnie 3 przekierowań, również cross-host (zatwierdzone przez
właściciela): każdy hop ponownie przez `PublicWebUrl::normalize`, świeże DNS, kontrola
publicznego IPv4, zakaz downgradu https→http, bez cookies i forwardowania credentials.
Zachować pełną ochronę SSRF. Testy: legalne redirecty (w tym cross-host), pętla
redirectów, downgrade, redirect na adres prywatny/lokalny.

### E2.2 Realistyczny User-Agent i Accept-Language konfigurowalne w config/app.php

**Priorytet: wysoki**

Zastąpić UA `VirralAgents-Research/1.0` (blokowany 403 przez portale, np. Gazeta.pl) typowym
User-Agent przeglądarki oraz dodać `Accept-Language`. Wartości domyślne w
`config/app.php`, przekazane przez DI, bez zmiany plików środowiska i sekretów. Fetch
nadal bez cookies, bez JS, bez subresource (zatwierdzone przez właściciela). Testy:
nagłówki widoczne w atrapie HTTP.

### E2.3 Akceptacja application/xhtml+xml i rodzin text/*

**Priorytet: normalny**

Rozszerzyć dozwolone Content-Type w `PublicWebPageFetcher` o `application/xhtml+xml`
oraz inne `text/*` (poza dotychczasowymi html/plain), z zachowaniem limitów rozmiaru
i bezpiecznego parsowania (xhtml parsowany jak HTML przez DOMDocument, bez dostępów
do sieci). Testy pozytywne i negatywne (np. `application/pdf`, `image/*`).

### E2.4 Obsługa gzip i jeden retry na timeout/5xx

**Priorytet: normalny**

Wysyłać `Accept-Encoding: gzip` i dekompresować odpowiedź (efektywnie więcej realnej
treści w limicie 1 MiB; odrzucenie odpowiedzi z niezamawianym kodowaniem pozostaje).
Jeden powtórny attempt przy timeout oraz 5xx/429 (z uwzględnieniem Retry-After jeśli
obecne), bez ponawiania przy błędach walidacji. Testy na atrapach: gzip poprawny,
uszkodzony, retry, brak retry przy 403.

### E2.5 Wykrywanie charset z meta tagów gdy brak nagłówka

**Priorytet: niski**

Gdy nagłówek Content-Type nie zawiera charset, odczytać `<meta charset>` /
`<meta http-equiv=content-type>` z surowego HTML przed konwersją; rozszerzyć listę
obsługiwanych kodowań. Testy na fikcyjnych stronach windows-1250 bez nagłówka.

---

## Etap 3 — Jakość treści

### E3.1 Ekstrakcja treści main/article i cięcie na granicy akapitów

**Priorytet: wysoki**

Priorytetowo ekstrahować `//main`, `//article`, `[role=main]` (fallback na dotychczasowe
całe body); usuwać dodatkowo `aside`/`header`/`figure` oraz typowe banery consent przed
wyciąganiem tekstu; skrócenie do limitu znaków wykonywać na granicy akapitu, nie
w środku zdania, z flagą `truncated`. Efekt: więcej realnej treści w tym samym limicie.
Testy jednostkowe na fixture HTML z boilerplate.

### E3.2 Limity konfigurowalne: 32k znaków na stronę i 200k budżetu kontekstu

**Priorytet: normalny**

Obecnie hardcoded 16 000 w `PublicWebPageFetcher` i 100 000 w
`ResearchService::evidence()`. Przenieść do `config/app.php` z domyślnymi
32 000/200 000 (zatwierdzone przez właściciela), przekazać przez DI do fetchera
i serwisu. Zaktualizować REPO.md (sekcje limitów) i testy na nowe wartości graniczne.

### E3.3 original_length/kept_length w evidence zamiast binarnej flagi truncated

**Priorytet: normalny**

Zwracać w evidence JSON dla każdego źródła `original_length` oraz `kept_length`, aby
model znał skalę straty treści; zachować flagę `truncated` dla kompatybilności UI.
Aktualizacja `ResearchService::evidence()` i testów; dokumentacja pola w REPO.md/docs
jeśli wymagana.

### E3.4 Aktualizacja tytułu źródła z <title>/<h1> pobranej strony

**Priorytet: niski**

Po udanym fetch zaktualizować `title` źródła w szyfrowanym workspace z `<title>`
lub pierwszego `<h1>` (obcięte do 300 znaków), zamiast pozostawania przy tytule
z wyników wyszukiwania — lepsze cytowania w odpowiedziach AI. Testy jednostkowe.
