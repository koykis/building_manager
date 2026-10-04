# Source review and import policy

Phase 0 review completed for the 13-photo pilot on 2026-09-30 and the 63-photo historical extension on 2026-10-02. `data/source-manifest.csv` inventories all 76 source hashes. Copies are archived in private application storage; the current input folder is `/home/koykis/Documents/expenses/`. The earliest supplied month is April 2019; ten years of coverage are not claimed.

## Mapping

| Printed section | Allocation column | Analytic categories |
|---|---|---|
| ΚΟΙΝΟΧΡΗΣΤΑ | common | cleaning, service_contract, electricity, water, insurance, gardening, plumbing |
| ΑΣΑΝΣΕΡ | lift | passenger_lift_maintenance, passenger_lift_repairs |
| ΘΕΡΜΑΝΣΗ | heating | heating_maintenance, heating_fuel |
| ΑΥΤΟΝΟΜΙΑ | individual | heating_fuel |
| BOILER | boiler | boiler_water |
| ΕΙΔΙΚΕΣ | special | car_lift_electricity, car_lift_maintenance, heating_fuel |
| ΙΔΙΟΚΤΗΤΩΝ | owners | reserve, plumbing; review line classification |
| Equal / standalone / issuance / closed | equal / standalone / issuance / closed | classify actual lines when present |

Boiler quantity is water in m³, not hours. Car-lift participants are A1, A2, B1, F1 (ΣΤ1). Passenger-lift weights and expenses are separate. Printed historical weights are snapshots, not calculated from floor or area. Heating fixed/variable formulas are unresolved and not needed for historical transcription.

## Review policy

Preserve raw text and distinguish value, explicit zero, blank (reviewed no charge), and unreadable. A draft can contain unreadable cells; publication requires them resolved. Confirm absence before interpreting blank as zero. Parse Greek decimal commas explicitly; API canonical decimals use dots and money is represented as strings. Preserve at least three source decimal places for boiler allocations. Reconcile before publication. No silent adjustments: differences require a specific explanation, stored per check. Published corrections create a new revision, never mutate the old source.

Source classifications pending interpretation: service-contract description, instalment links for March/May lift parts, and precise project completion dates. Reserve is reported separately from expense. Additional historical files can use the same pipeline; missing months remain gaps.

Representative section checks: August 2026 = 280 + 45 + 126 + 20 = 471. January 2026 fuel combines 309.40 variable + 54.60 fixed = 364. March 2026 includes reserve and lift repair; April includes owners plumbing. Pilot transcription will retain rounding exceptions explicitly.


## Historical import completed — 2026-10-02

The 63 new photographs cover April 2019–October 2024 continuously after splitting combined statements into months. They add 67 publications, 603 apartment rows and EUR 45,051.49 in charges, including reserve contributions. Together with the pilot there are 80 published months / 720 apartment rows across 89 calendar months, April 2019–August 2026. November 2024–July 2025 has no supplied statement and remains missing. Thirteen superseded pilot revisions remain intact.

### Provider change and mapping

The old International Service Oil format runs through March 2020; EMG begins in April 2020. Common expenses and lift maintenance map directly. Old “additional expenses” containing boiler charges map to `boiler_water`, with the matching apartment allocation column. Old space-heating variable and fixed charges map to `individual` and `special` / `heating_fuel`. Source-specific heating maintenance remains in `heating`. Reserve contributions and garage charges sometimes appear under owners: preserve their printed allocation and classify the actual expense separately. March 2020's A1-only EUR 85 fuel charge maps to individual heating even though the old provider placed it under owners.

Added categories: electrical repairs, liability insurance, solar water heater maintenance and combined garage electricity/repair. Every identified financial line was mapped; no charge was dropped. Source-specific heating coefficients and fuel-equivalent technical readings without a confirmed direct meaning were not imported into modern usage fields. Occupant names were not copied. Boiler quantities retain the manager-confirmed water m³ meaning.

Common and passenger-lift allocations were checked against the printed permille snapshots. Boiler shares retain source precision (two decimals in the old format, generally three in the new); exceptional heating, owners and special allocations were transcribed directly. No apartment size assumptions replace historical weights. Garage participants remain A1, A2, B1 and F1.

### Authorized two-month estimates

| Source period | Source total EUR | First month EUR | Second month EUR |
|---|---:|---:|---:|
| July–August 2019 | 592.86 | 296.43 | 296.43 |
| July–August 2021 | 780.33 | 390.16 | 390.17 |
| July–August 2023 | 604.33 | 302.16 | 302.17 |
| August–September 2024 | 754.00 | 377.00 | 377.00 |

Each expense line is halved; an indivisible cent goes to the second month. Each monthly section is allocated proportionally to the source's apartment shares, with largest-remainder cent rounding. Apartment totals equal their monthly allocations, sections equal their lines, and every original line and statement total is conserved across its pair. Zero participation remains zero. Boiler m³ is halved as an explicitly estimated monthly quantity. These records support trends, not new bills.

Both derived months link to the same original photo. Row metrics store the original period and allocation method; allocation cells carry `estimated_two_month_split` provenance. Greek/English report labels distinguish estimates and comparisons involving estimated months. The private raw bundle retains the unsplit figures and printed apartment totals.

### Specific source qualifications

- October 2024: the photograph crops the apartment-total column. Row totals are calculated from visible allocation cells and labelled as calculated in the apartment report. Building expense totals are visible.
- March 2022 A2: displayed components total EUR 134.919 against a printed EUR 134.90. Preserve this source arithmetic discrepancy with a specific reconciliation exception.
- Old provider rounding: row sums differ by up to EUR 0.07 from the statement total (January 2020 and December 2019). Displayed components remain unchanged and individual reconciliation exceptions record the exact deltas.
- July–August 2021: the handwritten EUR 10 cleaning undercharge is posted in September's EUR 80 cleaning charge. It is not added a second time.
- March 2021: EUR 208 combines garage electricity and remaining repair costs. Use the combined category; no unsupported breakdown.
- Repairs are recorded as operating repair expenses; reserve collections remain separate. No project completion dates or payback estimates are inferred merely from a charge date.

### Reproduction, backup and verification

Private bundle: `backend/storage/app/private/history-2026-10-02/` contains `raw-statements.json`, `monthly-statements.json`, transcription/build sources, exact source checks, initial dry-run/import receipts and final verification. Do not publish this private financial data. `data/source-manifest.csv` records file hashes and statement periods; original photos are content-addressed under private `sources/`.

The reviewed monthly bundle can be checked using:

```bash
php scripts/import-history.php backend/storage/app/private/history-2026-10-02/monthly-statements.json /home/koykis/Documents/expenses
```

The default runs in a transaction and rolls back. `--publish` commits only if every record reconciles. Existing periods from the same bundle are skipped; other collisions are refused. The importer verifies input photo hashes, uses the existing statement save/publication services, audits provenance and compares all pre-existing statements before committing. Originals must be present in the supplied directory for a first import.

Pre-import backup: `.local/backups/20261002-115543/` (database, private documents and application environment). All 26 existing statement revisions were confirmed unchanged. Post-import verification checked all 67 new reconciliations, nine rows per month, archived source hashes, derived-cell provenance, 80/89 coverage and all eight estimate flags. Report regression tests and frontend localization tests/build passed; no Chrome checks were performed for this task.

Final rerun check: all 67 periods skipped as already imported, all 93 existing revisions unchanged. Receipt: private `idempotency-receipt.json`. Full backend suite: 20 tests / 92 assertions passed; Composer validation passed.
