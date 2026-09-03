# Development

## Package identity

- Composer package: `anatolkin/arp-restaurant`
- TYPO3 extension key: `arp_restaurant`
- PHP namespace: `Anatolkin\ArpRestaurant`
- Site Set: `anatolkin/arp-restaurant`

Working version: `0.1.0-dev`. This is unreleased bootstrap work. Do not create Git tags or GitHub releases until an explicit release task says so.

## Compatibility

- TYPO3 13.4 LTS and TYPO3 14.3 LTS (`typo3/cms-core: ^13.4 || ^14.3`)
- PHP 8.2 through 8.5

`ext_emconf.php` is kept for TYPO3 13 Classic/TER tooling. Canonical TYPO3 14 Classic-mode metadata lives in `composer.json` (`extra.typo3/cms.version` and `Package.providesPackages`).

The 0.1 domain contract is [DOMAIN_MODEL.md](DOMAIN_MODEL.md). Do not add TCA, SQL, or PHP models until a later implementation task owns that work.

## Local installation

This repository is an extension, not a full TYPO3 project. To activate it, add it to a TYPO3 13.4 LTS or 14.3 LTS Composer project as a path or VCS repository, require `anatolkin/arp-restaurant`, run `php vendor/bin/typo3 extension:setup`, and add the **ARP Restaurant** Site Set to the site configuration.

## Validation in this repository

From the extension root:

```bash
composer validate --strict
```

Also check JSON/YAML/XML/PHP syntax of the shipped metadata files. TYPO3 runtime activation cannot be verified here until a TYPO3 test installation exists.

Copy/translation UUID alignment is covered by `php Tests/run.php`. That runner also exercises compact-editor read-model mapping, the bulk-paste parser, and `BulkDraftValidator`. It is not a full TYPO3 functional suite.

## Compact backend editor

`web_arp_restaurant_editor` is a Core (non-Extbase) backend module. It reads Menu → Category → Placement → Item / PriceOption for the selected page-tree pid and renders a compact table. Native List/FormEngine/IRRE remain available.

The selected context is a storage pid/page, not necessarily a sysfolder. That pid is the read boundary for every restaurant table, including Item. Duplicate Placements for the same Category+Item are rendered as separate rows in one flat table. Price formatting is display-only via `MinorUnitMoneyFormatter`; Site Settings will own currency later.

Bulk paste accepts TSV (Category, Item, Variant, Price), validates it in `BulkMenuParser`, and renders an editable in-request draft. `BulkDraftValidator` revalidates posted cell strings on `bulkDraftRevalidate`. `bulkDraftReset` rebuilds that draft from the last Preview TSV. Restore order is view-only. After a cell edit, the UI shows that server validation is stale until Revalidate. That path is still non-writing: no DataHandler, no identity lookup against stored Item/Category rows, and no Apply.

EDITOR-2A.1 is **PASS / TYPO3 14 LIVE ACCEPTED** at extension SHA `0afa5ca60104cc24166a3bd60fdde8b4d452758f` on TYPO3 14.3.6. That gate covers the unified saved/preview grid, cell rules, and cell-level invalid preview highlighting. TYPO3 13 is not part of that runtime acceptance. Production was not touched.

EDITOR-2A.2 is **REPO IMPLEMENTED / RUNTIME ACCEPTANCE PENDING**. The saved Menu table remains a flat read projection. Search/sort/row numbers are client-side view state only. Sticky headers, search, and sort still need TYPO3 browser runtime acceptance. `Item.sku` and `Placement.menu_code` are not in this milestone.

EDITOR-2B1.1 is **PASS / TYPO3 13 + TYPO3 14 LIVE ACCEPTED** at extension SHA `bd53dbe9acd08ac1df96113747027f0ed59502ae` on TYPO3 13.4.34 and TYPO3 14.3.6. That gate covers the editable temporary draft, Restore order vs Reset draft, Variant-run warnings, and Fluid `warnings` / `globalError` accessor fixes. Identity resolution and DataHandler Apply remain later work: [EDITOR_WRITE_CONTRACT.md](EDITOR_WRITE_CONTRACT.md).

## Copy UUID lifecycle

Deep copy of connected translations is corrected after DataHandler `process_cmdmap()` finishes.

Hook: `$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processCmdmapClass']` implementing `processCmdmap_afterFinish()`. That method exists on TYPO3 13.4 and 14.3 and runs after `remapListedDBRecords()` / `processRemapStack()`, so copied `l10n_parent` values are already remapped.

Copied destination UIDs come from `DataHandler::$copyMappingArray_merged`. TYPO3 Explained documents this public property as the source→copy UID map. DataHandler marks it `@internal` and neither 13.4 nor 14.3 expose a public getter, so access is isolated in `CopiedTranslationUuidHook`. Do not read `$copyMappingArray`; Core clears it between copy operations.

The hook writes only `public_uuid` via QueryBuilder. It does not start a nested DataHandler, and it does not change localization, Item reuse, or structural fields.

## Out of scope until later milestones

Do not add in bootstrap follow-up work unless a later task owns it:

- domain models and database tables
- menu rendering
- ordering
- Mosaic integration code
- ARP.top integration
- hardcoded public routes or page paths
