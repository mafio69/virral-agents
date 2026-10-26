# Starter admin account (development only)

| Field    | Value                                                    |
|----------|----------------------------------------------------------|
| Email    | `admin@mafio.pl`                                         |
| Password | Loaded from local `DEV_ADMIN_PASSWORD` (min. 10 chars)   |

This account is for the **local development environment only**. The password is never stored in
the repository or printed to terminal output; set `DEV_ADMIN_PASSWORD` locally in `.env` (or pass
it in your shell environment when running the restore script) and change it after logging in.

## Restoring the account

```bash
bin/restoreDevPass.sh
```

The script runs `bin/restore-dev-user.php` inside the `app` container (needs `./dev-start.sh` running):

- Requires `DEV_ADMIN_PASSWORD` (at least 10 characters) in the local environment.
- If `admin@mafio.pl` exists: updates its password hash from `DEV_ADMIN_PASSWORD`.
- If it does not exist: creates it with the password from `DEV_ADMIN_PASSWORD`.
- Marks the email as confirmed (login requires it, so no SMTP is needed), invalidates the
  account's sessions and clears the failed-login limits.
- If the application runs with `APP_ENV` other than `dev` (including `prod`), it aborts with
  `prod operacja nie dozwolona` and changes nothing.
