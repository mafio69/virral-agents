# Starter admin account (development only)

| Field    | Value            |
|----------|------------------|
| Email    | `admin@mafio.pl` |
| Password | `admin4567-=`       |

This is a **starter password for the development environment**. It is public in this
repository, so change it **immediately after the first login**. Never use it in production.

## Restoring the account

```bash
bin/restoreDevPass.sh
```

The script runs `bin/restore-dev-user.php` inside the `app` container (needs `./dev-start.sh` running):

- If `admin@mafio.pl` exists: resets the password to `admin4567-=`.
- If it does not exist: creates it with `admin4567-=`.
- Marks the email as confirmed (login requires it, so no SMTP is needed), invalidates the
  account's sessions and clears the failed-login limits.
- If the application runs with `APP_ENV` other than `dev` (including `prod`), it aborts with
  `prod operacja nie dozwolona` and changes nothing.

The starter password (11 characters) meets the 10-character minimum of the registration form.
