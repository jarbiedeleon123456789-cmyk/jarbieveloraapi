# Velora Parts API (LavaLust)

LavaLust 4 REST API for the Laboratory Exercise 6 Product Management System,
plus the Database Migration laboratory (CLI command, controller, routes).

- **Auth:** JWT access token + rotating refresh token (LavaLust `api` library)
- **Database:** MySQL (Aiven in production)
- **Deploy:** Docker web service on Render

## Endpoints

| Method | Path | Auth | Purpose |
|---|---|---|---|
| POST | `/api/auth/login` | – | Log in with email or username; returns user + tokens |
| POST | `/api/auth/refresh` | – | Exchange a refresh token for new tokens |
| GET | `/api/auth/me` | JWT | Current user |
| POST | `/api/auth/logout` | – | Revoke a refresh token |
| GET | `/api/users`, `/api/users/{id}` | Admin | List / show accounts |
| POST | `/api/users` | Admin | Create an account |
| PUT / PATCH | `/api/users/{id}` | Admin | Update account details, role, status, or password |
| DELETE | `/api/users/{id}` | Admin | Delete another account |
| GET | `/api/catalog`, `/api/catalog/categories` | – | Public read-only storefront |
| GET | `/api/products`, `/api/products/{id}` | JWT (read) | List / show |
| POST | `/api/products` | JWT (write) | Create |
| PUT / PATCH | `/api/products/{id}` | JWT (write) | Update |
| DELETE | `/api/products/{id}` | JWT (delete) | Delete |

## Run locally

```bash
cp .env.example .env          # fill in DB_* and the two secrets
php -r "echo bin2hex(random_bytes(32));"   # run twice: JWT_SECRET, REFRESH_TOKEN_KEY
# set MIGRATION_ENABLED=true in .env, then:
php lava migration run        # creates migrations, users, refresh_tokens, products + demo data
php lava serve                # or: php -S 127.0.0.1:3000 -t public public/index.php
```

Seeded login: `admin@velora.com` / `Admin@12345` (override with `ADMIN_EMAIL` / `ADMIN_PASSWORD`
**before** running the migration against a real database).

## Migration commands (Lab)

```bash
php lava migration status
php lava migration create-migration create_products_table
php lava migration run
php lava migration rollback
php lava migration rollback-all     # dev only
php lava migration refresh          # dev only
```

CLI → `app/commands/Migration.php` → route → `MigrationController` → Migration library → database.
The CLI command lives in `app/commands` (the framework's real folder) and the controller in
`app/controllers` (lower-case, required on Linux/Render).

## Deploy on Render + Aiven

1. Create an Aiven MySQL service. Copy host, port, user, password and the CA certificate.
2. From your computer, put the Aiven values in `.env` (`DB_SSL=true`, `DB_SSL_CA=path/to/ca.pem`,
   `MIGRATION_ENABLED=true`) and run `php lava migration run`.
3. Push this folder to GitHub (`.env` is git-ignored).
4. Render → New → Web Service → this repo → Runtime **Docker**. Add environment variables:
   `DB_DRIVER=mysql`, `DB_HOST`, `DB_PORT`, `DB_USER`, `DB_PASSWORD`, `DB_NAME`, `DB_CHARSET=utf8mb4`,
   `DB_SSL=true`, `DB_SSL_CA_PEM` (CA text, newlines as `\n`), `JWT_SECRET`, `REFRESH_TOKEN_KEY`,
   `ALLOW_ORIGIN=https://your-frontend.onrender.com` (comma-separated origins are supported; include
   `https://api-tester.marasigan.dev` if using that browser-based tester),
   `MIGRATION_ENABLED=false`.
5. Check `https://your-api.onrender.com/api` returns `{"name":"Velora Parts API",...}`.

Keep `MIGRATION_ENABLED=false` on Render so the browser migration routes stay locked.

User management is restricted to administrators. The API never returns password hashes,
revokes refresh tokens after password changes or deactivation, and prevents deleting or
deactivating the last active administrator.
