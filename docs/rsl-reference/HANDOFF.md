# Handoff: RSL Taxathon Challenge 2 prototype

Read this first. It records what is already decided, what is yours to design, and how to know the build is right.
Full reasoning lives in the concept doc: https://claude.ai/artifact/Xxsdgaijm7thpuLucXgR7g

## What we are building

An evidence-led case workflow for Revenue Services Lesotho (RSL) officers reviewing multinational groups in wholesale and retail (VAT, FMCG).
RSL's data already exists but sits in separate systems; the product's value is joining it per company, explaining patterns with evidence, and leaving every conclusion to an officer.
It is not a fraud detector. A pattern is a reason to review, never proof.

Users: risk analysts ("where should I look?"), refund auditors ("can I process this refund confidently?"), Special Sectors auditors ("what is happening in this company?").
Judging (RSL): value 25%, performance and evidence 20%, usability 15%, trust and compliance 15%, pilot potential 15%, innovation 10%.

Stack: Laravel with Inertia, or the TALL stack, chosen by the team. Demo "today" is 2 November 2026.

## The data

`data/` holds the system's only inputs. `test/` holds the answer key; the app must never read it.

| Table | Mirrors RSL channel | Grain |
| --- | --- | --- |
| companies, group_register | eTax registration; company registry (external) | one row per company / group entity |
| cit_returns | eTax income tax returns | company x tax year (2022/23-2025/26, April-March) |
| vat_returns | eTax VAT returns | company x month (2025-04 to 2026-10) |
| wht_schedule | WHT Excel schedule emailed to RSL | one row per payment; no payee country or TIN (link via group_register by name) |
| lekuka_monthly | Lekuka e-invoicing (IDMS) | company x month, Aug-Oct 2026 only; monthly summaries, not invoices |
| customs_imports | eCustoms (ASYCUDA) | company x month |
| paye_returns | eTax PAYE | company x month |
| refund_claims | written refund letters | one row per letter |
| officer_decisions | organisational memory from earlier reviews | one row per decision |
| taxpayer_responses | documents the taxpayer will send | one row per document, gated (see rule 3) |

Every data row has a `record_id` (CIT-, VAT-, WHT-, LEK-, CUS-, PAYE-, GRP-, COM-). Amounts are Maloti. Rates: VAT 15%, WHT 25% standard (10% mock treaty rate for South African recipients), CIT 25%.

## Decided: build these as rules

1. **Officers conclude; the system never does.** No finding may call a taxpayer's conduct "evasion" or "abuse".
2. **Wording follows evidence (evidence ladder).** Level 1 return only: "may need examination". Level 2 return plus other RSL records: "appear inconsistent with each other". Level 3 plus outside records: "the group structure suggests...". Level 4 plus taxpayer documents: "does not appear to match the evidence provided". Level 5: only the officer states a conclusion.
3. **Sequencing.** A taxpayer document is invisible until an officer sends an information request that includes its `document_type`; it then arrives `responds_after_days` later. `documents_to_request` in `test/pattern_rules.csv` uses the same names. Never pre-load responses into a case.
4. **Every finding shows** the observation, lawful explanations, what the officer must establish, an estimated exposure (a range or estimate, never an assessed amount), and links to the exact source rows.
5. **Information gaps are not suspicion.** Missing records produce an "information gap" finding (P13), not a risk finding.
6. **Four officer outcomes, no plain "dismiss":** Concern supported, referred (not a finding of wrongdoing); Explained, legitimate; More information needed; System error.
7. **Conclusions are stored as five separate parts:** observed pattern, conclusion, evidence, conditions, reassessment point.
8. **Memory informs, never exempts.** A later finding is still raised; earlier decisions show as context, and the system states when a pattern has moved outside a decision's conditions (Qiloane, DEC-0007).
9. **System errors go to data review, never into company memory** (Lebelo).
10. **Second sign-off** to close a finding as legitimate when estimated exposure is M1,000,000 or more (configurable).
11. **Thresholds are configuration, not code.** Every rule in `test/pattern_rules.csv` should be editable by the rule owner.
12. **Chat answers only from records and cites them.** It is a way into the same records, never a separate source.
13. **Notifications carry no taxpayer details** (e.g. "A refund review is ready"). They point the officer into the system.
14. **Tamper-evident audit log** of who received, analysed, opened, requested and concluded what, and when. No blockchain needed.
15. **Green lane:** recurring VAT credits that pass the checks are "lower risk", never "valid".
16. **Lead with enforceable checks.** The High Court (AfriSam v LRA, 6 Aug 2026) limits RSL to allocating actual income and deductions under s.113; it cannot impute arm's-length prices. Pricing questions stay with the officer.

## Yours to design

Database schema and naming, the workflow engine, screens and navigation, how analysis is triggered (on filing import, queued jobs), the WhatsApp provider and delivery, chat implementation (retrieval, LLM or rules), authentication and roles, and how the officer composes information requests.
The domain concepts you will need: company profile, filing review (one per filing), finding, evidence link (finding to record_id), case, information request, released taxpayer document, officer outcome, rule configuration, audit event, notification, chat message with citations, user and role.

## Acceptance tests

Run with `python3 rules.py` and `python3 verify.py` for the reference behaviour. The app must:

- [ ] Produce the same pattern set per company as `test/expected_system_findings.csv`, citing the same `record_id`s.
- [ ] Flag nothing for the two ordinary companies and the three decoys (Tsenola, Semonkong, Mafeteng), apart from Semonkong's lower-risk P07.
- [ ] Never show a taxpayer document before a matching request; show it after `responds_after_days`.
- [ ] Walk each company through `test/workflow_trace.csv` (53 steps) with the stated wording.
- [ ] Answer the five questions in `test/chat_tests.csv` with the expected companies and citations.
- [ ] Show DEC-0007 as context for Qiloane and state that the new fees fall outside its conditions.
- [ ] Keep Lebelo's system-error outcome out of company memory.

## Be honest about results

The data was written to contain these stories, so passing these tests shows the workflow behaves as designed. It does not show detection accuracy on real taxpayers. Say so in the demo and the submission.

## Rebuilding the data

`generate.py` regenerates `data/`; `rules.py` reruns the catalogue and writes the expected findings; `verify.py` checks every quoted amount can be rebuilt from the cited rows and that every taxpayer document can be released by a request.
