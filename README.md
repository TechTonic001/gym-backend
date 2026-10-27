# Gym backend deployment

## Local environment setup

Copy `.env.example` to `.env` in the backend root, then replace
`DB_PASS` with the Clever Cloud database password. `.env` is ignored by Git;
do not commit it. The API reads local `.env` values without a third-party
dependency.

## Deploying to Vercel

This repository is a PHP API. `vercel.json` configures the PHP runtime for the
PHP endpoints, and `/` returns a small JSON health response. Deploy the
repository root as the Vercel project root.

In Vercel, open the backend project, choose **Settings → Environment
Variables**, and add these keys for the **Production** environment (and
Preview too if you test preview deployments):

- `DB_HOST` = `bt70bds5cvwfaxrji9yy-mysql.services.clever-cloud.com`
- `DB_PORT` = `3306`
- `DB_NAME` = `bt70bds5cvwfaxrji9yy`
- `DB_USER` = `ugt8vls42xrbvld0`
- `DB_PASS` = your actual Clever Cloud database password
- `JWT_SECRET` = a randomly generated secret with at least 32 characters

Save each variable, then redeploy the backend so the production functions
receive them. Vercel does not read the committed `.env.example` or local
`.env` file. Never put the actual database password or JWT secret in GitHub.
Keep `config.local.php` local; it is intentionally excluded from Git. The API
returns a generic JSON service error if required production settings are
missing or invalid and logs connection details only to the server log.

The local `.env` values take effect only when Vercel/server environment
variables are not already set, so Vercel's configured values take precedence.
