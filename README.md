# RSL Taxathon: evidence-led case workflow

Laravel 12 · Sanctum · Spatie Permission · Resend · Pest · Tailwind 4 + Vite + Alpine.

```bash
composer setup        # install, .env, key, migrate, npm build
php artisan db:seed   # super_admin role + user (SUPER_ADMIN_* in .env)
composer dev          # server, queue, logs, vite
composer test
```

Built assets in `public/build` are compiled and committed by GitHub Actions
(`.github/workflows/build-assets.yml`), so production deploys are a plain `git pull`.

## Where things are

- `docs/implementation-plan.md`: plain-language overview, database design, build checklist
- `docs/rsl-reference/`: the handoff and the Python reference scripts
- `database/data/rsl/`: mock RSL inputs (the only data the app imports)
- `tests/Fixtures/rsl/`: answer key, used by Pest acceptance tests only
