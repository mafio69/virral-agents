# Autodeploy na produkcję (GitHub Actions + Tailscale)

Po każdym poprawnym scaleniu PR do gałęzi `master` i przejściu workflow **Tests** uruchamia się automatycznie workflow [`.github/workflows/deploy.yml`](../.github/workflows/deploy.yml), który łączy się z serwerem docelowym przez prywatną sieć **Tailscale** (OAuth Client, węzeł efemeryczny `tag:ci`) i uruchamia na serwerze:

1. `git fetch --prune origin master` + `git pull --ff-only origin master` (wyłącznie przy czystym drzewie na gałęzi `master`; `.env` i `var/` nie są dotykane),
2. warunkowy `composer install --no-dev` w kontenerze `app` (gdy zmienił się `composer.lock` lub brak `vendor/autoload.php`),
3. `docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d --force-recreate app` (migracje `bin/migrate.php` w `docker/start-app.sh` + weryfikacja, że `app` żyje),
4. `docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d --no-deps --force-recreate nginx`,
5. lokalny healthcheck HTTP 200 na `/api/ai-connections`.

Żadne adresy IP, nazwy użytkowników, ścieżki serwera ani klucze nie są zapisane w repozytorium — wszystko trafia wyłącznie do **GitHub Secrets** środowiska `production`.

---

## 1. Konfiguracja Tailscale (jednorazowo)

1. W panelu Tailscale → **Access Controls (ACL)** dodaj definicję tagu `tag:ci` oraz regułę dostępu wyłącznie na port SSH (`22`) do hosta produkcyjnego (zasada najmniejszych uprawnień):

   ```json
   {
     "tagOwners": {
       "tag:ci": ["autogroup:admin"]
     },
     "acls": [
       {
         "action": "accept",
         "src": ["tag:ci"],
         "dst": ["<twoj-serwer-lub-tag-prod>:22"]
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
cat ~/.ssh/virral_agents_deploy.pub >> ~/.ssh/authorized_keys
```

Pobierz odcisk hosta (wpis `known_hosts`) dla adresu Tailscale serwera (np. `100.x.y.z` lub nazwy MagicDNS; jeśli OpenSSH nasłuchuje na niestandardowym porcie, podaj `-p <PORT>`):

```bash
ssh-keyscan -p <PORT_SSH> -t ed25519 <ADRES_TAILSCALE_SERWERA>
```

## 3. Sekrety w GitHub (`Settings → Environments → production`)

Utwórz środowisko **`production`** i dodaj następujące **Environment secrets**:

| Nazwa sekretu | Wartość |
|---|---|
| `TS_OAUTH_CLIENT_ID` | Client ID z Tailscale OAuth |
| `TS_OAUTH_SECRET` | Client Secret z Tailscale OAuth |
| `DEPLOY_HOST` | Adres Tailscale serwera (np. `100.x.y.z` lub nazwa MagicDNS) |
| `DEPLOY_PORT` | *(opcjonalny, domyślnie `22`)* Port OpenSSH na serwerze (np. `2222`, gdy `22` zajmuje Tailscale SSH) |
| `DEPLOY_USER` | Nazwa użytkownika SSH na serwerze |
| `DEPLOY_PATH` | Bezwzględna ścieżka katalogu repozytorium na serwerze |
| `DEPLOY_SSH_KEY` | Zawartość prywatnego klucza (`~/.ssh/virral_agents_deploy`) |
| `DEPLOY_SSH_KNOWN_HOSTS` | Wynik `ssh-keyscan -p <PORT_SSH> -t ed25519 <DEPLOY_HOST>` |

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
