Automated tests currently cover copy/translation UUID alignment, the compact editor read/view-model, and the bulk-paste parser (preview only, no TYPO3 runtime).

From the extension root:

```bash
php Tests/run.php
```

Full TYPO3 backend-module or DataHandler functional tests require a TYPO3 test installation and are not part of this repository yet.
