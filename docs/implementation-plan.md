# RSL Taxathon Challenge 2: implementation plan

Companion to `docs/rsl-reference/HANDOFF.md`. That file says *what* is decided; this one says *how* we build it.

- Mock inputs (what the app imports): `database/data/rsl/*.csv`
- Rule catalogue (seeds the editable rule config): `database/data/rsl/pattern_rules.csv`
- Answer key (Pest acceptance tests only; app code must never read it): `tests/Fixtures/rsl/`
- Reference behaviour: `docs/rsl-reference/rules.py`, `verify.py`

---

## 1. The system in plain words

RSL already has the facts about every company, but spread over different systems:
income tax returns in eTax, VAT returns, withholding-tax spreadsheets emailed in,
e-invoices in Lekuka, customs import records, payroll tax, refund letters on paper.
No single officer sees all of them side by side.

This app puts each company's records **on one table** and runs a fixed list of
14 "things worth a second look" (patterns). Examples:

- the company paid its foreign parent 200% more in fees while sales grew 20%;
- it says it paid M3m interest to a group company, but the withholding-tax
  sheets show nothing withheld on it;
- it claims a VAT refund every month, but its own e-invoices don't back the numbers.

When a pattern fires, the app **does not accuse anyone**. It writes a careful note:
what it saw, the innocent reasons that could explain it, what the officer has to find
out, a rough "how much tax could be at stake", and a link to every exact row it used.
How strong the wording is depends on how much evidence there is (the *evidence ladder*):
one return alone only "may need examination"; only after the taxpayer's own documents
are in can it say "does not appear to match the evidence provided"; and only a human
officer ever states a conclusion.

The officer can then ask the taxpayer for specific documents. Those documents
**only appear after they are asked for**, and only after the taxpayer takes the
stated number of days to respond, the same as real life. The officer reviews,
decides one of four outcomes, and writes down *why* and *under what conditions*
that decision holds. That becomes the company's **memory**: next year, if the same
thing shows up, the app shows last year's decision and says plainly whether the
company is still within the conditions or has moved outside them. Memory never
lets a company off; it only gives context.

Around that: big "it's legitimate" decisions (M1m+) need a second officer to sign;
every action is written to a log that can't be quietly edited; WhatsApp alerts say
only "a review is ready" (no taxpayer details); and a chat box answers questions
**only from the records**, always with the row IDs it used.

What it is **not**: a fraud detector. The demo data was written to contain these
stories, so passing our tests proves the workflow behaves as designed, not that it
catches real evasion. We say that in the demo.

---

## 2. Stack and conventions

- Laravel 12, **TALL** (Livewire 3 + Alpine + Tailwind 4), no Filament. Decided. Alpine comes bundled with Livewire; full-page components use `resources/views/components/layouts/app.blade.php`.
- SQLite locally, MySQL/Postgres in production. Money as **integer Maloti** (`bigInteger`); the data has no cents.
- Spatie Permission for roles, Sanctum only if we expose an API, Pest for everything below.
- Queue: `database` driver. Analysis, document delivery and notifications run as jobs.
- **Clock:** an `App\Support\Clock` service is the only source of "today". It defaults to `RSL_TODAY=2026-11-02` and has an admin "advance N days" control for the demo (needed to show documents arriving 7-15 days after a request). Nothing calls `now()` directly in domain code.

---

## 3. Database design

Five layers. Each lower layer is written by fewer people and changes less.

```
 1. Source records      as received from RSL channels; append-only, versioned
 2. Configuration       rules, thresholds, wording, settings; versioned
 3. Analysis            filing reviews, rule checks, findings, evidence links
 4. Casework            cases, information requests, documents, outcomes, memory
 5. Cross-cutting       users/roles, audit chain, notifications, chat
```

### 3.1 Source records (layer 1)

One typed table per channel, mirroring the CSVs column for column. Typed (not one JSON blob table) because the rules aggregate numbers and the chat must query them.

| Table | From | Key notes |
| --- | --- | --- |
| `source_imports` | every file loaded | `channel`, `filename`, `sha256`, `rows`, `received_at`, `imported_by`. Every source row has `source_import_id`. This is the "received" event for the audit log. |
| `taxpayers` | companies.csv | `record_id` (COM-), `tin` unique, `name`, `sector`, `district`, `registered_on`, `vat_registered`, `group_code` (nullable, e.g. G-THABA) |
| `group_entities` | group_register.csv | `record_id` (GRP-), `group_code`, `entity_name`, `normalized_name`, `country`, `relationship`, `low_tax_jurisdiction`, `dta_with_lesotho`, `source`. Officers can add rows (Leribe step 3); those carry `source = 'Officer update from DOC-016'` and a new GRP- id. |
| `cit_returns` | cit_returns.csv | taxpayer x `tax_year`. All P&L columns, `related_party_*`, `related_party_loans_closing`, `financial_statements_attached` |
| `vat_returns` | vat_returns.csv | taxpayer x `period` (YYYY-MM). Includes `capture_channel` (needed for P14) |
| `wht_payments` | wht_schedule.csv | one row per payment. `withholding_agent_tin`, `payee_legal_name`, `payee_normalized_name`, `nature_of_service`, `gross_amount`, `tax_withheld`. No payee TIN/country: linked to `group_entities` **by normalized name at rule time** |
| `lekuka_monthly` | lekuka_monthly.csv | taxpayer x period, Aug-Oct 2026 only |
| `customs_imports` | customs_imports.csv | taxpayer x period |
| `paye_returns` | paye_returns.csv | taxpayer x period, incl. `fringe_benefits_value` |
| `refund_claims` | refund_claims.csv | `record_id` = claim_id (RF-). `vat_periods` normalised into `refund_claim_periods` (claim_id, period) so we can join to VAT returns |

Rules that apply to all of them:

- **`record_id` is the public identity** (CIT-00036, WHT-00042...). Prefixes are unique across tables, so a single `App\Domain\Records\RecordRegistry` maps prefix to model. Evidence links, chat citations and outcomes all point at `record_id` strings. No polymorphic integer IDs leak into the UI.
- **Append-only + versioned.** Columns `version` (1..n), `superseded_at`, `superseded_by_version`; unique (`record_id`, `version`). A correction (Lebelo's mis-keyed figure) inserts version 2 via a data-review item; version 1 is never edited. Rules read the current version; evidence links pin the version they saw.
- Composite indexes on (`tin`, `period`) / (`tin`, `tax_year`) / (`withholding_agent_tin`, `period`).

The **taxpayer document pool** (`taxpayer_responses.csv`) is *not* a source record. It simulates the taxpayer's filing cabinet:

| Table | Notes |
| --- | --- |
| `simulated_taxpayer_documents` | `document_id` (DOC-), `tin`, `document_type`, `title`, `responds_after_days`, `content_summary`, `what_it_shows`. **No Eloquent relation from any case model points here.** Only `DeliverRequestedDocuments` reads it. A Pest arch test enforces that. |

### 3.2 Configuration (layer 2)

| Table | Notes |
| --- | --- |
| `pattern_rules` | `code` (P01..P14), `name`, `plain_language`, `data_channels`, `default_evidence_level`, `legal_source`, `kind` (`risk`, `green_lane`, `information_gap`, `data_quality`), `owner_user_id`, `enabled` |
| `pattern_rule_versions` | `pattern_rule_id`, `version`, `parameters` JSON (thresholds, e.g. P03 `{"min_gap_points":25,"min_fees":500000}`), `wording_templates` JSON (per variant: P07 `lower_risk` / `needs_review`), `lawful_explanations` JSON list, `must_establish` JSON list, `documents_to_request` JSON list, `effective_from`, `created_by`, `change_reason`. **Every finding records the `rule_version_id` that produced it**, so a rule owner changing a threshold never rewrites history. |
| `settings` | key/value with history via audit: `vat_rate` 0.15, `wht_standard_rate` 0.25, `wht_treaty_rate_za` 0.10, `cit_rate` 0.25, `second_signoff_threshold` 1000000, `priority_high_from` 500000, `priority_medium_from` 100000, `chat_fee_increase_threshold` 0.30 |
| `document_types` | canonical names shared by rules and the pool: "Services agreement", "Basis of charge", "Certificate of residence"... (the gate matches on these exactly) |

Rule logic lives in PHP (one class per pattern); **every number it compares against comes from `parameters`**. That is what "thresholds are configuration, not code" means in practice.

### 3.3 Analysis (layer 3)

| Table | Notes |
| --- | --- |
| `analysis_runs` | `trigger` (`import`, `manual`, `rule_change`, `document_released`), `started_at`, `finished_at`, `status`, `rule_versions_snapshot` JSON |
| `filing_reviews` | **one per filing.** `taxpayer_id`, `filing_type` (`cit_return`, `vat_return`, `refund_claim`), `filing_record_id`, `analysis_run_id`, `summary` (the one-liner Mohale gets: "Revenue, expenses and profit are broadly consistent..."), `findings_count` |
| `rule_checks` | one row per rule per filing review: `rule_version_id`, `result` (`fired`, `not_fired`, `not_applicable`), `metrics` JSON (the computed numbers), `explanation` ("fees +40% vs revenue +35%: gap 5 points < 25"). This is how the decoys (Tsenola, Semonkong, Mafeteng) show *why* nothing fired, which is trace steps for those companies. |
| `findings` | `reference` (F-001), `taxpayer_id`, `filing_review_id`, `rule_version_id`, `pattern_code`, `variant`, `kind`, `evidence_level` (1-4, 5 is reserved for officer outcomes), `wording`, `metrics` JSON, `exposure_estimate`, `exposure_low`, `exposure_high` (nullable), `exposure_basis` ("excess fees x 25% CIT"), `status` (`open`, `in_case`, `concluded`, `superseded`), `raised_by` (`system_rule`, `released_document`), `raised_from_document_id` (P09), `fingerprint` unique (taxpayer + pattern + period scope) so re-running analysis updates rather than duplicates, `case_id` nullable |
| `finding_evidence` | `finding_id`, `record_id`, `record_version`, `role` (`primary`, `comparison`, `context`), `row_hash` (sha256 of the row as seen). Exactly the `evidence_record_ids` list in the expected findings. |
| `finding_checklist_items` | snapshot of the rule's lawful explanations and "must establish" questions onto the finding, so officers can tick each off with a note. `type`, `text`, `status`, `note`, `resolved_by` |
| `finding_level_changes` | history of the evidence ladder for a finding (1 -> 4 when documents arrive) with the wording before/after |
| `memory_checks` | `finding_id`, `decision_id`, `result` (`within_conditions`, `outside_conditions`, `not_comparable`), `condition_results` JSON, `statement` ("Previously explained by warehouse investment, but the pattern has moved beyond what that explanation covers.") |

**Wording guard:** a single `WordingPolicy` validates every rendered sentence: the phrase must match the finding's evidence level, and a deny-list (`evasion`, `abuse`, `fraud`, `non-compliant`, `valid`...) is rejected. Used by findings, chat answers and notifications. Unit-tested.

### 3.4 Casework (layer 4)

| Table | Notes |
| --- | --- |
| `cases` | `reference`, `taxpayer_id`, `type` (`risk_review`, `refund_review`, `information_gap`, `data_check`), `refund_claim_id` nullable, `refund_lane` (`lower_risk`, `needs_review`; never "valid"/"refused"), `priority` (computed from total exposure via settings), `status` (`new`, `triage`, `with_specialist`, `awaiting_taxpayer`, `under_review`, `pending_signoff`, `closed`), `team` (`risk_analysis`, `refund_audit`, `special_sectors`), `assigned_user_id`, `opened_at`, `closed_at`. Ordinary companies get **no case**, only a filing review. |
| `case_assignments` | history of hand-offs: from/to team and user, `reason` ("Referred to Special Sectors") |
| `information_requests` | `reference` (IR-001...), `case_id`, `composed_by`, `status` (`draft`, `sent`, `partly_answered`, `answered`, `overdue`), `sent_at`, `due_at`, `message` |
| `information_request_items` | `request_id`, `document_type`, `finding_id` (which finding it serves), `reason`, `status` (`requested`, `received`, `not_supplied`), `released_document_id`. The composer prefills from the open findings' `documents_to_request`, deduplicated; the officer edits. |
| `document_deliveries` | created when a request is sent: `request_item_id`, `simulated_document_id`, `due_at = sent_at + responds_after_days`, `delivered_at`. A scheduled job `documents:deliver-due` (runs on every clock tick) turns due rows into released documents. If nothing in the pool matches, nothing arrives. |
| `released_documents` | the only taxpayer documents the UI ever shows: `reference` (DOC-), `case_id`, `request_item_id`, `document_type`, `title`, `content_summary`, `what_it_shows`, `received_at`, plus file storage later |
| `document_assessments` | officer's reading of a document against a finding: `effect` (`supports_concern`, `explains`, `inconclusive`), `note`. Raises the finding to level 4 wording. Releasing "Housing and vehicle records" for Katse runs the document-triggered rule P09. |
| `officer_outcomes` | per **finding** (Qiloane needs a split: P12 explained, P03 concern supported). `outcome` enum: `concern_supported_referred`, `explained_legitimate`, `more_information_needed`, `system_error`. **The five parts as five columns**: `observed_pattern`, `conclusion`, `evidence`, `conditions`, `reassessment_point`. Plus `decided_by`, `decided_at`, `exposure_at_decision`, `requires_second_signoff`, `signoff_status`, `second_signer_id` (DB check: != `decided_by`), `signed_off_at`, `signoff_note`. |
| `outcome_evidence` | links an outcome to the record IDs / released documents it relies on |
| `decisions` (company memory) | the organisational memory. Seeded from officer_decisions.csv (DEC-0004/0007/0009) and written by `DecisionRecorder` when an outcome is final (after sign-off if required). Same five parts + `tin`, `tax_year_reviewed`, `pattern_code`, `outcome`, `officer`, `second_signoff`, `decided_on`, `source` (`imported`, `officer_outcome_id`). **DB CHECK constraint `outcome <> 'system_error'`** plus a guard in the recorder: rule 9 is enforced twice. |
| `decision_conditions` | the machine-checkable part of "conditions": `metric` (from a small `MetricRegistry`: `related_party_fees`, `fees_to_revenue`, `depreciation`, `bank_interest`, `zero_rated_share`), `operator` (`approx`, `lte`, `between`), `value`, `tolerance`, `text`. DEC-0007: fees approx 400,000; DEC-0004: fees/revenue <= 6%; DEC-0009: zero-rated share between 50% and 60%. Read by `memory_checks`. |
| `data_review_items` | where System error goes: `record_id`, `record_version`, `raised_from_outcome_id`, `reason`, `status`, `resolution`, `corrected_version`. **Never touches `decisions`.** |

### 3.5 Cross-cutting (layer 5)

| Table | Notes |
| --- | --- |
| `users`, `teams`, spatie tables | Roles: `risk_analyst`, `refund_auditor`, `special_sectors_auditor`, `team_lead` (second sign-off), `rule_owner`, `data_team`, `audit_viewer`, `super_admin` |
| `audit_events` | **hash chain.** `sequence` (unique, gapless), `occurred_at`, `actor_type` (`user`, `system`, `taxpayer`), `actor_id`, `action` (`received`, `analysed`, `opened`, `requested`, `released`, `concluded`, `signed_off`, `rule_changed`, `chat_asked`, `exported`), `subject_type`, `subject_ref`, `taxpayer_id`, `payload` JSON, `ip`, `prev_hash`, `hash = sha256(prev_hash + canonical(payload+fields))`. Model throws on update/delete; DB triggers block UPDATE/DELETE; `php artisan audit:verify` recomputes the chain and names the first broken link. "Opened" means page views of a company/case are logged too. |
| `notifications` (Laravel) + `notification_deliveries` | WhatsApp via Meta Cloud API (VENTIQ's provider code reused). Templates are keys with a **whitelist of variables** (`case_type`, `lane`, `link`); a test fails if any template can carry a TIN, name or amount. Text: "A refund review is ready (lower risk)." |
| `chat_conversations`, `chat_messages`, `chat_citations` | message `role`, `content`, `tool_calls` JSON; citations link to `record_id`, `finding_id` or `decision_id`. A `CitationGuard` rejects any answer that cites an ID not returned by a query in that turn, or has no citations. |

### 3.6 How the main entities relate

```
taxpayer ─┬─ source rows (CIT/VAT/WHT/LEK/CUS/PAYE/RF)  [record_id, version]
          ├─ filing_reviews ── rule_checks (14 per review)
          │        └─ findings ── finding_evidence ──> record_id
          │              ├─ finding_checklist_items
          │              └─ memory_checks ──> decisions ── decision_conditions
          ├─ cases ── findings (1 case : n findings)
          │     ├─ information_requests ── items ── document_deliveries ──> released_documents
          │     └─ officer_outcomes (per finding, 5 parts) ─┬─> decisions (memory)
          │                                                 └─> data_review_items (system error only)
          └─ decisions (memory, imported + recorded)
audit_events: every arrow above emits one
```

---

## 4. How analysis runs

1. Import command per channel (`rsl:import cit database/data/rsl/cit_returns.csv`) writes `source_imports` + rows, logs `received`, dispatches `AnalyseTaxpayer` for each affected TIN. `rsl:import-all` loads the whole folder in channel order.
2. `AnalyseTaxpayer` builds a `TaxpayerSnapshot` (all current rows for that TIN, loaded once), runs every enabled rule version, writes one `filing_review` per new filing with 14 `rule_checks`, upserts findings by fingerprint, attaches evidence, runs memory checks, then `CaseRouter` opens or updates a case and queues notifications.
3. Rules are classes implementing `PatternRule::evaluate(TaxpayerSnapshot, RuleParameters): RuleResult`. They port `rules.py` one for one, including its time windows (latest year 2025/26 vs 2024/25; FY months 2025-04..2026-03; last 6 VAT periods).
4. Wording comes from the version's templates with a `Money` formatter that reproduces Python's output exactly (`M1,234`, `a loss of M400,000`, `{:.0%}` percentages). Exact string match is an acceptance test, so this formatter gets its own unit tests.
5. Document-triggered analysis: when a document is released, `AnalyseTaxpayer` reruns with `trigger = document_released` so P09 can fire and affected findings move up the ladder.

---

## 5. Screens (first cut)

- **Worklist** (per role): cases ranked by priority/exposure, filter by team; green-lane refunds in their own lane.
- **Company profile**: all channels side by side by year/month, group structure, memory (past decisions), filing reviews with the 14 rule checks.
- **Case page**: findings, each with the five parts (observation, lawful explanations, must establish, estimated exposure, evidence links that open the exact rows); memory panel; requests and arrived documents; outcome form.
- **Information request composer**: prefilled document list, edit, send.
- **Outcome form**: four outcomes, five fields, sign-off routing.
- **Rule configuration** (rule owner): edit thresholds and wording, see version history, re-run.
- **Data review queue** (data team), **Audit log** (with chain-verified badge), **Chat** side panel on every page.
- **Demo clock** (admin): today's date, "advance 7 days".

---

## 6. Build checklist

Phase 0: Foundation
- [x] TALL stack: Livewire 3 installed, base layout in place
- [x] `Clock` service + demo advance control (`App\Support\Clock`, `rsl:clock`, admin widget on Home)
- [x] Roles/teams seeder with one demo user per role (`App\Support\Roles` is the permission matrix)
- [x] `audit_events` hash chain, `audit:verify`, immutability triggers (sign-ins, sign-outs and clock moves are already logged)

Phase 1: Source records
- [x] Migrations + models for all channel tables, versioning columns, `RecordRegistry` (channel layouts live in `App\Domain\Records\Channels`)
- [x] CSV importer + `rsl:import --all`; `source_imports` (all-or-nothing per file; corrections become new versions; warnings for unregistered TINs and rows dated after today)
- [x] Record viewer: any `record_id` opens its row (`/records/{id}`, lookup box on Home; opening is audited)

Phase 2: Rules and findings
- [ ] `pattern_rules` + versions seeded from `database/data/rsl/pattern_rules.csv` with parameters extracted
- [ ] `TaxpayerSnapshot`, `MetricRegistry`, `Money` formatter, `WordingPolicy`
- [ ] 14 rule classes (P09 document-triggered)
- [ ] Filing reviews, rule checks, findings, evidence, checklist
- [ ] **Acceptance:** findings per company = `expected_system_findings.csv` (pattern, wording, exposure, record IDs); nothing for ordinary companies and decoys except Semonkong P07 lower risk

Phase 3: Memory
- [ ] Import decisions + structured conditions
- [ ] Memory checks and statements
- [ ] **Acceptance:** DEC-0007 shown for Qiloane, new fees stated as outside its conditions; DEC-0004 holds for Tsenola; DEC-0009 holds for Maluti

Phase 4: Cases and worklist
- [ ] `CaseRouter`, priority from settings, assignments/referral
- [ ] Worklist, company profile, case page
- [ ] Refund lanes (lower risk / needs review, never "valid" or "refused")

Phase 5: Requests and documents
- [ ] Composer, requests, items, deliveries, scheduled release
- [ ] Document assessment, ladder to level 4, P09 from Katse's documents
- [ ] **Acceptance:** no document visible before a matching request; visible after `responds_after_days`

Phase 6: Outcomes
- [ ] Four outcomes, five-part form, per-finding (split outcomes)
- [ ] Second sign-off at configured threshold
- [ ] `DecisionRecorder` to memory; System error to data review + record correction (version 2)
- [ ] **Acceptance:** Lebelo's system error never appears in memory

Phase 7: Notifications and chat
- [ ] WhatsApp channel with whitelisted templates
- [ ] Chat: intent router to query tools, optional Claude for phrasing over tool results only, `CitationGuard`
- [ ] **Acceptance:** the 5 chat tests return the expected companies and citations

Phase 8: Rule owner, end-to-end, demo
- [ ] Rule configuration screens + rerun on change
- [ ] **Acceptance:** the 53-step `workflow_trace.csv` as scenario tests per company
- [ ] Demo script; honesty statement on screen and in the submission

---

## 7. Things to watch / questions for the team

1. **Rows dated after "today".** All 14 VAT returns for 2026-10 are filed 2026-11-20 and three refund letters (RF-0007/0014/0021) are dated 2026-11-28, after demo "today" (2026-11-02). The answer key treats them as received (Maluti's F-001 cites its 2026-10 return), so the importer receives them as given and reports a warning. Open question for the team: keep 2 Nov, or move demo "today" to on/after 28 Nov so the dates are consistent.
2. **P05 vs P10 evidence level.** Both cite WHT + group register, but P05 is level 2 and P10 level 3. So level is declared by the rule, not derived from the source mix. We follow the catalogue.
3. **Exposure ranges.** Expected findings give one number; we store `exposure_estimate` and leave low/high optional, always labelled "estimated, not assessed".
4. **Level-4 wording is system text, conclusion is officer text.** "Does not appear to match the evidence provided" appears after a document is assessed; it is still not a conclusion.
5. **Python rounding.** `rules.py` formats with `:.0%` and `:,.0f`; our formatter must match on the edge cases (e.g. 0.5%), or exact-wording tests fail.
6. **Chat Q1 uses a 30% threshold** comparing related fees 2024/25 vs 2025/26, separate from P03. It goes in `settings`.
