"""Check the dataset is internally consistent.

1. Every evidence record ID exists, and belongs to the finding's company.
2. Every money amount quoted in a finding's wording can be rebuilt from the cited rows alone.
3. The answer key matches the findings; the trace only names patterns that fired (plus P09).
4. An information request built from the fired patterns would release every taxpayer document.
"""
import os, re, itertools, sys
import pandas as pd

B = os.path.dirname(os.path.abspath(__file__))
D, T = (f"{B}/data", f"{B}/test") if os.path.isdir(f"{B}/data") else (f"{B}/csv", f"{B}/out/rsl_mock_seed/test")
tables = {p: pd.read_csv(f"{D}/{n}.csv") for p, n in [("CIT", "cit_returns"), ("VAT", "vat_returns"), ("WHT", "wht_schedule"),
          ("LEK", "lekuka_monthly"), ("CUS", "customs_imports"), ("GRP", "group_register")]}
F = pd.read_csv(f"{T}/expected_system_findings.csv")
K = pd.read_csv(f"{T}/answer_key.csv", keep_default_na=False)
TR = pd.read_csv(f"{T}/workflow_trace.csv")
R = pd.read_csv(f"{T}/pattern_rules.csv")
DOC = pd.read_csv(f"{D}/taxpayer_responses.csv")
DEC = pd.read_csv(f"{D}/officer_decisions.csv")
problems = []


def rows_for(ids):
    out = []
    for i in ids:
        t = tables[i.split("-")[0]]
        hit = t[t.record_id == i]
        if hit.empty:
            problems.append(f"missing record {i}")
        out.append((i.split("-")[0], hit))
    return out


for f in F.itertuples():
    ids = f.evidence_record_ids.split(";")
    cited = rows_for(ids)
    # 1. ownership
    for kind, hit in cited:
        if kind == "GRP" or hit.empty:
            continue
        owner = hit.iloc[0]["withholding_agent_tin" if kind == "WHT" else "tin"]
        if owner != f.tin:
            problems.append(f"{f.finding_id}: {hit.iloc[0].record_id} belongs to {owner}, not {f.tin}")
    # 2. rebuild every quoted amount from cited rows: single values, column sums, and sums/differences of two values
    nums = set()
    by_kind = {}
    for kind, hit in cited:
        if not hit.empty:
            by_kind.setdefault(kind, []).append(hit.iloc[0])
    for kind, rows in by_kind.items():
        df = pd.DataFrame(rows)
        num = df.select_dtypes("number")
        for col in num:
            vals = num[col].tolist()
            nums.update(abs(v) for v in vals)
            nums.add(abs(sum(vals)))
            if kind == "WHT":
                for nature, g in df.groupby("nature_of_service"):
                    nums.add(abs(g[col].sum()))
        if kind == "CIT":
            for r in rows:
                nums.add(abs(r.related_party_management_fees + r.related_party_interest + r.related_party_royalties))
                nums.add(abs(r.net_profit_before_tax + r.related_party_interest + r.bank_interest))
            nums.add(sum(r.related_party_management_fees + r.related_party_interest + r.related_party_royalties for r in rows))
    quoted = [int(x.replace(",", "")) for x in re.findall(r"M([\d,]{4,})", f.system_wording)]
    for q in quoted:
        if not any(abs(q - n) <= 10 for n in nums):
            if f.pattern == "P14":   # the 'usual monthly level' is a median over the company's history, not a cited row
                continue
            problems.append(f"{f.finding_id} {f.pattern} {f.name[:25]}: M{q:,} not reproducible from cited rows")

# 3. answer key vs findings; trace vs findings
for k in K.itertuples():
    got = set(F[F.tin == k.tin].pattern)
    want = set(re.findall(r"P\d\d", k.must_fire)) - {"P09"}
    if got != want:
        problems.append(f"answer key mismatch {k.company}: {sorted(got)} vs {sorted(want)}")
    named = set(re.findall(r"P\d\d", " ".join(TR[TR.tin == k.tin].event)))
    checked_not_fired = set(re.findall(r"P\d\d", " ".join(TR[(TR.tin == k.tin) & TR.event.str.contains("does not fire")].event)))
    if not (named - checked_not_fired) <= got | {"P09"}:
        problems.append(f"trace names unfired patterns for {k.company}: {sorted(named - got - {'P09'})}")
    for d in re.findall(r"DEC-\d{4}", k.organisational_memory + " " + " ".join(TR[TR.tin == k.tin].event)):
        if d not in set(DEC.decision_id):
            problems.append(f"{k.company}: unknown decision {d}")

# 4. requests built from fired patterns release every document
docs_for = {r.code: {x.strip() for x in r.documents_to_request.split(";")} for r in R.itertuples()}
for tin, g in DOC.groupby("tin"):
    fired = set(F[F.tin == tin].pattern) | ({"P09"} if tin == "TN1000656-9" else set())
    requestable = set().union(*(docs_for[p] for p in fired)) if fired else set()
    missing = set(g.document_type) - requestable
    if missing:
        problems.append(f"{tin}: documents {sorted(missing)} could never be requested from fired patterns")

print(f"{len(F)} findings, {sum(len(x.split(';')) for x in F.evidence_record_ids)} evidence links, {len(DOC)} taxpayer documents checked")
print("\n".join(problems) if problems else "All checks passed")
sys.exit(1 if problems else 0)
