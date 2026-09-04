Automated tests currently cover copy/translation UUID alignment, the compact editor read/view-model, the bulk-paste parser, server-side bulk draft revalidation, restaurant title normalization (`RestaurantTitleNormalizer`), and pure read-only identity resolution decisions (`BulkIdentityResolver`).

Identity tests do **not** require a TYPO3 database. `RestaurantIdentityReader` QueryBuilder SELECTs are reviewed statically (DeletedRestriction, WorkspaceRestriction, `sys_language_uid=0`, pid / Menu scope, SELECT-only; title matching in PHP via matchKey) and need TYPO3 13/14 runtime acceptance later.

Client-side search, sorting, sticky headers, and visible row numbering have no JS test harness in this repository. Those behaviors require TYPO3 backend browser runtime acceptance and are not claimed here.

From the extension root:

```bash
php Tests/run.php
```

Full TYPO3 backend-module or DataHandler functional tests require a TYPO3 test installation and are not part of this repository yet.
