# Autodeploy na produkcję (GitHub Actions + Tailscale)

Po każdym poprawnym scaleniu PR do gałęzi `master` i przejściu workflow **Tests** uruchamia się automatycznie workflow [`.github/workflows/deploy.yml`](../.github/workflows/deploy.yml), który łączy się z serwerem docelowym przez prywatną sieć **Tailscale** (OAuth Client, węzeł efemeryczny `tag:ci`) i uruchamia na serwerze:

1. `git fetch --prune origin master` + aktualizacja dokładnego przetestowanego commita `git merge --ff-only <head_sha>` (wyłącznie przy czystym drzewie na gałęzi `master`, z pomijaniem przestarzałych uruchomień; `.env` i `var/` nie są dotykane),
2. `docker compose -f docker-compose.yml -f docker-compose.prod.yml build app` (przebudowa obrazu `app` przed instalacją zależności i restartem),
3. warunkowy `composer install --no-dev` w kontenerze `app` (gdy brak `vendor/autoload.php` lub znacznik `vendor/.deploy-composer-lock` nie zgadza się z aktualnym `composer.lock`),
4. `docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d --force-recreate app` (migracje `bin/migrate.php` w `docker/start-app.sh` + weryfikacja, że `app` żyje),
5. `docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d --no-deps --force-recreate nginx`,
6. lokalny healthcheck wymagający poprawnego transferu i dokładnie HTTP `200` na `/api/ai-connections`.

Żadne adresy IP, nazwy użytkowników, ścieżki serwera ani klucze nie są zapisane w repozytorium — wszystko trafia wyłącznie do **GitHub Secrets** środowiska `production`.

---

## 1. Konfiguracja Tailscale (jednorazowo)

1. W panelu Tailscale → **Access Controls (ACL)** dodaj definicję tagu `tag:ci` oraz regułę dostępu wyłącznie na pojedynczy port SSH zgodny z sekretem `DEPLOY_PORT` (domyślnie `22` — nie zezwalaj na wszystkie porty `*`) do hosta produkcyjnego (zasada najmniejszych uprawnień):

   ```json
   {
     "tagOwners": {
       "tag:ci": ["autogroup:admin"]
     },
     "acls": [
       {
         "action": "accept",
         "src": ["tag:ci"],
         "dst": ["<twoj-serwer-lub-tag-prod>:<DEPLOY_PORT_LUB_22>"]
       }
     ]
   }
   ```

2. W panelu Tailscale → **Settings → OAuth clients → Generate OAuth client**:
   - uprawnienie: **Devices → Core → Write**,
   - tag: **`tag:ci`**.
   - Zapisz `Client ID` (`TS_OAUTH_CLIENT_ID`) oraz `Client secret` (`TS_OAUTH_SECRET`). Klient OAuth nie wygasa po 90 dniach, a węzły CI są efemeryczne i znikają zaraz po zakończeniu zadania.

## 2. Dedykowany klucz SSH do deployu (na serwerze)

Utwórz osobny klucz `ed25519` wyłącznie do deployu (nie używaj prywatnego klucza codziennego):

```bash
ssh-keygen -t ed25519 -N "" -C "github-actions-deploy" -f ~/.ssh/virral_agents_deploy
```

### Wymuszona komenda (wzór guardiana)

Klucz CI **nie dostaje powłoki**. Każde połączenie tym kluczem uruchamia tylko skrypt
[`ops/deploy-virral-agents`](../ops/deploy-virral-agents), który przyjmuje wyłącznie `"<pełny hash commita> auto|manual"`
i odrzuca każdą inną komendę. Cała logika wdrożenia (czyste drzewo na `master`, `fetch`, `merge --ff-only`,
`bin/deploy-prod.sh`) i ścieżka repo są po stronie serwera.

1. Skrypt jako root, bez prawa zapisu dla konta wdrożeniowego:

   ```bash
   sudo install -o root -g root -m 755 ops/deploy-virral-agents /usr/local/bin/deploy-virral-agents
   ```

2. W `~/.ssh/authorized_keys` konta `DEPLOY_USER` **jedna linia** dla klucza CI — z ograniczeniami i ścieżką repo
   (ścieżka zostaje tylko na serwerze, nie w repozytorium):

   ```text
   restrict,from="100.64.0.0/10",command="/usr/local/bin/deploy-virral-agents /BEZWZGLEDNA/SCIEZKA/REPO" ssh-ed25519 AAAA... github-actions-deploy
   ```

   Jeśli klucz był wcześniej dopisany bez ograniczeń, **zamień** tamtą linię na powyższą (nie zostawiaj obu).

3. Test na serwerze, jako `DEPLOY_USER`:

   ```bash
   SSH_ORIGINAL_COMMAND="id" /usr/local/bin/deploy-virral-agents /BEZWZGLEDNA/SCIEZKA/REPO   # -> odrzucone
   ```

Uwaga na kolejność: nowy workflow (wysyła sam hash) i wymuszona komenda muszą wejść **razem**.
Stary workflow wysyła cały skrypt — wymuszona komenda go odrzuci; nowy workflow bez wymuszonej komendy też nie zadziała.

Odcisk hosta ED25519 (`known_hosts`) jest pobierany automatycznie wewnątrz uwierzytelnionego tunelu WireGuard Tailscale (`ssh-keyscan`) — nie trzeba go utrzymywać ręcznie jako osobnego sekretu.

## 3. Sekrety w GitHub (`Settings → Environments → production`)

Utwórz środowisko **`production`** i dodaj następujące **Environment secrets**:

| Nazwa sekretu | Wartość |
|---|---|
| `TS_OAUTH_CLIENT_ID` | Client ID z Tailscale OAuth |
| `TS_OAUTH_SECRET` | Client Secret z Tailscale OAuth |
| `DEPLOY_HOST` | Adres Tailscale serwera (np. `100.x.y.z` lub nazwa MagicDNS) |
| `DEPLOY_PORT` | *(opcjonalny, domyślnie `22`)* Port OpenSSH na serwerze (np. `2222`, gdy `22` zajmuje Tailscale SSH) |
| `DEPLOY_USER` | Nazwa użytkownika SSH na serwerze |
| `DEPLOY_SSH_KEY` | Zawartość prywatnego klucza (`~/.ssh/virral_agents_deploy`) |

> Opcjonalnie: w ustawieniach środowiska `production` możesz włączyć **Required reviewers**, jeśli zechcesz ręcznie zatwierdzać wdrożenie jednym kliknięciem przed jego startem.

## 4. Jednorazowe sprawdzenie serwera przed pierwszym deployem

Na serwerze w katalogu aplikacji upewnij się, że:
1. Repozytorium jest na gałęzi `master` (`git branch --show-current` → `master`), ma czyste pliki śledzone (`git status -sb`) oraz skonfigurowany dostęp tylko do odczytu (`git fetch origin master` przechodzi bez interaktywnego hasła — np. przez HTTPS lub klucz deploy key read-only).
2. W lokalnym `.env` (nigdy nie commitować!) ustawione są:
   - `APP_PORT` (jeśli używasz innego portu niż domyślne `8181`),
   - `LANGFUSE_BASE_URL` (jeśli korzystasz z Langfuse).
3. Użytkownik `DEPLOY_USER` może uruchamiać `docker compose` bez interaktywnego `sudo` (rootless Docker: skrypt automatycznie wykrywa `/run/user/$(id -u)/docker.sock` oraz `APP_UID=0`).

## 5. Ręczny podgląd i testowanie

- Podgląd komend bez ich uruchamiania: `./bin/deploy-prod.sh --dry-run`
- Ponowne ręczne uruchomienie w GitHub Actions: zakładka **Actions → Deploy Production → Run workflow** (na gałęzi `master`).
