Automated tests currently cover copy/translation UUID alignment, the compact editor read/view-model, the bulk-paste parser, and server-side bulk draft revalidation (no TYPO3 runtime, no identity lookup, no writes).

Client-side search, sorting, sticky headers, and visible row numbering have no JS test harness in this repository. Those behaviors require TYPO3 backend browser runtime acceptance and are not claimed here.


From the extension root:

```bash
php Tests/run.php
```

Full TYPO3 backend-module or DataHandler functional tests require a TYPO3 test installation and are not part of this repository yet.
