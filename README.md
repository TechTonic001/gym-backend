# Gym backend deployment

## Deploying to Vercel

This repository is a PHP API. `vercel.json` configures the PHP runtime for the
PHP endpoints, and `/` returns a small JSON health response. Deploy the
repository root as the Vercel project root.

Vercel does not provide the local XAMPP MySQL database. Configure these
environment variables in the Vercel project using credentials for a remotely
accessible MySQL database:

- `DB_HOST`
- `DB_PORT` (usually `3306`)
- `DB_NAME`
- `DB_USER`
- `DB_PASSWORD`
- `JWT_SECRET` (at least 32 characters)

Redeploy after adding or changing environment variables. Keep
`config.local.php` local; it is intentionally excluded from Git.

For local XAMPP development, the existing `gym_app` database defaults remain
in place when the `DB_*` environment variables are not set.
