# RSL Taxathon prototype

Evidence-led case workflow for Revenue Services Lesotho (Challenge 2). Laravel 12, TALL (Livewire 3, Alpine bundled with Livewire, Tailwind 4), no Filament. Pest for tests.

Read first:
- `docs/rsl-reference/HANDOFF.md`: the brief and its 16 rules (non-negotiable).
- `docs/journey/README.md`: the build journey, checkpoint by checkpoint. The owner presents from it and sends it with the proposal. **Update it at the end of every checkpoint** (section + screenshots).
- `docs/implementation-plan.md`: database design and build checklist.

## Status

Done: 0 foundation, 1 records, 2 rules and findings (17 findings match the answer key word for word), 3 company memory, 4 cases/worklists/triage/data intake, 5 AI case brief (grounded, guarded, officer approves/edits/rejects; verified against real Ollama gpt-oss:20b on the owner's machine), 6 **the officer's desk** (`docs/design/officer-desk.md`, all 7 build steps): stages, role desks, case workspace, thin information requests/documents, outcomes/sign-off, WhatsApp sending + Notifications sent, Results tab, guided tour (11 steps, incl. Ask the records: clock moved from the tour panel, never as admin; Leribe → sign-off), reset (allowed on a server when `RSL_DEMO=true`, never sends WhatsApp), front page/Act as; officers land on their desk. The journey doc's checkpoint 6 section and screenshots are current (`docs/journey/README.md`); a full cold stranger run-through (front page → tour → finish, no prior knowledge) passes clean.

Next: hosted deployment (the owner is handling this) and a final look before the Friday 6 Nov 2026 17:00 submission (challenge selection email, proposal of at most 7 pages, prototype link). **Ask the records** (chat, `/ask` and a panel on every case page) is built: `App\Domain\Chat` looks the question up in the records first (`ChatRetriever`), the model may only word an answer from that fact sheet (`ChatGuard`: every citation, figure and word checked), and the plain lookup answer is the fallback. `rsl:demo` now loads every company's filings by default (`--empty` keeps the Data intake story); sign-out in demo mode returns to the officer cards. Not yet checked against a real Ollama run. Deferred, only if time remains: editing the AI brief/checklist notes/referral reason in place and "Add a finding" (officer-desk.md section 5), rule editing (10).

Checkpoint 5 follow-ups raised in review, not yet done: numeric cross-check (verify every amount/percentage a statement states against the facts it cites — the guard today only checks *which* records are cited, not *what is claimed*); redraft a brief when later findings/memory change, not only on case open or referral (mark the old one superseded, add a Redraft action). `composer dev` runs serve + a queue worker together; use it (not `php artisan serve` alone) so briefs draft in the background.

## Commands

```bash
php artisan test                      # full suite (SQLite in memory)
php artisan rsl:demo --fresh          # DEMO MACHINES ONLY: wipes DB; registry, memory and every company's filings loaded (--empty: no filings)
php artisan rsl:import --all          # receive every mock file, then analyse once
php artisan rsl:analyse [tin...]      # re-run the 14 patterns
php artisan rsl:clock advance 7       # move the demo calendar
php artisan audit:verify              # check the audit hash chain
./vendor/bin/pint                     # format
npm run build
node scripts/journey-screenshots.cjs http://127.0.0.1:8123 <password>   # after rsl:demo --fresh and php artisan serve --port=8123
```

## Rules that shape the code

- **Officers conclude, the system never does.** Every system sentence goes through `App\Domain\Analysis\Wording\WordingPolicy` (forbidden words: evasion, abuse, fraud, valid...; evidence-ladder phrases need the right level). AI output must pass it too.
- **Every claim cites records** by public ID (CIT-00036, WHT-00042, RF-0001, GRP-, DEC-, F-). `App\Domain\Records\RecordRegistry` resolves any ID.
- **"Today" comes from `App\Support\Clock`** (demo date 2026-11-02 + offset), never `now()` in domain code.
- **Source records are never edited**: corrections are new versions (`version`, `superseded_at`). Rule versions and decisions are never edited either.
- **Audit everything** through `App\Domain\Audit\AuditLog::record()` (hash chain; DB triggers block UPDATE/DELETE).
- **Memory informs, never exempts**: memory checks run after rules. System errors never enter `decisions` (DB trigger).
- **The app never reads `tests/Fixtures/rsl/`** (the answer key). `tests/Unit/AnswerKeyIsolationTest.php` enforces it.
- **Thresholds are configuration**: rule parameters live in `pattern_rule_versions`; case thresholds in `settings` (`CaseRouter::DEFAULTS`).
- **Notifications carry no taxpayer details** (no TIN, name or amount). The four template bodies live in `NotificationSender::BODIES`, word for word as approved in WhatsApp Manager; the Notifications sent page shows that same text. `WHATSAPP_TEMPLATE_LANGUAGE` must match the templates' language ("English" = `en`). Tests never reach Meta (`phpunit.xml` blanks the WhatsApp credentials).

## Where things are

- `app/Domain/Records`: channels, CSV importer, registry, demo intake.
- `app/Domain/Analysis`: engine, snapshot, 14 rules in `Rules/`, `RuleCatalogue` (v1 thresholds and wording), wording.
- `app/Domain/Memory`: decision importer, metrics, memory checker.
- `app/Domain/Cases`: router, workflow (take, refer, note). Events `CaseOpened`, `CaseReferred` are the hook for WhatsApp (and trigger the AI brief).
- `app/Domain/Briefs`: fact sheet builder, prompt, citation/wording guard, template fallback, generator, officer review. `app/Services/AI` + `app/Contracts/AI\AIProvider`: the Ollama/OpenRouter provider layer (ported from VENTIQ), chosen by `AI_PROVIDER`.
- `app/Livewire`: pages (worklist, case, findings, review, record, data intake, demo clock); `Cases\CaseBriefPanel` is the AI brief panel on the case page.
- `database/data/rsl`: mock inputs, `pattern_rules.csv`, `decision_conditions.json` (our reading of decision conditions).
- `tests/Feature/Records/helpers.php`: `importAll()`, `analyseEverything()`, `fixture()`, `editedCopy()`.

## Gotchas we have hit

- **Pint rewrites fully qualified class names into `use` imports.** After running Pint, re-read a file before editing it with search/replace, or the edit silently misses.
- **SQLite ignores column lengths; MySQL enforces them.** The importer validates lengths (`Field::limit()`); keep column sizes and channel definitions in step. The owner runs MySQL locally; the suite runs SQLite. MySQL triggers (audit, decisions) are written but only exercised on the owner's machine.
- **GitHub Actions commits `public/build`** after pushes touching assets: always `git pull --rebase` before pushing; on a manifest conflict, rebuild and add `public/build`.
- Livewire 3 ships Alpine: never import Alpine separately.
- Batch imports must analyse once at the end: wrap in `AnalysisScheduler::deferring()` (the import command and test helper do).
- **`php artisan serve` does not rebuild CSS.** New Tailwind classes in a new blade file (e.g. `bottom-4 right-4` on the guided tour panel) are invisible until `npm run build` runs — the page looks fine structurally but a `fixed`-positioned element with no compiled `top`/`right`/`bottom` rules renders at its static in-flow position instead (which can be off-screen on a long page). Run `npm run build` after adding classes a build hasn't seen yet, before trusting a visual check.

## Checkpoint 5 plan: AI case brief

Goal: per case, the AI consolidates every angle (findings, rule checks, memory checks, key figures, refund letter) into one brief with a suggested next step. The officer approves, edits or rejects. The AI drafts; it never concludes.

- **Providers, same as VENTIQ** (`app/Services/AI` in the VENTIQ repo): `AIProvider` contract with Ollama and OpenRouter, chosen by `AI_PROVIDER`; env names `OLLAMA_URL`, `OLLAMA_MODEL`, `OLLAMA_TIMEOUT`, `OLLAMA_TEMPERATURE`, `OPENROUTER_API_KEY`, `OPENROUTER_MODEL`. Owner's local setup: `OLLAMA_MODEL=gpt-oss:20b`, `OLLAMA_EMBED_MODEL=nomic-embed-text` (embeddings are for checkpoint 9).
- gpt-oss is a reasoning model: use Ollama `/api/chat`, read `message.content` (reasoning comes separately in `message.thinking`; never show or store it as the brief). Ask for JSON with Ollama's `format` (JSON schema), low temperature.
- **Grounding**: build the prompt only from records the system looked up (a fact sheet with IDs). The brief returns statements each with `citations`. Reject any statement citing an ID not in the fact sheet, any uncited statement, and anything failing `WordingPolicy`; fall back to a deterministic template brief so the demo never breaks.
- Store briefs (case, provider/model, prompt hash, fact sheet IDs, draft, status draft|approved|edited|rejected, reviewer, timestamps); audit generation and review. Generate in a queued job after a case opens or changes; the case page shows "drafting…" until ready.
- Tests use a fake provider; also test the guard rejects invented citations and forbidden words. Real-model quality is checked by hand against Ollama on the owner's machine.
- Record in the journey: data stays inside RSL with Ollama (trust and compliance).
