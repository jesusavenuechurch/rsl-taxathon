# RSL Taxathon: evidence-led case workflow

Laravel 12 · Livewire 3 (TALL) · Sanctum · Spatie Permission · Resend · Pest · Tailwind 4 + Vite.

```bash
composer setup        # install, .env, key, migrate, npm build
php artisan db:seed   # roles, teams, super admin, demo officers
php artisan rsl:demo --fresh      # demo machines only: wipe, then RSL knows the registry; companies arrive via Data intake
php artisan rsl:import --all      # or receive every mock RSL record at once, then analyse
php artisan rsl:analyse           # re-run the 14 patterns (e.g. after a rule change)
composer dev          # server, queue, logs, vite
php artisan rsl:clock advance 7   # move the demo calendar (or use Home as super admin)
php artisan audit:verify          # check the audit log has not been tampered with
composer test
```

Built assets in `public/build` are compiled and committed by GitHub Actions
(`.github/workflows/build-assets.yml`), so production deploys are a plain `git pull`.

## Demo accounts

One officer per role, all `@rsl.test` (seeded outside production only): `l.thamae` risk analyst,
`n.lebona` refund auditor, `p.mokoena` Special Sectors, `m.sello` team lead (second sign-off),
`t.ramone` rule owner, `k.molapo` data team, `r.nthako` internal audit. Password: `DEMO_USER_PASSWORD`
in `.env`, or the one the seeder prints. Super admin: `SUPER_ADMIN_EMAIL` / `SUPER_ADMIN_PASSWORD`.

## Where things are

- `docs/journey/README.md`: **the build journey, checkpoint by checkpoint, with screenshots (for presenting and the proposal)**
- `docs/implementation-plan.md`: plain-language overview, database design, build checklist
- `docs/rsl-reference/`: the handoff and the Python reference scripts
- `database/data/rsl/`: mock RSL inputs (the only data the app imports)
- `tests/Fixtures/rsl/`: answer key, used by Pest acceptance tests only
