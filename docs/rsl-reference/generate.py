"""Generate the fictional mock dataset for the RSL Taxathon Challenge 2 prototype.

Every company, person and figure is invented. Monetary values are Maloti (M).
Lesotho tax year (year of assessment) is assumed April to March.
Run: python3 generate.py  -> writes CSV files into ./csv
"""
import csv, os, random, statistics

random.seed(2026)
_here = os.path.dirname(os.path.abspath(__file__))
OUT = os.path.join(_here, "data" if os.path.isdir(os.path.join(_here, "data")) else "csv")
os.makedirs(OUT, exist_ok=True)

VAT = 0.15
CIT = 0.25
WHT_STD = 0.25          # s.107 management fees, interest, royalties to non-residents
WHT_SA_TREATY = 0.10    # mock treaty rate for South African recipients
TAX_YEARS = ["2022/23", "2023/24", "2024/25", "2025/26"]
MONTHS = [f"{y}-{m:02d}" for y, m in
          [(2025, m) for m in range(4, 13)] + [(2026, m) for m in range(1, 11)]]
LEKUKA_MONTHS = ["2026-08", "2026-09", "2026-10"]
SEASON = {1: .92, 2: .90, 3: .97, 4: .98, 5: .97, 6: .99, 7: 1.0, 8: 1.0, 9: 1.01, 10: 1.03, 11: 1.07, 12: 1.16}


def fy_of(period):
    y, m = map(int, period.split("-"))
    start = y if m >= 4 else y - 1
    return f"{start}/{str(start + 1)[2:]}"


def r(x):
    return int(round(x / 10) * 10)


def noise(p=0.03):
    return 1 + random.uniform(-p, p)


PREFIX = {"cit_returns": "CIT", "vat_returns": "VAT", "wht_schedule": "WHT", "paye_returns": "PAYE",
          "customs_imports": "CUS", "lekuka_monthly": "LEK", "group_register": "GRP", "companies": "COM"}


def write(name, rows):
    if name in PREFIX and "record_id" not in rows[0]:   # stable IDs so findings can cite exact rows
        rows = [dict(record_id=f"{PREFIX[name]}-{i:05d}", **row) for i, row in enumerate(rows, 1)]
    with open(os.path.join(OUT, name + ".csv"), "w", newline="", encoding="utf-8") as f:
        w = csv.DictWriter(f, fieldnames=list(rows[0].keys()))
        w.writeheader()
        w.writerows(rows)


# ---------------------------------------------------------------- companies
# profile: annual drivers per tax year. Keys used:
# rev, gm (gross margin), opex_pct, dep, fees (related mgmt/service fees), int_rel (related interest),
# int_bank, roy (related royalties), loans (related-party loan balance), staff
C = [
    dict(tin="TN1000101-3", name="Mohale Wholesale (Pty) Ltd", sector="Wholesale FMCG", district="Maseru",
         registered="2011-03-14", group=None, z=0.20, imp_share=0.60, std_purch=0.80, staff=46,
         years={"2022/23": dict(rev=18.0e6), "2023/24": dict(rev=19.3e6), "2024/25": dict(rev=20.6e6), "2025/26": dict(rev=22.2e6)},
         gm=0.21, opex_pct=0.135, dep=0.28e6),
    dict(tin="TN1000214-8", name="Butha-Buthe General Dealers (Pty) Ltd", sector="Retail FMCG", district="Butha-Buthe",
         registered="2008-07-02", group=None, z=0.25, imp_share=0.40, std_purch=0.75, staff=31,
         years={"2022/23": dict(rev=9.8e6), "2023/24": dict(rev=10.3e6), "2024/25": dict(rev=10.9e6), "2025/26": dict(rev=11.6e6)},
         gm=0.24, opex_pct=0.17, dep=0.15e6),
    dict(tin="TN1000327-1", name="Maluti Foods Distributors (Pty) Ltd", sector="Wholesale FMCG (exporter)", district="Maseru",
         registered="2014-01-20", group="G-MALUTI", z=0.55, imp_share=0.30, std_purch=0.88, staff=58, exporter=True,
         years={"2022/23": dict(rev=26.0e6, fees=0.6e6), "2023/24": dict(rev=27.5e6, fees=0.62e6), "2024/25": dict(rev=29.1e6, fees=0.65e6), "2025/26": dict(rev=31.0e6, fees=0.68e6)},
         gm=0.18, opex_pct=0.11, dep=0.45e6, rc_ok=True),
    dict(tin="TN1000430-6", name="Thaba Trading (Pty) Ltd", sector="Wholesale FMCG", district="Maseru",
         registered="2016-05-09", group="G-THABA", z=0.20, imp_share=0.65, std_purch=0.80, staff=22,
         years={"2022/23": dict(rev=8.6e6, fees=1.1e6), "2023/24": dict(rev=9.2e6, fees=1.15e6), "2024/25": dict(rev=10.0e6, fees=1.2e6), "2025/26": dict(rev=12.0e6, fees=3.6e6)},
         gm=0.36, opex_pct=0.04, dep=0.12e6, rc_ok=True, target_np={"2024/25": 2.0e6, "2025/26": 0.6e6}),
    dict(tin="TN1000543-2", name="Senqu Imports (Pty) Ltd", sector="Wholesale FMCG (importer)", district="Maseru",
         registered="2017-09-11", group="G-SENQU", z=0.40, imp_share=0.85, std_purch=0.75, staff=29,
         years={"2022/23": dict(rev=21.0e6, fees=3.9e6), "2023/24": dict(rev=22.4e6, fees=4.3e6), "2024/25": dict(rev=23.2e6, fees=4.6e6), "2025/26": dict(rev=24.0e6, fees=4.8e6)},
         gm=0.30, opex_pct=0.06, dep=0.2e6, rc_ok=False, claims_input_on_imported=True),
    dict(tin="TN1000656-9", name="Katse Retail Group (Pty) Ltd", sector="Retail FMCG", district="Maseru",
         registered="2015-02-27", group="G-KATSE", z=0.22, imp_share=0.55, std_purch=0.78, staff=85,
         years={"2022/23": dict(rev=24.0e6, fees=0.5e6), "2023/24": dict(rev=25.5e6, fees=2.16e6), "2024/25": dict(rev=26.9e6, fees=2.16e6), "2025/26": dict(rev=28.2e6, fees=2.16e6)},
         gm=0.26, opex_pct=0.14, dep=0.6e6, rc_ok=True, fixed_fee_from="2023-04"),
    dict(tin="TN1000769-4", name="Qiloane Hardware & Building Supplies (Pty) Ltd", sector="Retail hardware", district="Maseru",
         registered="2012-10-03", group="G-QILOANE", z=0.0, imp_share=0.60, std_purch=0.95, staff=40,
         years={"2022/23": dict(rev=14.2e6, fees=0.38e6), "2023/24": dict(rev=15.0e6, fees=0.4e6), "2024/25": dict(rev=16.0e6, fees=0.4e6, dep_extra=0.9e6, int_bank=0.6e6),
                "2025/26": dict(rev=16.5e6, fees=1.6e6, dep_extra=0.9e6, int_bank=0.55e6)},
         gm=0.25, opex_pct=0.12, dep=0.35e6, rc_ok=True, target_np={"2024/25": 0.5e6, "2025/26": -0.4e6}),
    dict(tin="TN1000872-0", name="Lebelo Cash & Carry (Pty) Ltd", sector="Wholesale FMCG", district="Leribe",
         registered="2013-06-18", group=None, z=0.20, imp_share=0.55, std_purch=0.80, staff=27,
         years={"2022/23": dict(rev=13.1e6), "2023/24": dict(rev=13.8e6), "2024/25": dict(rev=14.6e6), "2025/26": dict(rev=15.3e6)},
         gm=0.19, opex_pct=0.12, dep=0.18e6, capture_error="2026-03"),
    dict(tin="TN1000985-5", name="Highveld Retail Lesotho (Pty) Ltd", sector="Retail FMCG", district="Maseru",
         registered="2019-11-05", group="G-HIGHVELD", z=0.22, imp_share=0.70, std_purch=0.78, staff=140,
         years={"2022/23": dict(rev=30.0e6, fees=3.0e6, int_rel=1.0e6, loans=8.0e6), "2023/24": dict(rev=34.0e6, fees=3.4e6, int_rel=1.6e6, loans=14.0e6),
                "2024/25": dict(rev=38.0e6, fees=3.8e6, int_rel=2.3e6, loans=21.0e6), "2025/26": dict(rev=42.0e6, fees=4.2e6, int_rel=3.0e6, loans=29.0e6)},
         gm=0.22, opex_pct=0.15, dep=0.9e6, rc_ok=True, wht_on_interest=False),
    dict(tin="TN1001098-7", name="Pitseng Beverages (Pty) Ltd", sector="Beverages distribution", district="Leribe",
         registered="2018-04-23", group="G-PITSENG", z=0.0, imp_share=0.50, std_purch=0.95, staff=38,
         years={"2022/23": dict(rev=16.0e6, fees=0.8e6, roy=1.0e6), "2023/24": dict(rev=17.1e6, fees=0.85e6, roy=1.1e6), "2024/25": dict(rev=18.0e6, fees=0.88e6, roy=1.15e6), "2025/26": dict(rev=19.2e6, fees=0.9e6, roy=1.2e6)},
         gm=0.30, opex_pct=0.14, dep=0.3e6, rc_ok=True, treaty_misuse=True),
    dict(tin="TN1001201-1", name="Leribe Pharma Distributors (Pty) Ltd", sector="Wholesale pharmaceuticals", district="Leribe",
         registered="2020-08-12", group="G-UNKNOWN", z=0.30, imp_share=0.80, std_purch=0.70, staff=24,
         years={"2022/23": dict(rev=11.0e6, fees=1.6e6), "2023/24": dict(rev=11.9e6, fees=1.8e6), "2024/25": dict(rev=12.6e6, fees=1.9e6), "2025/26": dict(rev=13.4e6, fees=2.0e6)},
         gm=0.31, opex_pct=0.08, dep=0.15e6, rc_ok=True, fs_missing=True),
    dict(tin="TN1001314-6", name="Tsenola Distributors (Pty) Ltd", sector="Wholesale FMCG", district="Mafeteng",
         registered="2010-12-01", group="G-TSENOLA", z=0.20, imp_share=0.60, std_purch=0.80, staff=35,
         years={"2022/23": dict(rev=17.0e6, fees=0.8e6), "2023/24": dict(rev=18.5e6, fees=0.95e6), "2024/25": dict(rev=20.0e6, fees=1.0e6), "2025/26": dict(rev=27.0e6, fees=1.4e6)},
         gm=0.22, opex_pct=0.12, dep=0.3e6, rc_ok=True),
    dict(tin="TN1001427-2", name="Semonkong Fresh Produce (Pty) Ltd", sector="Wholesale fresh produce", district="Maseru",
         registered="2024-04-01", group=None, z=0.70, imp_share=0.20, std_purch=0.50, staff=12,
         years={"2024/25": dict(rev=3.2e6), "2025/26": dict(rev=5.1e6)},
         gm=0.20, opex_pct=0.30, dep=0.12e6, startup=True),
    dict(tin="TN1001530-8", name="Mafeteng Agri-Supplies (Pty) Ltd", sector="Wholesale agricultural inputs", district="Mafeteng",
         registered="2009-03-30", group=None, z=0.35, imp_share=0.70, std_purch=0.65, staff=33,
         years={"2022/23": dict(rev=19.0e6, int_bank=1.2e6), "2023/24": dict(rev=19.9e6, int_bank=1.3e6), "2024/25": dict(rev=20.8e6, int_bank=1.4e6), "2025/26": dict(rev=21.5e6, int_bank=1.4e6)},
         gm=0.23, opex_pct=0.11, dep=0.4e6),
]
BY_TIN = {c["tin"]: c for c in C}

# ---------------------------------------------------------------- group register (assumed from registry / taxpayer, A4)
GROUPS = [
    ("G-MALUTI", "Maluti Foods Holdings (Pty) Ltd", "South Africa", "Parent", "No", "Yes"),
    ("G-THABA", "Thaba Group Services (Pty) Ltd", "South Africa", "Parent", "No", "Yes"),
    ("G-SENQU", "Senqu Holdings (Pty) Ltd", "South Africa", "Parent", "No", "Yes"),
    ("G-KATSE", "Katse Retail Holdings (Pty) Ltd", "South Africa", "Parent", "No", "Yes"),
    ("G-QILOANE", "Qiloane Building Group (Pty) Ltd", "South Africa", "Parent", "No", "Yes"),
    ("G-HIGHVELD", "Highveld Retail Holdings Ltd", "South Africa", "Parent", "No", "Yes"),
    ("G-HIGHVELD", "Highveld Treasury Services Ltd", "South Africa", "Sister (group finance)", "No", "Yes"),
    ("G-PITSENG", "Pitseng Beverages Group (Pty) Ltd", "South Africa", "Parent", "No", "Yes"),
    ("G-PITSENG", "Pitseng Brands Ltd", "Islandia (fictional)", "Sister (brand owner)", "Yes", "No"),
    ("G-TSENOLA", "Tsenola Holdings (Pty) Ltd", "South Africa", "Parent", "No", "Yes"),
]
group_rows = [dict(group_id=g, entity_name=n, country=co, relationship=rel, low_tax_jurisdiction=lt,
                   dta_with_lesotho=dta, source="Company registry / taxpayer disclosure (assumption A4)")
              for g, n, co, rel, lt, dta in GROUPS]
PAYEE = {  # who receives the related-party payments, per company
    "TN1000327-1": {"fees": "Maluti Foods Holdings (Pty) Ltd"},
    "TN1000430-6": {"fees": "Thaba Group Services (Pty) Ltd"},
    "TN1000543-2": {"fees": "Senqu Holdings (Pty) Ltd"},
    "TN1000656-9": {"fees": "Katse Retail Holdings (Pty) Ltd"},
    "TN1000769-4": {"fees": "Qiloane Building Group (Pty) Ltd"},
    "TN1000985-5": {"fees": "Highveld Retail Holdings Ltd", "int_rel": "Highveld Treasury Services Ltd"},
    "TN1001098-7": {"fees": "Pitseng Beverages Group (Pty) Ltd", "roy": "Pitseng Brands Ltd"},
    "TN1001201-1": {"fees": "Leribe Pharma Holdings (Pty) Ltd"},  # not in group register: information gap
    "TN1001314-6": {"fees": "Tsenola Holdings (Pty) Ltd"},
}
NATURE = {"fees": "Management fee", "int_rel": "Interest", "roy": "Royalty"}
FORM = {"fees": "WD4(1993)", "int_rel": "WD1(1993)", "roy": "WD5(1993)"}

# ---------------------------------------------------------------- income tax returns
cit_rows, fy_cache = [], {}
for c in C:
    loss_bf = 0
    for ty in TAX_YEARS:
        if ty not in c["years"]:
            continue
        y = c["years"][ty]
        rev = y["rev"]
        cogs = rev * (1 - c["gm"])
        fees, int_rel, roy = y.get("fees", 0), y.get("int_rel", 0), y.get("roy", 0)
        int_bank = y.get("int_bank", 0)
        dep = c["dep"] + y.get("dep_extra", 0)
        staff = rev * 0.055 if not c.get("startup") else rev * 0.12
        rent = rev * 0.025
        other = rev * c["opex_pct"] - staff - rent
        if other < rev * 0.01:
            other = rev * 0.01
        np_ = rev - cogs - staff - rent - other - dep - fees - int_rel - roy - int_bank
        if c.get("target_np", {}).get(ty) is not None:   # tune Thaba to the agreed scenario figures
            other += np_ - c["target_np"][ty]
            np_ = c["target_np"][ty]
        if c["tin"] == "TN1000985-5":                    # Highveld: losses roughly equal to the group charges
            other += np_ + (fees * 0.55 + int_rel * 0.4)
            np_ = -(fees * 0.55 + int_rel * 0.4)
        if c.get("startup"):
            np_ = {"2024/25": -0.62e6, "2025/26": -0.31e6}[ty]
            other = rev - cogs - staff - rent - dep - np_
        taxable = np_ - loss_bf if np_ > 0 else np_
        if np_ > 0:
            used = min(loss_bf, np_)
            taxable = np_ - used
            loss_cf = loss_bf - used
        else:
            taxable = 0
            loss_cf = loss_bf - np_
        row = dict(tin=c["tin"], tax_year=ty, filed_date=f"20{ty[-2:]}-09-{random.randint(10, 29):02d}",
                   revenue=r(rev), cost_of_sales=r(cogs), gross_profit=r(rev - cogs), staff_costs=r(staff),
                   rent_utilities=r(rent), depreciation=r(dep), other_operating_expenses=r(other),
                   related_party_management_fees=r(fees), related_party_interest=r(int_rel), related_party_royalties=r(roy),
                   bank_interest=r(int_bank), net_profit_before_tax=r(np_), assessed_loss_brought_forward=r(loss_bf),
                   taxable_income=r(max(taxable, 0)), assessed_loss_carried_forward=r(loss_cf),
                   tax_payable=r(max(taxable, 0) * CIT), related_party_loans_closing=r(y.get("loans", 0)),
                   financial_statements_attached="No" if c.get("fs_missing") else "Yes",
                   source_system="eTax")
        cit_rows.append(row)
        fy_cache[(c["tin"], ty)] = dict(row, _fees=fees, _int=int_rel, _roy=roy, _rev=rev)
        loss_bf = loss_cf
write("cit_returns", cit_rows)

# ---------------------------------------------------------------- monthly channels
vat_rows, wht_rows, paye_rows, cus_rows, lek_rows = [], [], [], [], []
next_year_growth = 1.05


def annual(c, fy):
    if fy in c["years"]:
        return c["years"][fy]
    last = c["years"]["2025/26"]                      # 2026/27 months: extend last year
    return {k: (v * next_year_growth if k == "rev" else v) for k, v in last.items()}


for c in C:
    for p in MONTHS:
        fy = fy_of(p)
        if c.get("startup") and fy not in ("2024/25", "2025/26", "2026/27"):
            continue
        a = annual(c, fy)
        m = int(p[-2:])
        sales = a["rev"] / 12 * SEASON[m] * noise()
        z = c["z"]
        std_sales, zero_sales = sales * (1 - z), sales * z
        purchases = sales * (1 - c["gm"]) * noise(0.04)
        imports = purchases * c["imp_share"]
        local = purchases - imports
        std_local = local * c["std_purch"]
        std_imp = imports * c["std_purch"]
        import_vat = std_imp * VAT
        input_local = std_local * VAT
        rel = {}
        for key in ("fees", "int_rel", "roy"):
            amt = a.get(key, 0) / 12 * (noise(0.08) if a.get(key, 0) else 1)
            if key == "fees" and c.get("fixed_fee_from") and p >= c["fixed_fee_from"]:
                amt = 180000
            rel[key] = amt
        services = rel["fees"] + rel["roy"]                  # imported services from related parties
        rc_output = services * VAT if c.get("rc_ok") else 0
        input_imp_services = services * VAT if (c.get("rc_ok") or c.get("claims_input_on_imported")) else 0
        capture = "eTax online"
        if c.get("capture_error") == p:              # S7: one month captured ten times too large
            input_local *= 10
            capture = "Manual capture from paper return"
        output = std_sales * VAT
        net = output + rc_output - input_local - import_vat - input_imp_services
        due = f"{p[:4]}-{int(p[-2:]) + 1:02d}-20" if m < 12 else f"{int(p[:4]) + 1}-01-20"
        vat_rows.append(dict(tin=c["tin"], period=p, due_date=due, filed_date=due, standard_rated_sales=r(std_sales),
                             zero_rated_sales=r(zero_sales), exempt_sales=0, output_vat=r(output),
                             imported_services_value=r(services if c.get("rc_ok") else 0), output_vat_imported_services=r(rc_output),
                             local_purchases_standard=r(std_local), input_vat_local=r(input_local),
                             import_vat_per_return=r(import_vat), input_vat_imported_services=r(input_imp_services),
                             net_vat=r(net), position="Credit (refund due)" if net < 0 else "Payable",
                             capture_channel=capture, source_system="eTax"))
        cus_rows.append(dict(tin=c["tin"], period=p, declarations=random.randint(8, 30) if imports > 0 else 0,
                             customs_value=r(imports), main_origin="South Africa", import_vat_paid=r(import_vat),
                             source_system="eCustoms (ASYCUDA)"))
        if p in LEKUKA_MONTHS:
            lek_rows.append(dict(tin=c["tin"], period=p, invoices_issued=int(sales / 3500), sales_standard_excl_vat=r(std_sales * noise(0.01)),
                                 sales_zero_rated=r(zero_sales * noise(0.01)), vat_on_sales=r(output * noise(0.01)),
                                 purchases_from_vat_vendors_excl_vat=r(std_local * noise(0.01)), vat_on_purchases=r(input_local * noise(0.01)),
                                 note="Foreign supplier invoices are not routed through Lekuka", source_system="Lekuka / IDMS"))
        # PAYE
        employees = c["staff"] + random.randint(-1, 1)
        gross = c["years"].get(fy, a)["rev"] * (0.055 if not c.get("startup") else 0.12) / 12 * noise(0.01)
        fb = r(gross * 0.01)
        paye_rows.append(dict(tin=c["tin"], period=p, employees=employees, gross_remuneration=r(gross),
                              paye_withheld=r(gross * 0.17), fringe_benefits_value=fb, fbt_paid=r(fb * 0.30),
                              source_system="eTax"))
        # WHT schedule (one row per payment; fields as in RSL's Excel schedule)
        if c["tin"] in PAYEE:
            for key, payee in PAYEE[c["tin"]].items():
                amt = rel[key]
                if amt <= 0:
                    continue
                if key == "int_rel" and c.get("wht_on_interest") is False:
                    continue                                       # Highveld: interest paid, no WHT
                rate = WHT_SA_TREATY
                if c.get("treaty_misuse"):
                    rate = WHT_SA_TREATY                           # 10% applied to all, incl. Islandia royalties
                wht_rows.append(dict(withholding_agent_tin=c["tin"], period=p, payment_date=f"{p}-25",
                                     payee_legal_name=payee, nature_of_service=NATURE[key], form=FORM[key],
                                     gross_amount=r(amt), tax_withheld=r(amt * rate),
                                     submitted_via="Excel schedule emailed to WHTSubmission@rsl.org.ls", source_system="WHT schedule"))
        if c["tin"] == "TN1000101-3" and m in (6, 11):            # ordinary resident contractor payment
            wht_rows.append(dict(withholding_agent_tin=c["tin"], period=p, payment_date=f"{p}-12",
                                 payee_legal_name="Ha Abia Builders (Pty) Ltd", nature_of_service="Construction (resident contractor)",
                                 form="WD11(1993)", gross_amount=84000, tax_withheld=4200,
                                 submitted_via="Excel schedule emailed to WHTSubmission@rsl.org.ls", source_system="WHT schedule"))

write("vat_returns", vat_rows)
write("wht_schedule", wht_rows)
write("paye_returns", paye_rows)
write("customs_imports", cus_rows)
write("lekuka_monthly", lek_rows)

# ---------------------------------------------------------------- refund claims (written letters)
ref_rows, n = [], 1
for c in C:
    credits = [v for v in vat_rows if v["tin"] == c["tin"] and v["net_vat"] < 0 and v["capture_channel"] == "eTax online"]
    for i in range(0, len(credits), 3):              # one letter per quarter of credit periods
        batch = credits[i:i + 3]
        last = batch[-1]["period"]
        y, m = map(int, last.split("-"))
        letter = f"{y + (m == 12)}-{(m % 12) + 1:02d}-28"
        status = "Paid" if letter < "2026-06-01" else ("Under verification" if letter < "2026-10-01" else "Lodged")
        ly, lm = int(letter[:4]), int(letter[5:7]) + 3
        audit_due = f"{ly + (lm > 12)}-{(lm - 1) % 12 + 1:02d}-28"
        ref_rows.append(dict(claim_id=f"RF-{n:04d}", tin=c["tin"], vat_periods=", ".join(b["period"] for b in batch),
                             amount_claimed=-sum(b["net_vat"] for b in batch), letter_date=letter,
                             verification_due=audit_due, status=status,
                             channel="Written application to Commissioner Core Operations", source_system="Refunds (letter)"))
        n += 1
write("refund_claims", ref_rows)
write("group_register", group_rows)

companies = [dict(tin=c["tin"], name=c["name"], sector=c["sector"], district=c["district"], registered=c["registered"],
                  vat_registered="Yes", group_id=c["group"] or "", source_system="eTax registration") for c in C]
write("companies", companies)

# ---------------------------------------------------------------- organisational memory: earlier officer decisions
decisions = [
    dict(decision_id="DEC-0007", tin="TN1000769-4", tax_year_reviewed="2024/25", pattern="P12 Profit squeeze",
         observed="Net profit fell from M1.2m to M0.5m while revenue rose 6.7%",
         outcome="Explained - legitimate", reason="New warehouse at Ha Tsolo: depreciation +M0.9m and bank interest M0.6m on the construction loan",
         evidence="Warehouse construction invoices; Standard Lesotho Bank loan agreement; fixed asset register",
         conditions="Applies while depreciation stays near M0.9m and bank interest near M0.6m; related-party fees unchanged (about M0.4m); margin expected to recover within two years",
         reassess_at="2025/26 return", officer="Officer P. Mokoena (Special Sectors)", second_signoff="Not required (exposure below M1m)", decided_on="2025-11-14"),
    dict(decision_id="DEC-0004", tin="TN1001314-6", tax_year_reviewed="2023/24", pattern="P03 Fees growing faster than revenue",
         observed="Management fees rose 19% against revenue growth of 9%",
         outcome="Explained - legitimate", reason="Shared IT and procurement services agreement with parent signed 2023; fee capped at 6% of revenue",
         evidence="Signed services agreement; monthly service reports; WHT paid at treaty rate; reverse-charge VAT declared",
         conditions="Fee stays at or below 6% of revenue and the agreement remains in force",
         reassess_at="Each annual return", officer="Officer L. Thamae (Risk analysis)", second_signoff="Not required", decided_on="2024-12-03"),
    dict(decision_id="DEC-0009", tin="TN1000327-1", tax_year_reviewed="2024/25", pattern="P07 Recurring VAT credits",
         observed="VAT credits in 9 of 12 months",
         outcome="Explained - legitimate", reason="Exports to South Africa are zero-rated (about 55% of sales) while most inputs are standard-rated",
         evidence="Export documents matched to customs records; supplier invoices",
         conditions="Export share stays roughly 50-60% of sales; inputs consistent with purchases",
         reassess_at="Each refund claim", officer="Officer N. Lebona (Refund audit)", second_signoff="Not required", decided_on="2025-08-21"),
]
write("officer_decisions", decisions)

# ---------------------------------------------------------------- taxpayer responses (stage 4, released only after a request)
# One row per document. The system may only see a document after the officer has sent an
# information request covering its document_type; it then "arrives" responds_after_days later.
D_ = [
 ("TN1000430-6", "Services agreement", "Group support services agreement", 12, "Agreement signed 3 February 2026, after 10 of 12 monthly charges were already paid", "Agreement post-dates most charges"),
 ("TN1000430-6", "Basis of charge", "Group allocation schedule", 12, "Charge set as 3% of the group's regional turnover allocated to Lesotho", "Charge not linked to services received"),
 ("TN1000430-6", "Proof of services delivered", "(no document supplied)", 12, "Taxpayer states service reports are not available", "No evidence that services were delivered"),
 ("TN1000543-2", "Invoices", "Parent invoices, Apr 2025 - Oct 2026", 9, "Monthly invoices for management and technical services, about M400,000 each", "Imported services confirmed"),
 ("TN1000543-2", "VAT working papers", "VAT reconciliation for imported services", 9, "Input VAT claimed on parent invoices; no reverse-charge output VAT recorded", "Reverse charge never declared; this created the recurring credits"),
 ("TN1000656-9", "Services agreement", "Management services agreement", 14, "Fixed fee of M180,000 a month for 'management services' from April 2023", "Fixed fee from the start of the arrangement"),
 ("TN1000656-9", "Who performed the services", "Secondment letters (2)", 14, "General Manager and Financial Manager seconded full-time to Maseru since April 2023, reporting to the Lesotho board", "Full-time, under local direction: employment indicators"),
 ("TN1000656-9", "Payroll records", "Recharge statements", 14, "Both salaries paid in South Africa and recharged at cost plus 5% as the monthly fee", "Remuneration borne by the Lesotho company; no PAYE"),
 ("TN1000656-9", "Housing and vehicle records", "Lease and fleet register", 14, "Company-leased houses in Maseru West and two company vehicles for the managers", "Fringe benefits not declared (raises P09)"),
 ("TN1000769-4", "Services agreement", "Brand and procurement fee agreement", 10, "New fee of 10% of purchases payable to the parent from April 2025", "New fee falls outside the conditions of DEC-0007"),
 ("TN1000985-5", "Loan agreements", "Intercompany loan agreements (4)", 15, "Loans from Highveld Treasury Services at 11%, no repayment schedule", "Losses funded by group loans"),
 ("TN1000985-5", "Board minutes", "Board minutes 2023-2026", 15, "Minutes cite a regional market-share strategy; parent letter of support each year", "Group benefit is market share, not Lesotho profit"),
 ("TN1000985-5", "Basis of charge", "Management fee schedule", 15, "Fee of 10% of revenue, no service breakdown", "Charge not linked to specific services"),
 ("TN1001098-7", "Certificate of residence", "Certificate of residence - Pitseng Brands Ltd", 7, "Pitseng Brands Ltd is resident in Islandia, not South Africa", "Treaty rate does not apply; 25% rate applies"),
 ("TN1001201-1", "Financial statements", "Audited financial statements 2025/26", 8, "Fees of M2.0m paid to Leribe Pharma Holdings (Pty) Ltd, South Africa", "Information gap closed"),
 ("TN1001201-1", "Group structure", "Group structure chart", 8, "Leribe Pharma Holdings (Pty) Ltd is the South African parent", "Group register can be updated"),
 ("TN1001201-1", "Services agreement", "Regulatory and procurement services agreement", 8, "Agreement with monthly service reports; WHT and reverse charge already declared", "Arrangement documented: explained, legitimate"),
]
responses = [dict(document_id=f"DOC-{n:03d}", tin=t, document_type=dt, document_title=ti, responds_after_days=d,
                  content_summary=cs, what_it_shows=ws, release_condition="Officer sends an information request that includes this document_type")
             for n, (t, dt, ti, d, cs, ws) in enumerate(D_, 1)]
write("taxpayer_responses", responses)
print("CSV files written to", OUT)
