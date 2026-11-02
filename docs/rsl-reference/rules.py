"""Run the Tax Risk Pattern Catalogue rules over the mock data.

This is a reference implementation of the detection layer, used to prove the
dataset behaves as the answer key says. It reads only what the system would
receive (never the answer key) and writes csv/expected_system_findings.csv.
"""
import os, statistics
import pandas as pd

_here = os.path.dirname(os.path.abspath(__file__))
D = os.path.join(_here, "data" if os.path.isdir(os.path.join(_here, "data")) else "csv")
TEST = os.path.join(_here, "test") if os.path.isdir(os.path.join(_here, "test")) else D
cit = pd.read_csv(f"{D}/cit_returns.csv")
vat = pd.read_csv(f"{D}/vat_returns.csv")
wht = pd.read_csv(f"{D}/wht_schedule.csv")
lek = pd.read_csv(f"{D}/lekuka_monthly.csv")
cus = pd.read_csv(f"{D}/customs_imports.csv")
grp = pd.read_csv(f"{D}/group_register.csv")
com = pd.read_csv(f"{D}/companies.csv").fillna("")
dec = pd.read_csv(f"{D}/officer_decisions.csv")

LATEST, PRIOR = "2025/26", "2024/25"
FY_MONTHS = [p for p in vat.period.unique() if "2025-04" <= p <= "2026-03"]
LAST6 = sorted(vat.period.unique())[-6:]
ENTITY = {r.entity_name: r for r in grp.itertuples()}
M = lambda x: f"M{x:,.0f}" if x >= 0 else f"a loss of M{-x:,.0f}"
findings = []
LABEL = {"input_vat_local": "Local input VAT", "output_vat": "Output VAT", "standard_rated_sales": "Standard-rated sales"}


def ids(*frames):
    """Exact record IDs behind a finding, so every claim can be traced to source rows."""
    return ";".join(i for fr in frames for i in fr["record_id"])


def add(tin, code, name, level, text, exposure=0, evidence=""):
    findings.append(dict(finding_id=f"F-{len(findings) + 1:03d}", tin=tin, pattern=code, pattern_name=name, evidence_level=level,
                         system_wording=text, estimated_exposure=round(exposure), evidence_records=evidence))


for co in com.itertuples():
    t = co.tin
    y = cit[cit.tin == t].set_index("tax_year")
    v = vat[vat.tin == t].set_index("period")
    w = wht[wht.withholding_agent_tin == t]
    rel = lambda yr: y.loc[yr, ["related_party_management_fees", "related_party_interest", "related_party_royalties"]].sum()

    if LATEST in y.index and PRIOR in y.index:
        now, pre = y.loc[LATEST], y.loc[PRIOR]
        rev_g = now.revenue / pre.revenue - 1
        fees_now = now.related_party_management_fees + now.related_party_royalties
        fees_pre = pre.related_party_management_fees + pre.related_party_royalties
        # P12 profit squeeze
        if rev_g > 0 and pre.net_profit_before_tax > 0 and now.net_profit_before_tax <= 0.5 * pre.net_profit_before_tax:
            add(t, "P12", "Profit squeeze", 1,
                f"Revenue rose {rev_g:.0%} while net profit fell from {M(pre.net_profit_before_tax)} to {M(now.net_profit_before_tax)}. This may have a legitimate explanation and may need examination.",
                0, ids(y.loc[[PRIOR, LATEST]]))
        # P03 fees growing faster than revenue
        if fees_pre > 0 and fees_now >= 500_000 and (fees_now / fees_pre - 1) - rev_g >= 0.25:
            excess = fees_now - fees_pre * (1 + rev_g)
            add(t, "P03", "Fees growing faster than revenue", 1,
                f"Related-party fees rose {fees_now / fees_pre - 1:.0%} against revenue growth of {rev_g:.0%}. This pattern may need examination.",
                excess * 0.25, ids(y.loc[[PRIOR, LATEST]]))
    # P01 / P02 persistent losses, who funds them
    yrs = list(y.index)
    run = 0
    for yr in reversed(yrs):
        if y.loc[yr, "net_profit_before_tax"] < 0 and rel(yr) > 0:
            run += 1
        else:
            break
    if run >= 3:
        loss_years = yrs[-run:]
        add(t, "P01", "Persistent losses with group charges", 1,
            f"Losses in {run} consecutive years ({loss_years[0]} to {loss_years[-1]}) while paying related companies {M(sum(rel(x) for x in loss_years))} over the period. This pattern may need examination.",
            0, ids(y.loc[loss_years]))
        loans = [y.loc[x, "related_party_loans_closing"] for x in loss_years]
        if all(b > a for a, b in zip(loans, loans[1:])):
            add(t, "P02", "Who funds the losses", 1,
                f"Related-party loans grew from {M(loans[0])} to {M(loans[-1])} across the loss years. How the losses are funded, and what the group gains, may need examination.",
                0, ids(y.loc[loss_years]))
    # P11 large interest on group loans
    if LATEST in y.index:
        now = y.loc[LATEST]
        ebit = now.net_profit_before_tax + now.related_party_interest + now.bank_interest
        if now.related_party_interest >= 500_000 and (ebit <= 0 or now.related_party_interest / ebit >= 0.30):
            add(t, "P11", "Large interest on group loans", 1,
                f"Interest of {M(now.related_party_interest)} paid to related companies; operating result before interest is {M(ebit)}. This may need examination.",
                now.related_party_interest * 0.25, ids(y.loc[[LATEST]]))
        # P13 information gap
        gaps = []
        if now.financial_statements_attached == "No" and rel(LATEST) > 0:
            gaps.append("financial statements were not attached")
        unknown = [p for p in w.payee_legal_name.unique() if p not in ENTITY and w[w.payee_legal_name == p].nature_of_service.str.contains("Management|Royalty|Interest").any()]
        if unknown:
            gaps.append("the recipient " + ", ".join(unknown) + " is not in the group register")
        if gaps:
            add(t, "P13", "Information gap", 1,
                f"Related-party charges of {M(rel(LATEST))} were reported, but {' and '.join(gaps)}. The available records do not establish the nature or basis of these charges.",
                0, ids(y.loc[[LATEST]], w[w.payee_legal_name.isin(unknown)]))
    # P04 related payments without matching WHT (FY 2025/26)
    if LATEST in y.index:
        wfy = w[w.period.isin(FY_MONTHS)]
        for col, nature in [("related_party_management_fees", "Management fee"), ("related_party_interest", "Interest"), ("related_party_royalties", "Royalty")]:
            declared = y.loc[LATEST, col]
            withheld_on = wfy[wfy.nature_of_service == nature].gross_amount.sum()
            if declared >= 200_000 and withheld_on < 0.8 * declared:
                add(t, "P04", "Related-party payments without matching withholding tax", 2,
                    f"{nature} of {M(declared)} reported in the {LATEST} return, but withholding tax schedules cover only {M(withheld_on)}. These records appear inconsistent with each other.",
                    (declared - withheld_on) * 0.25, ids(y.loc[[LATEST]], wfy[wfy.nature_of_service == nature]))
    # P05 treaty rate claimed / P10 low-tax jurisdiction
    for payee, g in w.groupby("payee_legal_name"):
        e = ENTITY.get(payee)
        if e is None:
            continue
        rate = g.tax_withheld.sum() / g.gross_amount.sum()
        if e.dta_with_lesotho == "No" and rate < 0.25:
            add(t, "P05", "Treaty rate claimed", 2,
                f"Withholding at {rate:.0%} applied to payments to {payee}, which the group register places in {e.country}, a country without a tax treaty with Lesotho. These records appear inconsistent with each other.",
                g[g.period.isin(FY_MONTHS)].gross_amount.sum() * (0.25 - rate), ids(g) + ";" + e.record_id)
        if e.low_tax_jurisdiction == "Yes":
            add(t, "P10", "Payments to low-tax jurisdictions", 3,
                f"Payments of {M(g.gross_amount.sum())} ({', '.join(g.nature_of_service.unique())}) were made to {payee}, a related entity in {e.country}, a low-tax jurisdiction. What the payments are for may need examination.",
                0, ids(g) + ";" + e.record_id)
    # P06 imported services without matching VAT (FY 2025/26)
    services = w[w.period.isin(FY_MONTHS) & w.nature_of_service.isin(["Management fee", "Royalty", "Technical services"])].gross_amount.sum()
    rc = v.loc[v.index.isin(FY_MONTHS), "output_vat_imported_services"].sum()
    if services >= 200_000 and rc < 0.5 * services * 0.15:
        add(t, "P06", "Imported services without matching VAT", 2,
            f"Services of {M(services)} were paid to non-resident related parties (withholding tax schedules), but reverse-charge VAT of only {M(rc)} was declared; input VAT of {M(v.loc[v.index.isin(FY_MONTHS), 'input_vat_imported_services'].sum())} was claimed on imported services. These records appear inconsistent with each other.",
            services * 0.15 - rc, ids(w[w.period.isin(FY_MONTHS) & w.nature_of_service.isin(["Management fee", "Royalty", "Technical services"])], v.loc[v.index.isin(FY_MONTHS)]))
    # P08 long-term fixed related-party service fee
    mf = w[w.nature_of_service == "Management fee"].sort_values("period")
    if len(mf) >= 12:
        last12 = mf.gross_amount.tail(12)
        if statistics.pstdev(last12) / statistics.mean(last12) < 0.02:
            add(t, "P08", "Long-term personnel arrangement", 1,
                f"An identical fee of {M(last12.mean())} has been paid to {mf.payee_legal_name.iloc[-1]} every month for all {len(mf)} months on record. A fixed, long-running charge can indicate seconded personnel; whether this is consultancy or employment may need examination.",
                last12.sum() * 0.30, ids(mf.tail(12)))
    # P07 recurring VAT credits, with green-lane consistency checks
    last6 = v.loc[v.index.isin(LAST6)]
    credits = (last6.net_vat < 0).sum()
    if credits >= 3:
        problems = []
        for p in lek[lek.tin == t].period:
            lr = lek[(lek.tin == t) & (lek.period == p)].iloc[0]
            vr = v.loc[p]
            cr = cus[(cus.tin == t) & (cus.period == p)].iloc[0]
            if abs(vr.input_vat_local / lr.vat_on_purchases - 1) > 0.10:
                problems.append(f"{p}: local input VAT does not match Lekuka purchases")
            if abs(vr.output_vat / lr.vat_on_sales - 1) > 0.10:
                problems.append(f"{p}: output VAT does not match Lekuka sales")
            if cr.import_vat_paid and abs(vr.import_vat_per_return / cr.import_vat_paid - 1) > 0.05:
                problems.append(f"{p}: import VAT does not match customs")
            if vr.input_vat_imported_services > vr.output_vat_imported_services * 1.01 + 1:
                problems.append(f"{p}: input VAT claimed on imported services without matching reverse-charge output")
        if problems:
            add(t, "P07", "Recurring VAT credits - needs review", 2,
                f"VAT credits in {credits} of the last 6 months. Checks found: {'; '.join(sorted(set(x.split(': ', 1)[1] for x in problems)))}.",
                -last6[last6.net_vat < 0].net_vat.sum(), ids(last6, lek[lek.tin == t], cus[(cus.tin == t) & cus.period.isin(lek[lek.tin == t].period)]))
        else:
            add(t, "P07", "Recurring VAT credits - lower risk", 2,
                f"VAT credits in {credits} of the last 6 months. The available records are consistent with the reported trading activity: sales and purchases match Lekuka, and import VAT matches customs. No significant discrepancies were identified in the checks performed.",
                0, ids(last6, lek[lek.tin == t], cus[(cus.tin == t) & cus.period.isin(lek[lek.tin == t].period)]))
    # P14 possible data capture error
    for col in ["input_vat_local", "output_vat", "standard_rated_sales"]:
        med = v[col].median()
        for p, val in v[col].items():
            if med and val > 4 * med:
                add(t, "P14", "Possible data capture error", 1,
                    f"{LABEL[col]} for {p} is {M(val)}, about {val / med:.0f} times the company's usual monthly level ({M(med)}), while other figures are normal. Check the source figure before drawing conclusions (channel: {v.loc[p, 'capture_channel']}).",
                    0, v.loc[p, "record_id"])

out = pd.DataFrame(findings).merge(com[["tin", "name"]], on="tin")
out = out[["finding_id", "tin", "name", "pattern", "pattern_name", "evidence_level", "system_wording", "estimated_exposure", "evidence_records"]]
out = out.rename(columns={"evidence_records": "evidence_record_ids"})
out.to_csv(f"{TEST}/expected_system_findings.csv", index=False)
if __name__ == "__main__":
    print(out.groupby(["name"]).pattern.apply(lambda s: ", ".join(sorted(s))).to_string())
