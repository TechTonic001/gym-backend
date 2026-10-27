# Gym backend deployment

## Deploying to Vercel

This repository is a PHP API. `vercel.json` configures the PHP runtime for the
PHP endpoints, and `/` returns a small JSON health response. Deploy the
repository root as the Vercel project root.

Vercel does not provide the local XAMPP MySQL database. Configure these
environment variables in the Vercel project using credentials for a remotely
accessible MySQL database:

- `DB_HOST` — the Clever Cloud MySQL hostname
- `DB_PORT` — `3306`
- `DB_NAME` — the Clever Cloud database name
- `DB_USER` — the Clever Cloud database username
- `DB_PASSWORD` — enter the actual database password through Vercel's environment-variable settings; never commit it
- `JWT_SECRET` (at least 32 characters)

Set the variables for the Production environment and redeploy after adding or
changing them. Keep `config.local.php` local; it is intentionally excluded
from Git. The backend returns a generic JSON service error if these required
Vercel settings are missing or invalid and logs connection details only to
the server log.

For local XAMPP development, the existing `gym_app` database defaults remain
in place when the `DB_*` environment variables are not set.
