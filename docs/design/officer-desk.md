# Build plan: the officer's desk

**Design principle.** A professional officer's desk: a calm, organised workspace where officers see what needs attention, understand the evidence and take the next step, while the system quietly preserves the workflow and the audit trail. It should feel like work is happening, not like the officer is managing software.

Judges open the hosted link **on their own**, with no presenter. Everything below must make sense to someone who has never seen the data.

Not this: hospital software, a generic admin dashboard, a draggable Kanban board, literal yellow sticky notes.

Use the real mock data in every example, label and test (Thaba Trading (Pty) Ltd, est. M540,000, CASE-…), never invented figures.

---

## 1. Way in: front page and "Act as"

**Front page** (before sign-in, demo mode only, `RSL_DEMO=true`):
- Three sentences: what this is, that all data is fictional, that the system never concludes.
- One card per officer, one click to enter their desk (no password):
  - L. Thamae, Risk analyst: "Where should I look?"
  - N. Lebona, Refund auditor: "Can I process this refund confidently?"
  - P. Mokoena, Special Sectors: "What is happening in this company?"
  - M. Sello, Team lead: "Can I sign this off?"
  - K. Molapo, Data team: "Is this figure wrong?"
- "Take the guided tour" (section 8) and "Reset the demo".
- Normal sign-in stays for the administrator.

**Act as** (administrator only, demo mode only): header menu listing the officers. While acting, a slim banner: *You are acting as L. Thamae, Risk analyst · Return to admin*. Every audit event records both: actor label `Super Admin acting as L. Thamae (risk_analyst)`, payload `acting_for: <user id>`. Implemented as a session key read by `AuditActor::current()` and the auth guard; the front-page cards use the same mechanism. Presented as a demo convenience, never a production feature.

---

## 2. Case stages (the backbone)

Add `cases.stage` (and keep `status` for open/closed). Every lane, card, strip and Results figure reads from it.

| Stage | Set when | Holder |
|---|---|---|
| `triage` | Case opens routed to Risk analysis or Refund audit; or referred back | First-stop team |
| `specialist` | Opens routed to Special Sectors, or referred to it | Special Sectors |
| `waiting_taxpayer` | An information request is sent | Taxpayer (simulated) |
| `decision` | All requested documents have arrived (or none outstanding and the officer chooses "Decide") | Holding officer |
| `signoff` | An outcome needs second sign-off (explained, legitimate at or above the threshold) | Team lead |
| `closed` | Every open finding has a final outcome (and sign-off where needed) | Nobody |

Every change writes a case event (who, from, to, why) and an audit event, and raises the notification event (section 7).

Journey strip on every case: **Received → Triage → Specialist → Waiting → Decision → Sign-off → Closed**. Current stage highlighted, completed stages shown as history, skipped ones (e.g. Specialist for Leribe) shown faint.

---

## 3. The desk for each officer

Lanes side by side, left to right in the order work flows; stacked on a phone. Not draggable. Each lane shows a count and is empty-state friendly ("Nothing waiting here").

| Officer | Lane | Shows |
|---|---|---|
| Risk analyst (team risk_analysis) | New to triage | team's cases, stage `triage`, unassigned |
| | Working on | assigned to me, stage `triage` or `decision` |
| | Waiting on taxpayer | team's cases, stage `waiting_taxpayer` |
| | Ready to decide | team's cases, stage `decision` |
| Refund auditor (team refund_audit) | Lower risk | team's cases, `refund_lane = lower_risk`, not closed |
| | Needs review | team's cases, `refund_lane = needs_review`, not closed |
| | Verification due soon | team's refund cases ordered by days left to `verification_due` (RSL standard: 3 months) |
| Special Sectors | Handed to us | stage `specialist`, unassigned (shows who handed it over) |
| | Investigating | assigned to me, stage `specialist` |
| | Waiting on taxpayer | team's cases, stage `waiting_taxpayer` (days until documents are due) |
| | Documents arrived | team's cases, stage `decision` with released documents |
| Team lead | Waiting for my sign-off | outcomes with `signoff_status = pending` |
| | Returned | outcomes I returned, not yet resubmitted |
| | Team overview | counts per team per stage (read-only) |
| Data team | Figures to check | open data review items |

**Card** (compact; subtle urgency cues, no badge clutter):

> **Thaba Trading (Pty) Ltd**
> Related-party fees rose much faster than revenue.
> **Needs you:** review the hand-off from L. Thamae
> High priority · est. M540,000 · waiting 2 days
> [Open case]

Fields: company (plain), one-line story (first statement of the approved brief, else the top finding's title in plain words), **what is needed from you** (derived from stage + holder), priority, estimated exposure (if any), waiting time or refund days left, one main action.

Plus, above the lanes: a short "Reminders" strip for the officer (documents due today, sign-offs waiting, a case handed to you), each a link.

---

## 4. The case workspace

One working surface, no maze of tabs. Top to bottom:

1. **Header:** company and case reference, current stage and holder, priority and est. exposure, **who handed it over and why** (a hand-off note styled as a note on the desk), the **next action** as the one main button.
2. **Journey strip** (section 2).
3. **The brief:** the AI brief as a working note: approve, edit or reject (exists). Regenerated when findings change; older versions kept.
4. **Findings**, each a readable work item: what the system observed (plain title + wording), lawful explanations and "must establish" as **tickable items with a note each**, est. exposure with its basis, legal source, and the **evidence inline** (record IDs with their key figures; click opens the exact row and version).
5. **Requests and documents:** draft request (prefilled from the findings' documents to request, deduplicated, editable), send; pending items show their due date; arrived documents appear here, marked with the finding they relate to.
6. **Decision:** per finding, one of four outcomes with five parts (observed pattern, conclusion, evidence, conditions, reassessment point). Draft, edit, submit.
7. **Company memory** and **Timeline** (exist), lower down.
8. "Report a wrong figure" and "Add a finding" as quiet secondary actions.

Plain language everywhere: "Fees growing faster than revenue" not "P03"; "Based on the return only" / "Two RSL records disagree" / "Group records involved" / "Taxpayer documents received" instead of "Evidence level 1–4". Codes and IDs stay visible in small type for those who want them.

---

## 5. Editing and corrections

Feels editable to the officer; never rewrites the evidence trail.

Editable in place (modal or text box): AI brief, notes, checklist ticks and notes, information request before sending, outcome draft before submitting, referral reason.

Never edited in place, replaced by an action that leaves history:
- Wrong figure → **Report a wrong figure** → data review item → data team corrects it as a **new version** of the source row (original kept) → re-analysis withdraws or changes the finding.
- Disagree with a finding → record an **outcome** with a reason (explained / system error).
- Change a submitted decision → **Reopen with a reason** → new version of the outcome.
- System missed a pattern → **Add a finding** (`raised_by = officer`), recorded as a miss for testing.

---

## 6. Results tab

A summary of work done, not a scoreboard of taxpayers flagged. Numbers on top, specifics below:
- Cases closed by outcome (concern supported / explained / more information / system error).
- Estimated exposure referred for investigation.
- Refunds verified within the 3-month standard (and median days).
- Average days spent at each stage.
- Per pattern: how often it ended "concern supported" vs "explained" (the concept's feedback loop; evidence for "performance and evidence").
- List of closed cases: decision, reasons, evidence links.

---

## 7. Notifications (WhatsApp)

On each hand-off event, send the approved template to the receiving officer(s) (users get `whatsapp_number`, opt-in flag). Templates (Utility, English, button "Open case" → `https://<host>/cases/{{1}}`):

| Event | Template | Variables (never a company, TIN or amount) |
|---|---|---|
| Case opened for a team | `rsl_case_ready` | case type ("risk review case", "refund review case (lower risk)"), priority |
| Case referred to a team | `rsl_case_referred` | case reference, team name |
| Requested documents arrived | `rsl_documents_received` | case reference |
| Outcome needs sign-off | `rsl_signoff_needed` | case reference |

`notification_deliveries`: event, template, recipient user, rendered text, Meta message id, status (queued/sent/delivered/read/failed), timestamps; queued job; webhook for status updates if time allows. **"Notifications sent" page** lists them, so judges can see what went out and that no taxpayer details did. Test number: only verified recipients receive; others show "not delivered (demo number)".

---

## 8. Guided tour, reset, sign-off threshold

**Tour**: a small side panel, "Step 3 of 10: what to click, what to notice", with Next (which switches officer via Act as when the step needs it). Steps follow RSL's three required case types:
1. Ordinary: Mohale's filing review, nothing found, no work created.
2. Exception: L. Thamae's desk → Thaba → read the brief → hand it to Special Sectors with a reason.
3. P. Mokoena's desk: Thaba under "Handed to us" with the hand-off note → send an information request.
4. Advance the demo clock 12 days → documents arrive (notification) → "The declared charge does not appear to match the evidence provided."
5. Human review: Katse → documents → a new finding appears (fringe benefits) → record the outcome.
6. Qiloane: earlier decision DEC-0007 shown, new fees outside its conditions.
7. Lebelo: a wrong figure → data team corrects it as a new version → the finding is withdrawn.
8. M. Sello: a decision waiting for sign-off → approve.
9. Results tab.

**Reset**: "Reset the demo" on the front page (demo mode) and a nightly scheduled reset: fresh database, registry and memory loaded, every company received and analysed, briefs pre-generated, clock at 2 Nov 2026.

**Sign-off threshold**: no story in the mock data reaches M1,000,000 (and the stories that do reach M500,000 end as "concern supported", which needs no sign-off), so the demo (`rsl:demo`) asks for a second sign-off on **every** "explained, legitimate" closure, and every screen that relies on it says so (`SignoffPolicy::demoNote()`). The tour's sign-off story is Leribe: an information gap closed as explained once its documents arrive. The tour moves the clock from its own panel; it never signs a judge in as the administrator.

---

## 9. Visual direction

Calm and professional: warm neutral background (not clinical white), clear type and generous spacing, subtle separators, restrained colour (priority and urgency only), one obvious main action per screen, hand-off notes and reminders that feel placed on a desk. No unnecessary animation, badges or widgets.

---

## 10. What has to be built (in order)

1. `cases.stage` + transitions in `CaseWorkflow`; journey strip; Act as + front page + demo flag.
2. Desks per role (section 3) replacing the worklist as each officer's home; case workspace layout (section 4); plain-language labels.
3. Information requests, simulated taxpayer documents released on the clock, findings moving to level 4, P09 raised from Katse's documents (thin checkpoint 7).
4. Outcomes with five parts, sign-off queue, decisions into memory, system error → data review → new source version (thin checkpoint 8).
5. WhatsApp sending + Notifications sent page (section 7).
6. Results tab; guided tour; reset; editing modals and "Add a finding".
7. Journey document: new sections and screenshots; hosted deployment; final run-through as a stranger.

Done means: a judge who has never seen the data can click an officer, understand what is waiting, follow Thaba from one desk to the next, and see a WhatsApp notification recorded for each hand-off.
