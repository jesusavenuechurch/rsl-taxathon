# RSL Taxathon prototype

Evidence-led case workflow for Revenue Services Lesotho (Challenge 2). Laravel 12, TALL (Livewire 3, Alpine bundled with Livewire, Tailwind 4), no Filament. Pest for tests.

Read first:
- `docs/rsl-reference/HANDOFF.md`: the brief and its 16 rules (non-negotiable).
- `docs/journey/README.md`: the build journey, checkpoint by checkpoint. The owner presents from it and sends it with the proposal. **Update it at the end of every checkpoint** (section + screenshots).
- `docs/implementation-plan.md`: database design and build checklist.

## Status

Done: 0 foundation, 1 records, 2 rules and findings (17 findings match the answer key word for word), 3 company memory, 4 cases/worklists/triage/data intake.

Next, in this order: **5 AI case brief** → **6 WhatsApp notifications** → 7 information requests and document release → 8 officer outcomes and second sign-off → 9 "ask the records" chat (acceptance test 5) → 10 rule editing, 53-step workflow trace, demo script. AI and WhatsApp are the selling points.

## Commands

```bash
php artisan test                      # full suite (SQLite in memory)
php artisan rsl:demo --fresh          # DEMO MACHINES ONLY: wipes DB; registry + memory loaded, no filings
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
- **Notifications carry no taxpayer details** (no TIN, name or amount): "A refund review is ready (lower risk)."

## Where things are

- `app/Domain/Records`: channels, CSV importer, registry, demo intake.
- `app/Domain/Analysis`: engine, snapshot, 14 rules in `Rules/`, `RuleCatalogue` (v1 thresholds and wording), wording.
- `app/Domain/Memory`: decision importer, metrics, memory checker.
- `app/Domain/Cases`: router, workflow (take, refer, note). Events `CaseOpened`, `CaseReferred` are the hook for WhatsApp.
- `app/Livewire`: pages (worklist, case, findings, review, record, data intake, demo clock).
- `database/data/rsl`: mock inputs, `pattern_rules.csv`, `decision_conditions.json` (our reading of decision conditions).
- `tests/Feature/Records/helpers.php`: `importAll()`, `analyseEverything()`, `fixture()`, `editedCopy()`.

## Gotchas we have hit

- **Pint rewrites fully qualified class names into `use` imports.** After running Pint, re-read a file before editing it with search/replace, or the edit silently misses.
- **SQLite ignores column lengths; MySQL enforces them.** The importer validates lengths (`Field::limit()`); keep column sizes and channel definitions in step. The owner runs MySQL locally; the suite runs SQLite. MySQL triggers (audit, decisions) are written but only exercised on the owner's machine.
- **GitHub Actions commits `public/build`** after pushes touching assets: always `git pull --rebase` before pushing; on a manifest conflict, rebuild and add `public/build`.
- Livewire 3 ships Alpine: never import Alpine separately.
- Batch imports must analyse once at the end: wrap in `AnalysisScheduler::deferring()` (the import command and test helper do).

## Checkpoint 5 plan: AI case brief

Goal: per case, the AI consolidates every angle (findings, rule checks, memory checks, key figures, refund letter) into one brief with a suggested next step. The officer approves, edits or rejects. The AI drafts; it never concludes.

- **Providers, same as VENTIQ** (`app/Services/AI` in the VENTIQ repo): `AIProvider` contract with Ollama and OpenRouter, chosen by `AI_PROVIDER`; env names `OLLAMA_URL`, `OLLAMA_MODEL`, `OLLAMA_TIMEOUT`, `OLLAMA_TEMPERATURE`, `OPENROUTER_API_KEY`, `OPENROUTER_MODEL`. Owner's local setup: `OLLAMA_MODEL=gpt-oss:20b`, `OLLAMA_EMBED_MODEL=nomic-embed-text` (embeddings are for checkpoint 9).
- gpt-oss is a reasoning model: use Ollama `/api/chat`, read `message.content` (reasoning comes separately in `message.thinking`; never show or store it as the brief). Ask for JSON with Ollama's `format` (JSON schema), low temperature.
- **Grounding**: build the prompt only from records the system looked up (a fact sheet with IDs). The brief returns statements each with `citations`. Reject any statement citing an ID not in the fact sheet, any uncited statement, and anything failing `WordingPolicy`; fall back to a deterministic template brief so the demo never breaks.
- Store briefs (case, provider/model, prompt hash, fact sheet IDs, draft, status draft|approved|edited|rejected, reviewer, timestamps); audit generation and review. Generate in a queued job after a case opens or changes; the case page shows "drafting…" until ready.
- Tests use a fake provider; also test the guard rejects invented citations and forbidden words. Real-model quality is checked by hand against Ollama on the owner's machine.
- Record in the journey: data stays inside RSL with Ollama (trust and compliance).
