# The build journey

This document follows the prototype as it is built, one checkpoint at a time. It serves two audiences:

- **Presenting:** each checkpoint has a "Show it" click path and the point to make.
- **Reading with the proposal:** each checkpoint says what RSL gains, what was built, what it proves about feasibility, and its honest limits.

The full technical plan lives in [`docs/implementation-plan.md`](../implementation-plan.md); the challenge brief in [`docs/rsl-reference/HANDOFF.md`](../rsl-reference/HANDOFF.md).

---

## Progress at a glance

| # | Checkpoint | What it shows | Status |
|---|---|---|---|
| 0 | Foundation | Officer sign-in, roles, demo calendar, tamper-evident audit log | ✅ Done |
| 1 | RSL's data in one place | Every channel received and traceable to the row | ✅ Done |
| 2 | Patterns and findings | 14 patterns, 17 findings matching the answer key word for word | ✅ Done |
| 3 | Company memory | Earlier decisions shown as context, their conditions checked | ✅ Done |
| 4 | Cases, worklists and triage | Records arrive from a page; cases open, rank and route themselves; officers take and refer | ✅ Done |
| 5 | AI case brief | The AI consolidates every angle into one cited brief; the officer approves, edits or rejects | Next |
| 6 | WhatsApp notifications | "A new case is ready", with no taxpayer details | |
| 7 | Information requests | Documents arrive only after they are asked for, after the stated number of days | |
| 8 | Officer outcomes | Four outcomes, five-part conclusions, second sign-off at M1m | |
| 9 | Ask the records | Questions answered only from the records, with citations | |
| 10 | Rules, full walkthrough, demo | Rule owners edit thresholds; all 53 workflow steps pass | |

---

## The problem, in one paragraph

Revenue Services Lesotho already holds the facts about every multinational group trading here, but they sit in separate systems: income tax returns in eTax, VAT returns, withholding tax schedules emailed as spreadsheets, Lekuka e-invoices, customs declarations, payroll returns and refund letters on paper. No officer sees them side by side, so patterns that only appear when they are joined (fees paid to a parent with no withholding tax, VAT refunds the e-invoices do not support) go unnoticed or take weeks to assemble by hand.

## What the prototype does

It joins each company's records, explains patterns with the exact evidence, remembers what officers decided before, and leaves every conclusion to an officer. **It is not a fraud detector: a pattern is a reason to review, never proof.**

---

## Checkpoint 0: Foundation

**Why it matters.** Tax data demands accountability. Before any analysis, the system must know who is acting, limit what each role can do, and keep a record nobody can quietly change. *(Judging: trust and compliance.)*

**What was built.**
- Officer sign-in with eight roles (risk analyst, refund auditor, Special Sectors auditor, team lead, rule owner, data team, internal audit, administrator) and four teams that cases are routed between.
- A **tamper-evident audit log**: every entry carries the fingerprint of the entry before it, so altering or removing any entry breaks the chain. The database itself refuses edits and deletions, and a single command (`audit:verify`) proves the log is intact.
- A **demo calendar**: "today" is 2 November 2026, and an administrator can move it forward to show what happens days later (taxpayer documents arriving).

**Show it.** Sign in as P. Mokoena (Special Sectors). Point at the date badge at the top: every date in the system follows it.

![Sign-in](img/00-sign-in.png)

**What it proves.** Accountability is built in from the first line, not bolted on. No blockchain is needed for tamper evidence (handoff rule 14).

**Honest limits.** Removing the very newest log entries can only be detected by comparing with a fingerprint recorded outside the system; `audit:verify` prints it for that purpose.

---

## Checkpoint 1: RSL's data in one place

**Why it matters.** The value of the product starts with joining what RSL already has. *(Judging: value, pilot potential.)*

**What was built.**
- One table per RSL channel (eTax registration, group register, income tax, VAT, withholding tax schedules, Lekuka, customs, PAYE, refund letters), mirroring the source files column for column.
- Every row keeps its public ID (CIT-00036, WHT-00042, RF-0001). **Rows are never edited**: a corrected figure arrives as a new version and the original is kept, so a finding can always show exactly the row it relied on.
- Files are accepted whole or not at all, with a clear reason when rejected, and every receipt is logged.
- Withholding tax schedules carry no payee TIN or country, exactly as in reality; payees are matched to the group register by name.

**Show it.** Home shows what has been received from each channel. Type `CIT-00036` in "Open a record" to see Highveld's 2025/26 return exactly as filed, with where it came from.

![Records received](img/01-home-records-received.png)

![A record as received](img/02-record-as-received.png)

**What it proves.** RSL's existing exports are enough to start; no new taxpayer obligations are needed. In a pilot, the same import runs on files RSL already produces.

**Honest limits.** The data is a mock dataset written for the challenge. Fourteen October VAT returns and three refund letters are dated after the demo's "today"; the system receives them as the answer key expects and flags the date.

---

## Checkpoint 2: Patterns and findings

**Why it matters.** Officers need to know where to look and why. *(Judging: performance and evidence, trust and compliance.)*

**What was built.**
- The 14 patterns of RSL's catalogue run on every company: persistent losses with group charges, fees growing faster than revenue, payments without matching withholding tax, treaty rates claimed for non-treaty countries, imported services without reverse-charge VAT, recurring VAT credits checked against Lekuka and customs (the "green lane"), long-running fixed fees that may hide seconded staff, payments to low-tax jurisdictions, large group interest, profit squeezes, information gaps and possible capture errors.
- Every finding shows **five things**: what the records show, possible lawful explanations, what the officer must establish, an estimated exposure (never an assessed amount), and links to the exact source rows.
- **Wording follows the evidence.** One return alone "may need examination"; two RSL sources that disagree "appear inconsistent with each other". The system refuses to write words such as "evasion", "abuse" or "valid".
- **Every threshold is configuration**, versioned, so a rule owner can change it without a programmer, and every finding records the version that raised it.
- Each analysis keeps a filing review with **all 14 checks, including why the others did not fire**.

**Show it.**
1. Open **Findings**: companies with something to review first, ordinary companies with their one-line summary.
2. Open Thaba Trading's "Fees growing faster than revenue": walk through the five parts, then click a record ID to land on the exact return.
3. Open Tsenola's review ("all 14 checks"): the fees did rise 40%, but revenue rose 35%, so the gap is 5 points and nothing fires. The system explains why it stayed quiet.

![Findings by company](img/03-findings-by-company.png)

![A finding's five parts](img/04-finding-five-parts.png)

![A decoy explains itself](img/05-decoy-explains-itself.png)

**What it proves.** The analysis reproduces RSL's reference results **exactly**: all 17 expected findings, word for word, with the same estimated exposure and the same cited records, and nothing for the two ordinary companies or the three lawful decoys (apart from Semonkong's lower-risk VAT credits). This is checked automatically on every change.

**Honest limits.** The dataset was written to contain these stories, so matching it shows the workflow behaves as designed. It does not show detection accuracy on real taxpayers.

---

## Checkpoint 3: Company memory

**Why it matters.** RSL loses knowledge when an explained case is forgotten and re-examined from scratch next year, and when a past explanation is wrongly treated as a permanent exemption. *(Judging: value, innovation, trust.)*

**What was built.**
- Earlier officer decisions are loaded as company memory, each stored in **five parts**: what was observed, the conclusion, the evidence, the conditions it holds under, and when to reassess.
- After every analysis, the system checks those conditions against the new figures and says plainly whether the company is still within them.
- **Memory informs, never exempts**: findings are always raised first; memory is shown beside them as context.
- **System errors never enter memory.** The database refuses them; they belong to data review.

**Show it.** Open Qiloane Hardware's "Profit squeeze". The memory panel shows DEC-0007 (2024/25: profit fall explained by a new warehouse). Depreciation and bank interest still match the warehouse explanation, but **related-party fees are now M1.6m against about M0.4m when the decision was made: the pattern has moved beyond what that explanation covers.** Then open Tsenola and Maluti: their earlier decisions still hold (fees 5.2% of revenue against a 6% cap; exports 55% of sales against 50 to 60%).

![Memory: outside its conditions](img/06-memory-outside-conditions.png)

**What it proves.** The system can carry an officer's reasoning forward and test it against new data, without ever letting it switch off scrutiny.

**Honest limits.** The officers' conditions are written in plain English. The team translated them into checkable figures (`database/data/rsl/decision_conditions.json`); that translation is our reading, and the officer's own words are always shown beside it. Conditions that cannot be measured ("margin expected to recover within two years") are left for the officer to judge. New decisions recorded in the app will capture checkable conditions directly.

---

## Checkpoint 4: Cases, worklists and triage

**Why it matters.** Findings only help if they reach the right officer, in the right order, without anyone assembling them. *(Judging: value, usability, pilot potential.)*

**What was built.**
- **Data intake page.** Records enter from a page, not the command line: simulate a company's records arriving from every channel, upload a channel file, or receive everything. From there, nothing is manual: analysis runs, findings are raised, memory is checked and a case opens.
- **Cases open, rank and route themselves.** A company with open findings gets one case; an ordinary company gets only its filing review and creates no work.
  - **Priority** follows the estimated exposure (High from M500,000, Medium from M100,000; both are settings). Estimates for one company often describe the same money (Highveld's M3m interest appears in two findings), so the case shows the largest single estimate, never a misleading sum.
  - **Routing** follows the work: VAT refund claims go to Refund audit (in a *lower risk* or *needs review* lane, never "valid" or "refused"); a company with an earlier decision returns to the team that made it (Qiloane to Special Sectors, who decided DEC-0007); personnel arrangements go to Special Sectors; everything else goes to the risk analysts for triage. Every case says why it was routed where it was.
- **Worklists** per team, ranked by priority, then exposure.
- **Triage.** The officer takes the case or refers it to another team with a reason. Only the team holding a case (or a team lead) can act on it. Closing a case is an officer outcome with reasons, never a plain dismiss (checkpoint 8).
- **A timeline** on every case: opened, taken, referred, findings added or withdrawn, notes. Every step is also in the audit log, and every case opening or referral raises the event WhatsApp notifications will hook into (checkpoint 6).

**Show it.**
1. Sign in as the administrator, open **Data intake**, choose *Thaba Trading* and click **Receive records**: 83 rows arrive from six channels, analysis raises two findings, and CASE-0001 lands on the Risk analysis worklist. Then click **Receive every company**.
2. Sign in as L. Thamae (risk analyst): the worklist shows five cases, highest priority first.
3. Open Thaba: the findings with their evidence, the routing reason, and the next step. Click **Take this case**, then refer it to Special Sectors: "Fees tripled while revenue grew 20%: needs a specialist review of the group charges."
4. Sign in as P. Mokoena (Special Sectors): Thaba is now on their worklist, next to Katse and Qiloane.

![Data intake: a company's records arrive](img/07-data-intake-company-arrives.png)

![The analyst's worklist](img/08-worklist-ranked.png)

![A case](img/09-case-page.png)

![The timeline after referral](img/10-case-referred-timeline.png)

![Special Sectors' worklist](img/11-special-sectors-worklist.png)

**What it proves.** The system reproduces the answer key's priorities and first-stop teams for all ten companies with findings, and creates no work for the four without. The officer's day starts with a ranked list, not ten spreadsheets.

**Honest limits.** "Simulate a company's records arriving" filters the mock files; in a pilot, the same intake runs on RSL's exports. Thresholds for priority are starting values for RSL to set.

---

## Coming next

**Checkpoint 5: AI case brief.** The AI reads every finding, rule check, memory check and record for a company and writes one consolidated brief ("losses in four years, funded by group loans that grew from M8m to M29m, carrying M3m of interest with no withholding tax"), with every statement citing its records, plus a suggested next step. The officer approves, edits or rejects; the AI never concludes. It can run on a model hosted inside RSL (Ollama), so taxpayer data need not leave the authority.

**Checkpoint 6: WhatsApp notifications.** "A new case is ready for review (High priority)" reaches the officer's phone the moment a case opens or is referred to their team, with no taxpayer details: the message points into the system.

---

## The honesty statement (for the demo and the submission)

The mock data was written to contain these stories. Passing the acceptance tests shows the workflow behaves as designed; it does not show detection accuracy on real taxpayers. A pilot on RSL's own historical cases, where outcomes are known, is how accuracy would be measured.
