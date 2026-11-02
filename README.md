# New app

Laravel 12 · Sanctum · Spatie Permission · Resend · Pest · Tailwind 4 + Vite + Alpine.

```bash
composer setup        # install, .env, key, migrate, npm build
php artisan db:seed   # super_admin role + user (SUPER_ADMIN_* in .env)
composer dev          # server, queue, logs, vite
composer test
```

Built assets in `public/build` are compiled and committed by GitHub Actions
(`.github/workflows/build-assets.yml`), so production deploys are a plain `git pull`.
