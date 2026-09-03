# Architecture

ARP Restaurant is a TYPO3 extension. This document records the ownership and integration boundaries for the public package `anatolkin/arp-restaurant` (extension key `arp_restaurant`).

Canonical supported TYPO3 lines are **TYPO3 13.4 LTS** and **TYPO3 14.3 LTS**.

## What TYPO3 owns

TYPO3 owns website and CMS infrastructure:

- pages
- page slugs
- site languages
- CMS content
- backend users and groups
- frontend routing
- core SEO infrastructure

Public page paths are configured in TYPO3. Restaurant code must not assume literal URLs.

## What ARP Restaurant owns

ARP Restaurant will own restaurant-specific data and presentation:

- restaurant domain data
- menus
- categories
- items
- prices
- opening hours
- restaurant presentation
- simple direct ordering

This bootstrap does not implement that domain yet. The 0.1 contract is defined in [DOMAIN_MODEL.md](DOMAIN_MODEL.md): Menu → Category → Placement → Item. Commercial price belongs to Placement via PriceOption. Restaurant identity, structured address, currency, timezone, and semantic page roles live in TYPO3 Site Settings, not in a Restaurant table.

## Mosaic Gallery

Mosaic Gallery is a **planned optional** integration.

It is not implemented in this bootstrap and is not declared in Composer. Restaurant sites may use Mosaic later for gallery presentation, but the extension must remain installable and useful without it.

## ARP.top

ARP.top operational integration is **future work**.

It is not implemented in this bootstrap. Free/local ordering must eventually remain usable independently of ARP.top. ARP.top is an optional operational connector, not a prerequisite for local restaurant ordering.

## Routing invariant

Never hardcode public page paths.

Do not assume:

- `/menu`
- `/gallery`
- `/contact`
- `/order`
- `/item`

TYPO3 page configuration owns public page paths. Future Restaurant code must operate with semantic roles and page UIDs, then resolve URLs through TYPO3 routing.

## Public identifiers

Restaurant identity is layered. See [DOMAIN_MODEL.md](DOMAIN_MODEL.md) identity layers for the canonical contract.

- TYPO3 `uid` is physical/local storage identity only. It never crosses the integration boundary (ARP.top, public API, POS, sync, import/export business identity).
- `public_uuid` is the canonical stable logical identity for restaurant/site, Menu, Category, Item, Placement, and PriceOption. Connected translations share it. ARP.top and future integrations must use `public_uuid`, never `uid`.
- `Item.sku` is a reserved optional catalog identifier on Item. It is not implemented and must not be added by EDITOR-2B0.
- `Placement.menu_code` is a reserved per-appearance code on Placement (for example Lunch `L12` vs Dinner `D08` for the same Item). It is not SKU.
- Provider-specific IDs (`square_id`, `toast_id`, `clover_id`, `pos_id`) must not be added to core domain records. Future POS mapping is a provider-neutral external-reference table, persistence deferred.

A default-language business copy receives a new logical UUID. Connected translations of that copy share the new UUID. `t3_origuid` may still record TYPO3 copy provenance; it is not the public business identity.

## Compact backend editor

The compact restaurant editor is an **additional** backend UI over the same DOMAIN-1A records. It does not introduce a second restaurant model or database.

- Module: `web_arp_restaurant_editor` (parent `web`, page tree).
- The selected page-tree node is the storage pid/page. It does not have to be a sysfolder.
- Multiple Placement records for the same Category+Item are legal and are shown as separate rows.
- Native List, FormEngine, and IRRE remain valid editing surfaces.
- EDITOR-1 is read-only. Price display uses an isolated minor-unit formatter; currency and scale authority stay deferred to Site Settings.
- Bulk paste is parse, validate, and preview only. It does not write records, does not merge against stored TYPO3 data, and does not change DOMAIN-1A schema or TCA.
- The selected pid is the read boundary for Menu, Category, Placement, PriceOption, **and Item**. A Placement that points at an Item on another page does not reveal that Item's title. Cross-pid Item reuse needs explicit source-page ACL and is not implemented.

## Editor milestone status

### EDITOR-2A.1 — PASS / TYPO3 14 LIVE ACCEPTED

Runtime recorded for this gate:

- TYPO3 14.3.6
- accepted extension SHA: `0afa5ca60104cc24166a3bd60fdde8b4d452758f`
- visual acceptance: PASS
- unified saved/preview grid visible in backend
- vertical + horizontal cell rules visible
- invalid preview values highlighted at cell level
- DB unchanged
- no schema/TCA/DataHandler changes
- production not touched

TYPO3 13 remains on the previously accepted editor line at the time of this runtime gate and is not part of this runtime acceptance.

### EDITOR-2A.2 — REPO IMPLEMENTED / RUNTIME ACCEPTANCE PENDING

The compact editor now projects DOMAIN-1A as one flat saved-menu table (`# | Category | Item | Variant | Price | Status`) and one flat bulk-preview table (`# | Category | Item | Variant | Price | Line | Status`). That projection does not change Menu → Category → Placement → Item / PriceOption ownership.

`#` is a transient visible-row index. It is not an Item UUID, TYPO3 uid, `Item.sku`, `Placement.menu_code`, or ordering identity. Those reserved codes are not implemented here.

Client-side search and sorting do not query TYPO3, do not write records, and do not change DataHandler/TCA sorting. Original domain render order is the default and can be restored. Inline editing and bulk import/write remain deferred. Bulk paste is still preview-only.

This milestone is repository implementation only. Cursor has no VPS access; TYPO3 browser runtime acceptance for search, sort, sticky headers, and visible row numbering is pending.

### EDITOR-2B0 — DESIGN ONLY / NO WRITES

The first write-capable import is specified in [EDITOR_WRITE_CONTRACT.md](EDITOR_WRITE_CONTRACT.md). Current runtime stays read-only. Do not implement DataHandler Apply, SKU, or `menu_code` from this status note.

### EDITOR-2B1 — REPO IMPLEMENTED / NO WRITES

Bulk preview rows are an editable in-request draft. `BulkDraftValidator` is the server authority: it trims Category/Item/Variant, re-parses Price via `DecimalMinorUnitParser`, restores canonical `originalOrder`, and blocks mixed empty/named Variant runs. POST `bulkDraftRevalidate` is a dedicated CSRF action. Successful validation is `DraftValid`, not `ApplyReady`.

Not in this milestone: identity resolution, Apply, DataHandler, QueryBuilder writes, `Item.sku`, `Placement.menu_code`. Search/sort remain view-only and must not change semantic draft order. Hidden search rows still POST. Preview from the TSV box rebuilds/resets the draft; edited cells do not rewrite the textarea.

### EDITOR-2B1.1 — REPO IMPLEMENTED / NO WRITES

Draft `input` events mark dirty and must not call DOM row reparenting (`sortRows` / `appendChild`). Search, sort, Restore order, and blur/`change` may refresh the view. Restore order keeps edited values. Reset draft is a dedicated CSRF POST that re-parses `bulkSource`. Placement-run warnings (`singleNamedVariant`) are distinct from blocking errors (`mixedVariantRun`, `duplicateVariant`). No global Variant table.
