# Building expense reporting — final implementation plan

Prepared: 2026-09-30. Updated with manager clarifications: 2026-09-30. Status: administrator application implemented locally and deployed to Playground on 2026-10-02 at http://167.233.105.254/building_manager/. Phases 0–4 complete for the supplied archive and implemented features; Phase 5 local and Playground verification complete. Historical extension imported on 2026-10-02: 80 published months, April 2019–August 2026, with nine missing months. Domain/TLS and durable external backups remain follow-ups. See the implementation ledger below.

This is the working plan for future implementation in `/mnt/storage/projects/building_manager`. It supersedes conflicting implementation details in the two earlier agent specifications; the user's original scope remains authoritative. Update this document when the manager answers the questions below or implementation decisions change.

## 1. Purpose and boundaries

Build a small reporting application for one building, with Laravel as a decoupled API, Vue as the frontend, and MySQL for storage. Expect at most five concurrent users; there are nine apartments, not five. The manager enters and corrects information. The initial rollout is administrator-only. The administrator can manage and inspect all records. Once the application is completed and tested, each apartment may receive an account, activated only when the manager requests it or creates/enables it through the admin interface. Future residents can read their own apartment history and building aggregate graphs. Do not create or activate resident accounts during initial onboarding; retain the authorization design and test it with synthetic accounts.

Priorities are accurate historical reporting, expense trends, identifying recurring versus exceptional costs, and eventually evaluating whether improvements saved money. No invoicing, payments, debt collection, balance-due tracking, notice board, or tenant billing workflow. Importing existing printed allocations is historical reporting, not issuing new bills.

## 2. Evidence reviewed

Read order was `initial_prompt.txt`, then a randomized order of `Apartment_Analytics_Codex_Spec.pdf` followed by `apartment_analytics.md`. All 13 JPEGs in `/home/koykis/Documents/expenses/` were visually reviewed. Originals were left unchanged.

On 2026-10-02 the manager supplied 63 additional photos going back to April 2019 and clarified that the available archive is shorter than ten years. All were reviewed and imported as 67 monthly records. The provider changes from International Service Oil to EMG in April 2020. Directly matching financial fields were imported; nonmatching technical usage fields were omitted. The manager authorized evenly splitting two-month summer statements and reasonable apartment allocations. See `docs/IMPORT_POLICY.md` for the mapping, estimates, source qualifications and verification. Improvement evidence should be extracted from these sheets rather than requiring a separate pre-existing project register.

The photos are monthly Greek consolidated expense/allocation statements. They contain building expense lines and a separate apartment allocation matrix. The statement month is different from the issue date, generally in the following month. The photography date in the filenames is not an expense date.

### Photo manifest

The following totals are preliminary visual readings, useful for import checks, not a fully verified transcription of every cell.

| File | Statement period | Printed grand total EUR |
|---|---|---:|
| `20260930_141352.jpg` | 2025-08 | 528.00 |
| `20260930_141340.jpg` | 2025-09 | 489.00 |
| `20260930_141317.jpg` | 2025-10 | 689.00 |
| `20260930_141255.jpg` | 2025-11 | 829.00 |
| `20260930_141234.jpg` | 2025-12 | 970.00 |
| `20260930_141203.jpg` | 2026-01 | 1,210.65 |
| `20260930_141138.jpg` | 2026-02 | 1,035.20 |
| `20260930_141126.jpg` | 2026-03 | 1,227.00 |
| `20260930_141105.jpg` | 2026-04 | 794.00 |
| `20260930_141050.jpg` | 2026-05 | 846.00 |
| `20260930_141015.jpg` | 2026-06 | 896.99 |
| `20260930_140932.jpg` | 2026-07 | 581.84 |
| `20260930_140913.jpg` | 2026-08 | 471.00 |

The inspected sample contains 13 consecutive months, August 2025–August 2026. August is the only same-month year-over-year pair. September 2025–August 2026 supports one complete trailing 12-month total, but not a comparison with the preceding complete 12 months. No September 2026 statement is in this set; do not assume it is overdue or missing from the manager's records.

### Actual apartment structure

The printed unit labels are Α1, Α2, Β1, Β2, Γ1, Δ1, Ε1, ΣΤ1, Ζ1. Preserve Greek labels and use stable database identifiers; visually similar Latin letters must not create duplicate units. Names on the sheets are not account identifiers and should not be copied into public fixtures or this plan.

The following weight readings appear consistent across the sheets, but should be verified during onboarding:

| Apartment | Common ‰ | Lift ‰ | Heating ‰ |
|---|---:|---:|---:|
| Α1 | 74 | 63 | 66.80 |
| Α2 | 69 | 57 | 71.80 |
| Β1 | 74 | 68 | 66.80 |
| Β2 | 69 | 62 | 71.80 |
| Γ1 | 149 | 140 | 153.20 |
| Δ1 | 149 | 150 | 153.20 |
| Ε1 | 149 | 160 | 153.20 |
| ΣΤ1 | 149 | 170 | 153.20 |
| Ζ1 | 118 | 130 | 110.00 |
| Total | 1000 | 1000 | 1000 |

The manager confirms two apartments on each of floors 1–2, and one almost double-size apartment on each of floors 3–7. Map Α1/Α2 to floor 1, Β1/Β2 to floor 2, Γ1 to floor 3, Δ1 to floor 4, Ε1 to floor 5, ΣΤ1 to floor 6 and Ζ1 to floor 7. Square metres are not supplied: keep them optional and do not derive exact areas or allocation weights from approximate relative sizes. Support explicit English aliases A1, A2, B1, B2, C1, D1, E1, F1 and G1; in particular the manager's F1 means ΣΤ1, not Ε1.

### What the layout means for the model

- **Common expenses / ΚΟΙΝΟΧΡΗΣΤΑ:** cleaning, `ΣΥΜΒΑΣΗ ΕΡΓΑΣΙΩΝ` (retain this label until its meaning is confirmed), electricity, occasional water, fire insurance, gardening and plumbing.
- **Lift / ΑΣΑΝΣΕΡ:** regular €45 maintenance plus occasional repairs/parts. March and May 2026 show €165 parts entries apparently representing instalments; confirm their relationship before linking them to a project.
- **Heating / ΘΕΡΜΑΝΣΗ and autonomy / ΑΥΤΟΝΟΜΙΑ:** distinct sections. December–February show an 85% usage component and a 15% fixed component; the latter appears inside special expenses. Do not identify heating cost only by the heating column.
- **Boiler / BOILER:** a monthly building amount, apartment values in a column headed hours, and allocated amounts sometimes printed to three decimal places. The manager confirms these values represent cubic metres of water used (m³), despite the template heading. Store and display boiler consumption in m³, preserve the original heading as source metadata, and keep it distinct from individual-heating usage. Do not treat it as energy consumption or infer a heating tariff from it.
- **Special / ΕΙΔΙΚΕΣ:** garage electricity, occasional garage maintenance, and fixed heating costs. One printed column can contain multiple analytic categories and allocation rules.
- **Owners / ΙΔΙΟΚΤΗΤΩΝ:** reserve contributions (`ΑΠΟΘΕΜΑΤΙΚΟ`, €50 in several months) and an April 2026 €240 plumbing/burner-related entry. Owners' charges are not automatically capital expenditure.
- Other template columns include closed units, equal shares, standalone charges and statement issuance. Preserve their presence, but do not build speculative workflows around currently blank columns.

The manager confirms that garage expenses relate to a car elevator with a 2+2-car arrangement, and that its participating apartments are **Α1, Α2, Β1 and ΣΤ1 (F1)**. Keep car-lift electricity and maintenance separate from the building passenger lift. The manager corrected the earlier Β2 reference to Β1, resolving the apparent discrepancy with the inspected sheets. Preserve printed historical allocations. Record effective periods for participation; do not assume the current group held for all ten years. Winter special-column amounts combine garage and fixed heating charges; their individual apartment splits cannot always be recovered from the printed column alone.

Example: August 2026 reconciles at section level as €280 common + €45 lift + €126 boiler + €20 special = €471. January 2026 shows €309.40 variable heating and €54.60 fixed heating, totaling €364; these must be combined for a heating report without counting either twice.

## 3. Main corrections to the previous architecture

1. Replace one apartment `share_ratio` with separate, historically preserved allocation weights and usage observations.
2. Store building expense lines and printed apartment allocations as distinct records. They represent two views of the same money and must never be added together.
3. Start with transcription and reconciliation. Automatic allocation is optional assistance after the building's rules are confirmed, not the source of historical truth.
4. Keep analytic categories independent of printed sections. For example, heating crosses multiple sections, while special expenses contain both heating and garage costs.
5. Separate reserve contributions from operating expenditure and project expenditure. Include them in a clearly labelled printed-statement total; exclude them from expense/savings metrics to avoid double counting later reserve-funded work.
6. Separate reporting month, issue date and optional invoice/service dates. Historical monthly charts use the printed reporting month.
7. Remove fabricated production seed history and example solar projects. Use synthetic, clearly isolated development fixtures only.
8. Replace savings clipping (`max(0, baseline - actual)`) with signed savings. Increased costs must reduce cumulative savings.
9. Do not cascade-delete historical expenses when a category is removed, or turn apartment-specific records into common costs by deleting an apartment.

## 4. Data design

Use a single-building application; no multi-building tenancy framework is needed. EUR amounts, UTF-8 Greek text, Europe/Athens display dates, and explicit month identifiers are required. Use decimal arithmetic throughout; API money values should be decimal strings rather than binary floating-point numbers.

| Entity | Main fields and responsibilities |
|---|---|
| `apartments` | Stable ID, unique unit label, confirmed floor, optional area, explicit English alias, active flag. Archive instead of deleting referenced units. |
| `users` | Name, email, password hash, admin/resident role, nullable apartment ID, active flag. Administrator account only at launch. Later, one account per apartment as requested, provisioned/enabled by the manager; no automatic activation. Resident accounts have an assigned unit. Admin controls access. |
| `source_documents` | Original filename, private storage path, hash, media type, upload metadata. A statement can have multiple supporting documents. |
| `statements` | Period, issue date, source reference, revision, draft/published/superseded state, printed grand total, review status and notes. At most one current published revision per period. |
| `statement_sections` | Statement ID, stable section key, original Greek heading, printed subtotal, reviewed completeness. |
| `expense_categories` | Normalized reporting category, display names, active flag. No destructive deletion while referenced. |
| `expense_lines` | Statement/section/category IDs, original description, amount, classification (`operating`, `capital`, `reserve`, `unclassified`), optional service dates/vendor/project link and notes. Classification is per line, not forced by the printed section. |
| `apartment_statement_rows` | Statement ID, apartment ID, printed row total and source row position; unique per statement/apartment. |
| `allocation_cells` | Apartment row, printed allocation-column key, raw text, decimal amount, blank/zero/unreadable state, provenance (`printed` or `derived`). Store printed components separately from calculated checks. |
| `apartment_statement_metrics` | Statement/apartment, common/lift/heating permille snapshots, boiler water consumption in m³ and separate individual-heating usage values, raw source labels and explicit units (individual-heating unit to confirm). Preserve historic inputs. |
| `allocation_rules` (later) | Effective periods, participating apartments, weight or usage basis, fixed/variable split, rounding policy and confirmation notes. Never retroactively rewrite printed allocations. |
| `capital_projects` (later) | Title, nullable completion date with precision/confirmation status, first full assessment month when known, description, linked capital expense lines, optional documented external cost and evidence. Statement period is evidence of posting, not necessarily completion. |
| `project_baselines` (later) | Target categories, baseline months/method, seasonal values, exclusions, limitations and version. |
| `audit_events` | Actor, timestamp, action, affected record/revision, before/after values or equivalent change history. |

Building line amounts and printed row totals use two decimal places. Allocation cells need at least three (suggest `DECIMAL(14,4)`); usage and weights also use decimals. Keep original text so `36,107`, `0,00`, an empty cell and an illegible value remain distinguishable. Do not round every cell before comparing it with a row total.

A source section may not map one-to-one to an allocation column. Keep an explicit mapping for reconciliation (for example autonomy expense to individual-heating allocation). Do not create invented per-category apartment details when the source only provides a combined column. If a later calculation splits such a column, label that split as derived and retain the printed aggregate.

Draft records support CRUD. Published corrections create a revision with an audit trail and replace the current reporting version atomically; prior revisions remain inspectable by the administrator. Archiving reference data must preserve past reports.

## 5. Entry, import and reconciliation workflow

The first release should make manual monthly entry practical and provide a resumable batch-import/review workflow for the decade-long archive. Use the 13 inspected sheets as a pilot, then ingest older years in manageable batches. Maintain a period inventory showing available, imported, reviewed and published months, gaps and duplicate/replacement sources. Record progress so interrupted work can resume. Assess assisted OCR against a reviewed sample before investing in automation; all extracted values remain drafts until verified.

1. Upload the source privately; hash it to detect duplicate files. Select the printed period and issue date independently.
2. Enter expense lines in the same section layout as the sheet. Preserve source descriptions and assign reporting categories and classifications.
3. Enter the nine apartment rows, printed allocation columns, row totals, weights and usage values. Show the image beside the entry grid with rotation and zoom.
4. Prefill stable unit labels and previous weights as suggestions. Never copy prior monetary amounts or usage values as confirmed new data.
5. Reconcile line sums against section subtotals; sections against the grand total; allocation columns against their mapped sections; component sums against apartment totals; and apartment totals against the statement total.
6. Show exact differences, including sub-cent and cent rounding differences. Never silently alter the source to force a match. The reviewer must resolve an error or record a specific source/rounding exception before publication.
7. Preserve blank and unknown states until reviewed. A confirmed absence of a charge may become zero for reporting; an unreadable cell may not.
8. Publish only after required values, category mappings and reconciliation checks are reviewed. Unresolved classification can remain explicitly unclassified and visibly excluded from classification-dependent metrics.
9. Corrections and reimports must not duplicate financial records. Same-period uploads prompt an explicit revision or supporting-document association.

Include structured CSV import/export for bulk onboarding using the same draft/review workflow. During archive inventory, check whether original digital exports exist; photographs remain supported. OCR is an optional proposal generator for the larger archive; it must preserve source references and never publish automatically. Do not send the resident-bearing photos to an external OCR service without an explicit decision to do so.

## 6. Reports and screens

### Administrator

- Dashboard with date range, data coverage, operating expenditure, exceptional/capital expenditure, reserve contributions and printed statement totals as separately named figures.
- Monthly statement list with draft/published state, source, reconciliation status and revision history.
- Statement editor with image and expense/allocation grids.
- Building trends by normalized category and by printed section, recurring versus exceptional spending, and month-over-month changes.
- Apartment history and comparisons, visible only to administrators.
- Apartment, category and account management.
- Later: project entry and documented savings analysis.

### Resident — deferred activation

Prepare and test this access path before rollout, but do not enable it until the manager requests access or provisions accounts after completion and testing.

- Own apartment's monthly printed totals and available component breakdowns.
- Building aggregate monthly/category graphs and eligible year-over-year comparisons.
- Later: sanitized building project summaries and clearly qualified savings estimates.
- No other apartment rows, names, usage readings, unit comparison charts, private notes, raw statements or unrestricted exports.

Provide both Greek and English interfaces from the first release, with Greek as the default and a persistent language selector. Localize navigation, forms, validation, table headings, chart labels, dates and number formatting; EUR remains the currency. Use translation keys rather than hard-coded UI text. Preserve original Greek source descriptions independently of translated category labels; do not overwrite source text when switching language. Provide chart-equivalent tables, useful empty states, and mobile layouts. Verify both languages, including fallback behavior and locale-aware input parsing.

### Comparison rules

Only published current revisions enter reports. A complete confirmed zero is different from an unavailable month. Missing months create gaps, not zero spending. Report coverage alongside totals and compare equivalent month sets.

Same-month YoY: `(current - prior) / prior * 100`, with the absolute EUR change also shown. A zero prior value produces an explicit undefined percentage/new expense label, not infinity. With only the pilot imported, offer August 2026 versus August 2025 and show unavailable states elsewhere. As older reviewed statements are published, automatically enable supported same-month, annual and rolling-year comparisons, plus multi-year category trends. Do not hard-code the sample dates or equate the inspected sample with the full available history. Full-year comparisons require complete equivalent month sets; incomplete years must be explicitly labelled.

Expense growth alone is not inflation: these photos lack reliable energy volumes, tariffs and service-period detail. Label findings as spending changes. Reserve contributions and one-off repairs should not masquerade as recurring price increases. Utility amounts posted in a month are not necessarily that month's measured consumption.

## 7. Savings and investment analysis (after trustworthy reporting)

During historical import, capture candidate interventions from exceptional expense lines and descriptions: what changed, statement period, reported cost, affected categories and source references. Group related instalments and changes for administrator review. Later add a confirmed intervention log, including actual completion dates where known. A separate invoice is optional supporting evidence, not a prerequisite when the sheet adequately documents a cost. Repairs do not automatically become investment projects, and a reserve collection is not an investment cost.

A project links to actual expenditure lines so its cost is not counted twice. Instalments can belong to one project. Any external project cost must be explicitly documented and distinguished from already imported costs.

For an agreed baseline and each complete eligible post-completion month:

- `estimated_savings = comparable_baseline - actual_target_expense` (signed).
- `cumulative_estimated_savings = sum(eligible_monthly_savings)`.
- `net_estimated_benefit = cumulative_estimated_savings - documented_project_cost`.
- First observed break-even is the first chronological crossing of project cost. Later negative months can bring cumulative savings below cost again; report current coverage separately.

Prefer same-season baseline months for seasonal costs. A simple fixed monthly baseline is allowed only as an explicitly chosen estimate with limitations. Exclude the partial completion month by default. Gaps invalidate a claim of complete cumulative savings/payback; do not treat missing actuals as zero. Avoid double attribution when several projects affect the same category.

Do not display payback for zero/unknown investment cost, inadequate baseline coverage or incomplete post-periods. Forecast payback, if later added, must be labelled a projection with its assumptions. Observed spending differences do not establish causality: weather, occupancy, service timing and energy prices are not controlled by these sheets. The manager confirms that improvements and changes are recorded in the wider archive. Extract and review them before seeding real ROI projects. A sheet may establish a charge and reporting month without establishing the installation/completion date, intended savings or full project cost; ask only for those missing details when a concrete project needs them. Repairs can be logged as events even when no financial payback is expected.

## 8. Technical implementation

Use the requested Laravel API, Vue 3 application and MySQL database, with Vue Router, Pinia, Vite and a chart library such as Chart.js. Keep backend and frontend separately organized (proposed `backend/` and `frontend/`). Select and verify compatible, supported dependency versions against official documentation when implementation begins; do not blindly pin the old Laravel 11 blueprint. No Redis or separate analytics warehouse.

Use Laravel Sanctum session-cookie authentication for the first-party browser application, with CSRF protection and explicit frontend origin/session configuration. Prefer serving the frontend and API through one production origin while preserving the decoupled codebases. Do not store bearer credentials in localStorage. Admin provisions accounts; no public self-registration. Launch with only the administrator enabled. Resident activation is a separate, explicit admin action after completion and testing, not a side effect of importing apartments.

Suggested API surface:

| Area | Endpoints / policy |
|---|---|
| Authentication | Login, logout, current user; rate-limited login and session invalidation |
| Admin records | `/api/v1/admin/statements`, nested lines/rows/documents, review/publish/revise actions; admin only |
| Admin setup | `/api/v1/admin/apartments`, categories, users; admin only |
| Building reports | `/api/v1/reports/building`, categories, comparison; fixed aggregate response shapes |
| Own apartment | `/api/v1/reports/my-apartment`; apartment derived from authenticated user |
| Projects (later) | Admin project CRUD and separate sanitized resident report endpoint |

Enforce authorization and response-field scoping in the backend, including downloads and exports. Frontend route guards are only navigation assistance. Aggregate endpoints must not accept arbitrary apartment filters or grouping that exposes individual apartments. Admin-only files stay outside public storage and require authenticated, authorized download routes. Do not leak raw rows through errors, logs or aggregate response payloads.

Use transactions for imports, publication and revision changes, appropriate uniqueness constraints, and indexes on reporting period, statement/category and apartment/statement relationships. Protect against conflicting edits with a revision/version check. Use foreign-key restrictions and archive flags to protect history. Credentials, source photos and real names must not be committed to the repository.

Deployment requires HTTPS, environment-specific secrets, database and private-document backups, and a tested restore procedure. Exact hosting details remain to be agreed. Keep the runtime simple; introduce background work only if measured import needs justify it.

## 9. Delivery phases and acceptance criteria

### Phase 0 — confirm semantics and prepare import references

Apply the confirmed facts below; resolve remaining questions only where they affect interpretation. Inventory the available archive by year/period and source format before scheduling bulk entry. Recheck photo periods/totals, allocation labels and ambiguous readings, using the confirmed garage participants and boiler unit (m³). Use August 2026 as a simple sample; January 2026 for mixed heating/special expenses; March 2026 for reserve and lift parts; April 2026 for owners' maintenance.

Acceptance: source manifest, category mapping, blank/zero policy and unresolved assumptions are recorded. Manual printed allocations remain supported even when formulas are unknown.

### Phase 1 — foundation and permissions

Scaffold backend/frontend, migrations, authentication, policies and private document handling. Seed nine unit labels, floor mappings, explicit aliases and verified reference categories; no invented financial history. Enable only the administrator account. Add Greek/English localization infrastructure with Greek default. Provide separate synthetic development data.

Acceptance: administrator can manage reference records; residents cannot reach admin routes or another apartment's records/files, including by tampering with request IDs.

### Phase 2 — statement CRUD and review

Build expense/row entry, source viewer, reconciliation, draft handling, publication and revisions, plus resumable batch tracking and CSV import validation. Add decimal and locale parsing, source precision preservation and duplicate prevention.

Acceptance: the representative sheets can be entered faithfully; August 2026 section totals reproduce €471; winter heating is not lost in special expenses; rounding discrepancies are visible and explained; corrections preserve history.

### Phase 3 — historical onboarding and core dashboards

First transcribe and review the 13 pilot sheets and their 117 apartment rows. Then progressively import the available ten-plus years, tracking missing months and historical roster/weight changes instead of assuming the current configuration always applied. Prioritize enough contiguous history for meaningful comparisons and periods around identified improvements; do not require the whole archive to be entered before releasing the admin reporting MVP. Build administrator aggregate/apartment dashboards, coverage indicators and comparisons that expand with the published history. Prepare the resident view for later activation.

Acceptance: each published statement reconciles or has a documented source exception; administrator charts match reviewed data; residents see only their own rows; only supported YoY periods are available. Operating and reserve amounts remain distinguishable.

### Phase 4 — interventions and savings

Promote reviewed intervention candidates from imported sheets into project records where appropriate. Request supplementary information only for missing project dates, costs or attribution details. Implement baseline methods against eligible imported history. Add signed savings, data-quality gating and project-cost linkage.

Acceptance: higher actual costs reduce savings; missing months cannot create savings; seasonal baseline and partial-month choices are visible; project costs and overlapping savings are not double counted.

### Phase 5 — release readiness

Complete responsive/accessibility and Greek/English review, deployment configuration, backups/restore and short administrator instructions for monthly entry, batch history import and correction. Release to the administrator only.

Acceptance: production build and relevant backend checks pass; a backup can be restored with documents; the administrator session passes end-to-end checks and synthetic resident accounts pass isolation checks in testing. No production resident account is activated automatically. After completion/testing, the manager can request apartment accounts or create them through admin; settle historical visibility before enabling them.

### Focused verification

Automated coverage should exercise authorization across lists/details/downloads/exports, Greek decimal parsing and three-decimal cells, boiler m³ labels despite the source hours heading, duplicate/revision handling, publication transactions, reconciliation with precision differences, reserve exclusion, category/section mapping, missing-versus-zero comparisons, zero-denominator YoY, signed savings with chronological gaps, alias mapping (F1 to ΣΤ1), both UI languages with Greek default, batch resumption, and prevention of automatic resident activation. Use anonymized minimal fixtures based on the structural cases above. Do not broaden into a billing test suite.

## 10. Confirmed decisions and remaining information

### Confirmed by the manager on 2026-09-30

- At least ten years of similar expense sheets exist, including improvements, extra expenses and changes. The 13 reviewed images are a pilot sample, not the extent of the history.
- Garage expenses concern a car elevator with a 2+2-car arrangement; participants are Α1, Α2, Β1 and ΣΤ1 (F1).
- Floors 1 and 2 have two apartments each; floors 3–7 have one approximately double-size apartment each. Exact areas are optional.
- Initial platform access is administrator-only. Apartment accounts are deferred until completion/testing and the manager's later request or admin action.
- Both Greek and English are required, with Greek the default.
- Boiler usage values represent cubic metres of water used (m³), regardless of the printed hours heading.
- The subsequent garage correction confirms Β1 rather than Β2; the source discrepancy is resolved.

### Still useful now, but not blockers for source-faithful reporting

1. **Heating allocation (only needed for automated calculation):** How are the fixed/variable space-heating allocations determined? Boiler water consumption is now confirmed as m³; do not confuse it with space-heating usage. Until a calculation rule is confirmed, use printed allocations for reporting.
2. **Coverage gap:** November 2024–July 2025 is not present in either supplied batch. Request these nine months when available; do not interpolate the gap.

### Resolve later when the relevant work starts

- **For production hosting beyond Playground:** domain/TLS and durable external backup destination. The manager supplied Playground and explicitly requested its existing HTTP app-path hosting pattern on 2026-10-02.
- **Before resident activation:** whether an apartment account should expose the unit's entire history or only a permitted period. No need for resident names/emails now.
- **During classification:** meaning of `ΣΥΜΒΑΣΗ ΕΡΓΑΣΙΩΝ`, unclear owners' entries, reserve treatment and whether particular parts charges are instalments of one repair. Record uncertainties on concrete entries instead of blocking all import.
- **During project analysis:** completion date, full cost or scope only when the sheets do not establish it. Do not ask again whether improvements or older history exist.

## 11. Instructions for the next implementation session

Read this file first, then incorporate any subsequent manager answers. Treat the earlier PDF/Markdown as historical proposals, not competing specifications. Start with Phase 0 and Phase 1; do not begin with ROI or a general allocation engine. Preserve source photos and avoid publishing any data until its review status is established. Update this plan with resolved questions, completed phases and concrete validation evidence as work progresses.

## 12. Implementation ledger

| Phase | Status | Evidence / remaining work |
|---|---|---|
| 0 | Complete (supplied archive) | 76 source photos inventoried and reviewed; provider change and four combined periods mapped. See `data/source-manifest.csv` and `docs/IMPORT_POLICY.md`. |
| 1 | Complete | Laravel 13 / Sanctum, Vue bilingual shell, MySQL migrations and nine reference units; `FoundationTest`: 4 tests / 17 assertions passed; frontend production build passed. No resident accounts seeded. |
| 2 | Complete | Statement CRUD, decimal reconciliation, review/publication/revisions, private source viewer and resumable CSV batches. Backend tests pass, including stale writes, immutable publication, source rounding and duplicate imports. |
| 3 | Complete (supplied archive) | 80 published months, 720 current apartment rows; private originals, source rounding exceptions and eight labelled estimated months. Building/category/apartment reports and prior-year estimate flags verified. November 2024–July 2025 remains a gap. |
| 4 | Complete (implementation) | Intervention candidates from real source lines, project cost linkage, documented seasonal baseline inputs, signed savings and evidence gating. Tests cover negative savings, missing periods and overlapping projects. No real payback claim without confirmed completion/baseline evidence. |
| 5 | Complete (local administrator release and Playground deployment) | Earlier local browser and restore checks passed. Playground at `/building_manager/` verified on 2026-10-02: 20 backend tests / 92 assertions, four frontend tests, subpath production build, HTTP sign-in/out, CSRF, reports, private source download and CSV export. Imported 80 published months / 720 current rows; all 88 private files verified. Existing `/filosafe/` stayed available. No Chrome E2E tests run for this deployment, per manager instruction. Domain/TLS and durable external backups remain follow-ups. |

### Implementation evidence and deliberate schema choices

- Runtime: Laravel 13.34, Sanctum 4.3, Vue 3, Vite 7, MySQL 8.4; Node 20.19.5 used for builds. Lockfiles are included.
- Final automated result: 19 backend tests / 77 assertions, 2 frontend localization tests and 2 Chrome browser tests passed; frontend production build and Composer validation passed.
- Test suites: `backend/tests/Feature`, `frontend/src/i18n.test.js`, `frontend/e2e/admin.spec.js`. Browser checks cover login/logout, both languages, pilot dashboards, private source access, mobile layout and temporary draft revision editing/deletion.
- Backup drill receipt: `.local/backups/20260930-163937/verification.json`. It verified all table row counts and 15 private-file hashes against an isolated restored database; the live database was not overwritten. This snapshot predates the later source-heading revisions.
- Pilot source-heading corrections were made via audited revisions, with monetary values unchanged. Superseded revisions are available through the history toggle. Temporary browser-test drafts were deleted; their audit events remain.
- Metric snapshots are stored in the apartment row's JSON `metrics` column rather than a separate metrics table. Baseline configuration is JSON on each project. Cost links have a dedicated relational table with a unique expense-line constraint. This keeps the small application simpler while retaining precision and source history.
- `README.md` documents setup, private local credentials, monthly entry, corrections, resident activation, deployment and recovery. `docs/CSV_FORMAT.md` describes the resumable CSV interchange.
- Space-heating allocation automation is intentionally deferred until the formula is confirmed; printed allocations already support complete historical reporting. No external OCR service was used.

### Remaining external follow-ups

1. Supply November 2024–July 2025 if available. All 76 supplied photos are imported; coverage begins April 2019.
2. Confirm completion dates and baseline assumptions for a specific intervention before treating its spending comparison as a payback estimate.
3. Configure domain/TLS and a durable external backup destination when moving beyond the requested Playground HTTP hosting.
4. Activate apartment accounts only when requested by the manager; decide historical visibility first. No resident accounts are currently enabled.


### Historical extension completion — 2026-10-02

- Source review / Phase 0 extension: complete. 63 additional photos; old provider through March 2020, new provider from April 2020. All financial lines mapped; no unsupported technical metric mapping.
- Import / Phase 2–3 extension: complete. Added 67 months, EUR 45,051.49 including reserves. Preserved 26 existing pilot revisions; 80 current publications remain plus 13 superseded revisions. Four combined statements generate eight estimated months with conserved totals and source links.
- Reporting / Phase 3 extension: complete. Greek/English estimate labels, prior-year estimate flags and calculated-total label for October 2024's cropped row totals. Source quantities remain water m³. Category pie and zero-minimum line axis retained.
- Validation / Phase 5 extension: complete. All 67 imported reconciliations and source hashes verified; full backend suite 20 passed / 92 assertions; localization tests 2 passed; frontend production build passed. No Chrome checks in this extension. Pre-import backup `.local/backups/20261002-115543/`.
- Durable import policy, qualifications and rerun instructions: `docs/IMPORT_POLICY.md`. Private raw/transformed data and receipts: `backend/storage/app/private/history-2026-10-02/`.

### Queued tasks

- [x] Set the default date range to Year to Date. Completed locally on 2026-10-02: January through the current local calendar month, with missing statements shown as unavailable. Published-history shortcuts remain available. Validation: eight frontend unit tests, nine browser checks (including the real local database), and the frontend build passed. Online deployment awaits the manager's approval.
