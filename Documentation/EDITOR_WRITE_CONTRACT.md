# Restaurant editor write contract

Design-only contract for the first safe write-capable compact-editor import.

This document is canonical for EDITOR-2B0. It does **not** implement writes.

Status: **DESIGN ONLY** for Apply / DataHandler writes. EDITOR-2B2 implements **read-only** identity resolution. EDITOR-2B3 implements **read-only** ApplyPlan + ApplyReady confirmation preview. Restaurant records remain unwritten.

## EDITOR-2B1 implementation status

EDITOR-2B1 implements an **editable temporary draft** and **server-authoritative revalidation**. It does not change sections B–N.

| Implemented | Not implemented |
|---|---|
| Compact cell editors on bulk preview rows only | Identity resolution (create / reuse / ambiguous) |
| `BulkDraftValidator` rebuilds normalized draft from posted strings | `ApplyReady`, Apply / Import / Save |
| POST `bulkDraftRevalidate` (dedicated CSRF action) | DataHandler, QueryBuilder writes, Extbase writes |
| `DraftValid` = cell + Placement-run validation passed | Database Item/Category title matching |
| View search/sort remain presentation-only | `Item.sku`, `Placement.menu_code`, `public_uuid` minting |

Successful revalidation is **DraftValid**, not ApplyReady. Suggested UI copy: “Draft is valid. Identity resolution has not been performed yet.”

Semantic draft order is `originalOrder` (parsed order). The server restores that order before Placement-run validation. Client DOM order, visible `#`, search hiding, and sort must not become the server draft order. All posted rows are revalidated, including rows hidden by search.

The existing **65536-byte** bound is reused as the maximum concatenated length of editable Category + Item + Variant + Price strings on revalidate. Nested form metadata is not an extra unbounded payload. Maximum **200** rows remains.

Transitions that **must not** write still include: parse, preview render, cell edit of draft values, search, sort, filter, restore order, reset draft, numbering, `bulkDraftRevalidate`, and `bulkDraftReset`.

## EDITOR-2B1.1

Corrective runtime: draft cell `input` must not reparent rows. Client dirty state is “Draft changed — revalidate.” until the next server round-trip. **Restore order** is view-only (keeps edits). **Reset draft** is POST `bulkDraftReset` and rebuilds from preserved `bulkSource` via `BulkMenuParser` + `fromParsedRows()`. Variant stays `PriceOption.label` scoped to a Placement run. `singleNamedVariant` is a warning; `duplicateVariant` is blocking. `errors` and `warnings` are separate lists. Warnings do not clear `DraftValid`. Fluid ObjectAccess collisions on `BulkDraftRow::$warnings` / `hasWarnings()` and `BulkDraftValidationResult::$globalError` / `hasGlobalError()` were removed.

### EDITOR-2B1.1 — PASS / TYPO3 13 + TYPO3 14 LIVE ACCEPTED

Accepted on both supported lines at extension SHA `bd53dbe9acd08ac1df96113747027f0ed59502ae`:

- TYPO3 13.4.34
- TYPO3 14.3.6

Accepted: editable temporary bulk draft, server-authoritative validation, continuous typing / focus stable, search / sort / Restore order, Reset draft, mixedVariantRun / duplicateVariant blocking, singleNamedVariant warning, Fluid warnings/globalError accessor fixes. No restaurant-record writes, no DataHandler, DB unchanged.

Canonical identity remains [DOMAIN_MODEL.md](DOMAIN_MODEL.md) section E. This contract consumes that identity; it does not add `Item.sku`, `Placement.menu_code`, or provider-specific IDs.

Supported TYPO3 lines: **13.4 LTS** and **14.3 LTS**.

## EDITOR-2B2 implementation status

EDITOR-2B2 adds **read-only** identity resolution after DraftValid:

| Implemented | Not implemented |
|---|---|
| Explicit POST `bulkIdentityResolve` (dedicated CSRF) | Apply / Import / Save / write Apply |
| Revalidate posted draft before any identity read | DataHandler, QueryBuilder writes, Extbase writes |
| Target Menu re-resolved by uid+pid+default language | Menu creation |
| Item CREATE / REUSE / AMBIGUOUS (pid-wide; matchKey = whitespace-normalized + Unicode case-folded) | `Item.sku`, `Placement.menu_code` |
| Category CREATE / REUSE / AMBIGUOUS (target Menu only) | `public_uuid` minting or repair |
| Last-seen `uid` / `public_uuid` / `tstamp` / `pid` snapshots on REUSE | POS / ARP.top integrations |
| Future create/reuse/ambiguous summary + Placement/PriceOption counts | Automatic resolution on Preview/load/typing |
| Compact Create/Reuse/Ambiguous badges; no Apply button | |

Missing/unusable `public_uuid` on a sole REUSE candidate fails closed (`inaccessible` + blocker). Outcomes are `identityResolved` or `resolutionBlocked` — not ApplyReady. Future Apply must re-check concurrency and access.

Transitions that **must not** write still include: parse, preview, cell edit, search, sort, restore order, reset, revalidate, **and** identity resolution.

## EDITOR-2B3 implementation status

EDITOR-2B3 adds a **read-only exact ApplyPlan** and confirmation preview after `identityResolved`:

| Implemented | Not implemented |
|---|---|
| Explicit POST `bulkApplyPrepare` (dedicated CSRF `prepareToken`) | DataHandler / `process_datamap` / `process_cmdmap` |
| Revalidate posted draft + re-resolve identities (server-authoritative) | Confirmed / Applying / Applied |
| Pure `BulkApplyPlanBuilder` → `ApplyPlan` (no QueryBuilder, no writes) | Apply / Import / Save / Confirm & save button |
| Outcomes `applyReady` \| `preparationBlocked` | Session / localStorage draft persistence |
| Confirmation preview card (append-only semantics) | `Item.sku`, `Placement.menu_code`, schema/TCA changes |
| Deterministic SHA-256 plan fingerprint (confirmation continuity only) | Fingerprint compare on write (EDITOR-2B4) |

State machine for this gate:

```
identityResolved
  → explicit Prepare apply
  → revalidate + re-resolve
  → ApplyReady (or preparationBlocked)
  → future explicit confirmation/write (EDITOR-2B4)
```

The fingerprint is **not** authentication, authorization, CSRF, or external identity. EDITOR-2B4 must rebuild the plan immediately before DataHandler and compare fingerprints; mismatch blocks and requires confirmation again.

Transitions that **must not** write still include Prepare apply. Editing Category / Item / Variant / Price stales identity badges and ApplyPlan; search / sort / Restore order do not.

Warnings (e.g. `singleNamedVariant`) remain non-blocking: a warning-only DraftValid draft may become ApplyReady.

---

## Current audit (as of this document)

Inspected in-repo sources, not a live DataHandler run:

- TCA: Menu → IRRE Category → IRRE Placement → reusable Item + IRRE PriceOption. `public_uuid` is TCA `type=uuid` v4, required, `l10n_mode=exclude`. All five tables have `tstamp`, `deleted`, `versioningWS`, localization fields. No title UNIQUE. No `UNIQUE(category,item)`. No `sku` / `menu_code` columns.
- `BulkMenuParser` / `BulkMenuRow`: TSV parse + validate only. No database. One pasted row is one preview row, not an Item.
- `BulkDraftValidator` / `BulkDraftRow`: POST `bulkDraftRevalidate` rebuilds normalized draft state (`cleanDisplayTitle` on Category/Item/Variant, `DecimalMinorUnitParser`, Placement-run rules via `BulkDraftRunGrouping` matchKeys). No QueryBuilder identity lookup. No writes.
- `RestaurantIdentityReader`: QueryBuilder **SELECT only** loads bounded default-language Item candidates for the selected pid and Category candidates for the selected pid + target Menu (DeletedRestriction, WorkspaceRestriction, `sys_language_uid=0`; hidden/scheduled included). Title matching is **not** done in SQL.
- `RestaurantTitleNormalizer` / `BulkIdentityResolver`: identity comparison uses `matchKey` = Unicode whitespace-normalized + Unicode case-folded title. Display titles stay case-preserving (`cleanDisplayTitle`). No fuzzy matching. Unit-tested without DB.
- `BulkDraftValidator` applies `cleanDisplayTitle` to Category/Item so spacing mistakes are cleaned in the draft without changing capitalization.
- `RestaurantEditorController`: QueryBuilder reads via `MenuGraphReader`; POST `bulkPreview` / `bulkDraftRevalidate` / `bulkDraftReset` / `bulkIdentityResolve` / `bulkApplyPrepare` are separate CSRF actions. No DataHandler. Module `workspaces: live`.
- `BulkApplyPlanBuilder` / `ApplyPlan`: pure value plan from `identityResolved` only. Fingerprint is confirmation continuity for future EDITOR-2B4. No restaurant-record writes.
- `MenuGraphReader`: default language (`sys_language_uid=0`), selected pid only, deleted excluded, hidden/scheduled included. Item reads are pid-bounded.
- `BackendAccessGuard`: page show + `tables_select` for module open; identity resolution / Prepare apply add future-Apply preflight (`CONTENT_EDIT`, `tables_modify`, live workspace). Fail closed. DataHandler remains final write authority later.
- Fluid table: read projection. `#` is transient view state. Identity badges/summary and ApplyPlan are stale after draft cell edits until Resolve / Prepare again.

DOMAIN-1A is not redesigned. One pasted commercial row is **not** automatically one Item. Item stays reusable identity. Placement stays the occurrence in a Category. PriceOption stays the commercial variant/price on that Placement.

---

## A. State machine

```
raw TSV
  → parse (BulkMenuParser; no DB)
  → normalized draft (in-request only; not TYPO3 records)
  → editable preview (EDITOR-2B1; still not persisted)
  → server revalidation → DraftValid XOR blocked (EDITOR-2B1; no identity)
  → validation + identity resolution (pid-scoped QueryBuilder reads; EDITOR-2B2+)
  → apply-ready XOR blocked
  → explicit user confirmation
  → TYPO3 DataHandler command
  → result / errors / reload of the read graph
```

| State | Meaning | Writes? |
|---|---|---|
| `Empty` | No paste | no |
| `ParsedInvalid` | Global parse error or any row parse-invalid | no |
| `Draft` | All rows parse-valid; identities not resolved | no |
| `DraftValid` | Cell + run validation passed; identities not resolved (EDITOR-2B1) | no |
| `Blocked` | Parse-valid but not apply-ready (ambiguity, access, stale, language, …) | no |
| `ApplyReady` | All blockers cleared; confirmation allowed | no |
| `Confirmed` | User posted Apply with valid CSRF | no until DataHandler starts |
| `Applying` | DataHandler `process_datamap()` running | yes |
| `Applied` | DataHandler finished without `errorLog` | already written |
| `PartialFailure` | DataHandler finished with some records created and `errorLog` non-empty | maybe partial |
| `Failed` | Apply refused before DataHandler, or DataHandler failed with nothing usable | no or unknown; reload |

Transitions that **must not** write: parse, preview render, cell edit of draft values, search, sort, filter, reset original order, numbering, `bulkDraftRevalidate`, `bulkIdentityResolve`, `bulkApplyPrepare`.

The draft remains separate from persisted TYPO3 records until `Applying`.

---

## B. Draft row model

Minimum editable fields (EDITOR-2B1 implements these on the bulk draft table only; the saved Menu table stays read-only):

| Draft field | Source TSV | Normalized form |
|---|---|---|
| Category | column 1 | trim; non-empty |
| Item | column 2 | trim; non-empty |
| Variant | column 3 | `cleanDisplayTitle`; empty allowed; duplicate labels compared by matchKey within a run |
| Price | column 4 | `DecimalMinorUnitParser` → integer minor units |

Per draft row, keep all of:

- **draft value** — last user-visible strings (Category, Item, Variant, Price text)
- **normalized value** — trimmed strings + `amountMinor: int|null`
- **validation state** — parse/identity error codes (bounded list)
- **source line** — original TSV line number (unchanged by sort/filter)
- **relation/identity resolution** — see section C

EDITOR-2B1 runtime record is `BulkDraftRow` (draft-local `draftKey`, `originalOrder`, `sourceLine`, editable strings, `amountMinor`, parse/run errors). Identity `Resolution` remains documentation-only until EDITOR-2B2.

Suggested conceptual record including later identity fields:

```
DraftRow
  sourceLine: int
  draftCategory, draftItem, draftVariant, draftPrice: string
  normalizedCategory, normalizedItem, normalizedVariant: string
  amountMinor: int|null
  parseErrors: list<string>
  categoryResolution: Resolution
  itemResolution: Resolution
  placementGroupKey: string   # draft-local grouping, not a uid
```

```
Resolution
  status: create | reuse | ambiguous | inaccessible | unresolved
  uid: int|null              # local only; never an integration id
  publicUuid: string|null    # canonical ARP identity when reuse
  tstamp: int|null           # last-seen persisted tstamp when reuse
  pid: int|null
```

Price round-trip before Apply:

1. Edited display text is treated as `draftPrice`.
2. Re-run `DecimalMinorUnitParser` (integer arithmetic only).
3. Reject commas, currency symbols, negatives, more than 2 fractional digits, empty.
4. Store `amountMinor`. Never persist formatted strings. Never use floats.
5. Apply writes PriceOption `amount` as that integer. Empty Variant → PriceOption `label` empty. Non-empty Variant → `label` = normalized variant.

One TSV row maps to **one PriceOption** on **one Placement**. It does not map to one Item. Item reuse is resolved separately (section C).

### Placement grouping (draft-local)

DOMAIN-1A allows both (a) one Placement with several PriceOptions and (b) multiple Placements for the same Category+Item.

First Apply grouping, in paste order:

- A **run** is a maximal consecutive sequence of parse-valid rows sharing the same **logical** Category **and** Item. Logical equality uses `RestaurantTitleNormalizer::matchKey` (Unicode whitespace-normalized + Unicode case-folded), not case-preserving display strings. So `Drinks|Tea` and `drinks|tea` in consecutive `originalOrder` form **one** run. Display capitalization is preserved on each row; the first cleaned display spelling remains the CREATE proposal for identity.
- If **every** row in the run has an empty Variant: each row becomes its **own** Placement with one empty-label PriceOption (duplicate simple offerings stay legal and independent).
- If **every** row in the run has a non-empty Variant: the run becomes **one** Placement with one PriceOption per row, labels and amounts in row order. Variant **display** uses `cleanDisplayTitle` (whitespace collapsed; capitalization preserved). Variant **duplicate** comparison within the run uses the same match-key contract (`Small` / `small` / ` SMALL` are duplicates → `duplicateVariant` blocking). Punctuation still distinguishes labels (`Small` ≠ `Small!`). No fuzzy matching.
- If a run **mixes** empty and non-empty Variants: **blocking** (`mixedVariantRun`), including when Category/Item differ only by case/spacing.

Non-consecutive repeats of the same logical Category+Item start a new Placement. No `UNIQUE(category,item)`. `BulkDraftValidator` and `BulkIdentityResolver` share `BulkDraftRunGrouping` so validation and future Placement counts cannot diverge.

---

## C. Identity resolution rules

Title text is **not** business identity. `public_uuid` is. TYPO3 `uid` is a local handle for DataHandler only. `Item.sku` and `Placement.menu_code` are reserved and **must not** be used or invented here.

Resolution reads: QueryBuilder, same restrictions as `MenuGraphReader` (deleted excluded; hidden/scheduled included; `sys_language_uid=0`; selected pid only). Workspace live.

Never merge two existing Items because titles match.

### Item

Match set: default-language Items on the **selected pid**. Cross-pid Items are out of scope.

**Identity comparison** uses a restaurant title **match key**, not raw display equality:

1. `cleanDisplayTitle` — Unicode-safe trim; collapse consecutive whitespace / Unicode separators (`\p{Z}` and ASCII whitespace) to one ordinary space; preserve capitalization, punctuation, accents, and script.
2. `matchKey` — `cleanDisplayTitle` then Unicode case fold (`mb_convert_case(..., MB_CASE_FOLD, 'UTF-8')`).

Display titles remain case-preserving. Editors must not accidentally create duplicate logical Items from capitalization or spacing differences (`Atlantic salmon` / `Atlantic Salmon` / `  Atlantic   salmon  ` share one key). Punctuation and wording still distinguish titles (`Salmon` ≠ `Salmon!` ≠ `Salmon Roll`).

No transliteration, accent stripping, fuzzy spelling, or Levenshtein merge. Pre-existing same-key duplicates remain **AMBIGUOUS** (fail closed); this task does not merge DB rows.

Pid-wide, not menu-wide (Item is reusable).

| Situation | Status | Apply |
|---|---|---|
| No matching Item by matchKey | `create` | DataHandler creates a new default-language Item. Core TCA uuid assigns `public_uuid`. Proposed title = first `cleanDisplayTitle` in draft originalOrder. |
| Exactly one matching Item by matchKey | `reuse` | New Placement points at that Item. Do not rewrite Item title/uuid. Capture `uid`, `public_uuid`, `tstamp`, and expose the persisted title as the canonical REUSE reference. |
| Multiple matching Items by matchKey | `ambiguous` | **Fail closed.** Do not pick, merge, or create a third. User must disambiguate later (out of first UI) or rename in List/FormEngine. |
| Same Item already used in another Menu on this pid | `reuse` (still one Item) | Allowed. New Placement in the target Menu. |
| Same Item with multiple variants in one grouped run | `reuse` or `create` once | One Item; one Placement; several PriceOptions. |
| Duplicate Placement (same Category+Item already on the Menu) | n/a | **Create another Placement.** Do not update the existing Placement’s PriceOptions. |
| Match exists but page/table modify denied | `inaccessible` | Block. |
| Reused Item vanished, moved pid, or `public_uuid`/`tstamp` mismatch at Apply | stale | Block; reload. |

Consequences: two catalog dishes that share a match key (for example both stored as differently cased “Tea”) cannot be distinguished from TSV and resolve as `ambiguous`. Hidden “Tea” counts as a match (avoids creating a duplicate visible Tea). Changing an Item title after preview invalidates reuse via `tstamp` (section H).

### Category

Match set: default-language Categories on the selected pid whose `menu` is the **target Menu**, compared with the same `matchKey` contract as Item (whitespace-normalized + Unicode case-folded). Display titles remain case-preserving. Case / spacing differences must not create duplicate Categories in the target Menu.

| Situation | Status | Apply |
|---|---|---|
| No matching Category in the target Menu | `create` | One Category on that Menu; Core uuid. All draft rows sharing the Category matchKey share the same create-once draft identity. Proposed title = first `cleanDisplayTitle` in draft originalOrder. |
| Exactly one matching Category in the target Menu | `reuse` | Capture uid, public_uuid, tstamp; expose persisted title. |
| Repeated Category text / case variants in the draft | same resolution | Create at most one Category per distinct matchKey in this Apply. |
| Multiple Categories with the same matchKey in the target Menu | `ambiguous` | **Fail closed.** |
| Same title on a Category of a **different** Menu | not a match | Do not reuse across menus. Categories belong to one Menu. |
| Duplicate Category+Item Placement already stored | n/a | New Placement appended. |

No title uniqueness. No `UNIQUE(category,item)`.

### Menu (target)

First Apply does not create a Menu (see D). Target Menu must already exist on the selected pid. Submitted `menu` uid is **not** authority: reload the Menu by uid **and** pid; reject if missing, wrong pid, non-default language, or deleted.

---

## D. Chosen first Apply semantic

Evaluated:

| Model | Duplicates | Category reuse | Item reuse | Duplicate Placement | PriceOption | Rollback | Expectation | Demo later |
|---|---|---|---|---|---|---|---|---|
| **A. Append to selected existing Menu** | Possible if user Applies the same paste twice | Yes, unique title in that Menu | Yes, unique title on pid | Always append a new Placement | Always create | Harder: new rows mixed with existing | Matches current editor (user is looking at a Menu) | Demo can create a Menu then append |
| **B. Always create a new Menu** | Isolated to the new Menu | Only within the new Menu | Still pid-wide Item reuse | Isolated | Create | Easier to delete the whole Menu | Surprising if user intended to fill Lunch | Good isolation; extra Menu clutter |
| **C. Replace current Menu** | Destructive | — | — | — | — | Worse | Dangerous | Unsafe |

**Choice for the first write implementation: A — append to the currently selected Menu on the selected pid.**

Why:

- The module already has a selected Menu tab and a storage pid. User expectation is “add these rows to this menu.”
- Non-destructive: existing Categories, Placements, and PriceOptions are not updated, hidden, or deleted.
- DOMAIN-1A already allows multiple Placements for the same Category+Item, so append does not require uniqueness.
- Item reuse stays pid-scoped and fail-closed on ambiguity.
- Demo/load later can reuse the same command: create (or select) a dedicated Menu, then append. Cleanup must **not** be title-based (section L).
- Replace-current-menu is forbidden in this phase. No destructive sync.

If there is no selected Menu (`noMenus`), Apply is **blocked** until a Menu exists (List/FormEngine or a later bounded “create Menu then append” task). First write does not silently create a Menu in order to stay strictly append-only.

---

## E. DataHandler write architecture

Normal restaurant-record writes **must** use TYPO3 DataHandler (`process_datamap()` / `process_cmdmap()` as appropriate).

Forbidden for restaurant records:

- raw SQL INSERT/UPDATE/DELETE
- QueryBuilder writes (except the existing copy-translation `public_uuid` alignment hook)
- Doctrine/Extbase persistence used to bypass DataHandler
- a second persistence path beside DataHandler

QueryBuilder remains **read-only** for editor resolution and graph reload.

Suggested application boundary (documentation; do not implement in this task):

```
RestaurantEditorController
  → (future) EditorApplyCommand
       → DataHandler::start(datamap, [])
       → DataHandler::process_datamap()
  → MenuGraphReader reload
```

`EditorApplyCommand` would accept an apply-ready draft + pid + menu uid + CSRF-validated request + last-seen stamps. It would build a DataHandler datamap using `NEW…` tokens and IRRE parent pointers (`menu.categories`, `category.placements`, `placement.price_options`, `placement.item`).

DataHandler remains responsible for:

- TCA validation (`required`, types, ranges)
- backend permissions (`tables_modify`, exclude fields, page access)
- `public_uuid` generation via TCA `type=uuid` on insert — **do not** set `public_uuid` in the datamap for new records
- localization fields (`sys_language_uid=0` for this phase)
- history/logging
- enable fields defaults (`hidden`, start/end)
- workspace handling (this module is live-only)
- IRRE relation and `foreign_sortby` sorting

Do not mint UUIDs in PHP for new rows. Do not bypass the copy-translation UUID hook; first Apply creates default-language rows only, so that hook should not need to run.

---

## F. Permission / security contract

Fail closed. Do not weaken the current read boundary.

Required before Apply (and re-checked at Apply, not only at Preview):

- `id` / pid is a positive integer; page `readPage` (show) succeeds
- backend user may **modify** content on that page (page content/edit permission, not only show)
- `tables_modify` for Menu, Category, Item, Placement, PriceOption (all five)
- field exclude rights: if the user cannot write required fields (`title`, `amount`, relations), block rather than insert incomplete rows
- CSRF / `FormProtection` with a dedicated form name/action distinct from `bulkPreview` (for example action `bulkApply`)
- target Menu uid belongs to **this pid**, default language, not deleted; ignore a forged menu uid that points at another page
- module workspace is live; `$BE_USER->workspace === 0`; reject non-live
- hidden/scheduled records may be **reused** as identity matches but are not altered in this phase
- no public/frontend endpoint

Submitted uids are claims to verify against pid + ACL + `public_uuid` + `tstamp`, never authority for cross-pid access.

---

## G. Validation / apply-ready rules

Parse success is **not** apply-ready.

Apply control is allowed only when **all** hold:

- CSRF token valid
- pid and Menu target verified
- live workspace
- modify permissions on page and all five tables
- every draft row parse-valid (Category, Item, Price; Variant optional)
- no mixed empty/non-empty Variant run (section B)
- every Category resolution `create` or `reuse` (not `ambiguous` / `inaccessible` / `unresolved`)
- every Item resolution `create` or `reuse`
- reused records still present with matching pid, `public_uuid`, and `tstamp`
- default-language-only (`sys_language_uid=0`); any non-zero language state in the command is unsupported and blocking
- input still within existing parser byte/row limits

Blocking examples: parser errors, invalid/negative/over-precise price, unresolved Item ambiguity, unresolved Category ambiguity, inaccessible target records, stale existing record, missing Menu, wrong pid, non-live workspace.

All of the above that are knowable before DataHandler must be visible in the preview/blocked UI before confirmation.

---

## H. Optimistic concurrency

For **existing** records that will be reused (Menu target, reused Category, reused Item):

- At resolution time, store `uid + public_uuid + tstamp + pid`.
- At Apply, reload those rows. If missing, deleted, pid changed, `public_uuid` differs, or `tstamp` differs: **reject the whole Apply** (first phase: no partial apply of the rest). Reload the graph and show a conflict: the stored record changed since preview.

Do not overwrite stale rows. First Apply does not update existing Item/Category fields; the stamp check still applies because reuse targets must be the same records the user saw.

For **newly created** draft-only records: no persisted `tstamp` yet. Concurrency starts after insert. Two overlapping Applies can both `create` Items with the same title (no UNIQUE). That is accepted DOMAIN behavior; it is not silent overwrite of an existing row. A later Apply may then see `ambiguous` titles.

No OT / field merging.

---

## I. Confirmation summary model

Preview and draft edits must not write. Only an explicit Apply/Import POST writes.

Before that POST, show a summary derived from the apply-ready draft, for example:

```
Create:
  N Categories
  N Items
  N Placements
  N PriceOptions

Reuse:
  N Categories
  N Items

Target: Menu <title> on page <pid>
Language: default
```

N is counted after grouping (section B) and create-once Category/Item identity. UI is not implemented in this task.

---

## J. Partial-failure / transaction analysis

**Do not claim atomicity.**

What can be validated before DataHandler: everything in section G, including identity and stamps.

What DataHandler can still fail on: TCA, permissions Core re-checks, DB constraints, IRRE mapping, hooks, unexpected empty required fields, runtime exceptions.

TYPO3 13.4 / 14.3 DataHandler `process_datamap()` is **not** documented in this repository as wrapping the entire datamap in one database transaction. In typical Core behavior, inserts commit as they succeed; `errorLog` may be populated after some `NEW` rows already exist. This extension’s only DataHandler-adjacent write today is the copy-translation UUID hook, which is not a full-datamap transaction.

**First implementation rule:** do not wrap `process_datamap()` in `Connection::transactional()` unless a **dedicated runtime gate** on TYPO3 13.4 LTS and 14.3 LTS proves that Core, IRRE, uuid TCA, and hooks remain correct inside an outer transaction. Until that gate: treat Apply as **non-atomic**.

On `errorLog` after processing:

- Reload the pid graph.
- Report DataHandler messages plus which intended creates cannot be confirmed.
- Do not invent automatic rollback/delete of rows that did persist (that would be a destructive second command).
- Do not mark the draft apply-ready until the user previews/resolves again.

**Required runtime gate:** confirm on both LTS lines whether an outer DB transaction around this bounded datamap is safe. Until then, documentation must not promise rollback.

---

## K. Localization boundary

First write: **default language only** (`sys_language_uid = 0`).

Do not create, update, or localize connected translations. Do not copy translation trees. Existing DOMAIN-1A localization and the copy-UUID hook stay unchanged.

Future localization can attach overlays that **share** the default-language `public_uuid`. Apply would still create only the default row; editors would use Core Translate / localization as today. Public UUID semantics do not change: overlays do not mint a second UUID.

---

## L. Future demo-data compatibility

Not implemented here.

A later “Load demo menu” should call the same validate → apply-ready → confirm → `EditorApplyCommand` path (likely creating a Menu first, then append, or a later bounded create-menu+append command).

**Remove demo data must not match titles** such as “Lentil Soup”. Title is not identity.

Safe cleanup needs explicit provenance/ownership metadata (for example a batch `public_uuid` or demo flag stored on created records). **Exact schema is deferred** to a separate bounded task. Do not add those fields in EDITOR-2B0.

Future demo content requirements (also deferred): culturally neutral where practical; no pork/alcohol dependency; usable across common dietary contexts; obviously demonstration content; removable via provenance, not titles.

---

## M. Future ARP.top / POS adapter boundary

Not implemented here. Do not add `pos_id`, `square_id`, `toast_id`, `clover_id`, or `arp_top_id` to core records.

```
ApplyCommand / DataHandler
  → persisted records with public_uuid
       → (future) ExternalReference mapping
            provider + entity_type + entity_public_uuid + external_id
                 → ARP.top or POS adapter
```

Adapters sit **after** TYPO3 persistence. They consume `public_uuid`, never `uid`. `EditorApplyCommand` must not call ARP.top or POS.

---

## N. Explicit deferred items

- DataHandler execution and Apply button behavior
- Inline/JS cell editors and AJAX mutation
- Creating a Menu when none exists
- Replace/sync/delete of existing menu rows
- Ambiguity picker UI (fail closed until then)
- `Item.sku` TCA/SQL/import column
- `Placement.menu_code` TCA/SQL/import column
- Demo loader/remover and provenance schema
- Outer DB transaction around DataHandler (runtime gate)
- Connected-translation import
- Cross-pid Item reuse
- Workspace (non-live) writes
- ARP.top / POS adapters and provider ID fields
- Frontend / Site Settings / ordering / Mosaic
