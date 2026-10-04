# Statement CSV interchange

UTF-8 CSV with exactly two columns: `period,payload`. Each row contains a reporting month (`YYYY-MM`) and a quoted JSON statement payload. Standard CSV quoting escapes JSON quotes by doubling them. This format preserves source cells and nested allocations losslessly; use the statement export as a complete example or prepare files with a CSV library.

Payload fields:

- `period`, optional `issue_date`, `printed_total` (dot-decimal string).
- `reviewed` boolean, `exceptions` object and optional `notes`. Import always resets review to false and exceptions to empty.
- `sections`: `key`, `label`, `allocation_column`, `printed_total`.
- `lines`: `section_key`, `category_id`, `description`, `amount`, `classification` (`operating`, `capital`, `reserve`, `unclassified`).
- `rows`: `apartment_id`, `printed_total`, `metrics` object, and `cells` array.
- Each cell: `column`, `raw` source string or null, `state` (`value`, `zero`, `blank`, `unreadable`). The server calculates the canonical amount and forces printed provenance.
- Metric keys: `common_permille`, `lift_permille`, `heating_permille`, `boiler_m3`, `individual_usage`; optional source heading/unit metadata. Blank unknown metrics may be null. Boiler quantity is water in m³.

Section/column keys: `common`, `lift`, `heating`, `individual`, `boiler`, `special`, `owners`, `equal`, `standalone`, `issuance`, `closed`. A section's allocation column can differ from its own key.

Use current reference IDs from the administrator reference endpoint. Import strips exported child IDs/timestamps; it does not restore old primary keys. Money values must be strings, not JSON floating-point numbers. Use dot decimals for line/section/row totals and either Greek commas or canonical dots in raw allocation cells. Preserve printed precision.

Upload at most 500 statements / 20 MB per batch. Invalid rows are retained with errors, valid rows become drafts, and already existing periods are identified without replacement. Resume retries unresolved entries and skips imported entries. For a corrected source on an existing month, open that month and create a revision. Documents travel separately through the private upload endpoint.
