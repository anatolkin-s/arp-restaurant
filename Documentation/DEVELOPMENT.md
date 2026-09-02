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

## Local installation

This repository is an extension, not a full TYPO3 project. To activate it, add it to a TYPO3 13.4 LTS or 14.3 LTS Composer project as a path or VCS repository, require `anatolkin/arp-restaurant`, run `php vendor/bin/typo3 extension:setup`, and add the **ARP Restaurant** Site Set to the site configuration.

## Validation in this repository

From the extension root:

```bash
composer validate --strict
```

Also check JSON/YAML/XML/PHP syntax of the shipped metadata files. TYPO3 runtime activation cannot be verified here until a TYPO3 test installation exists.

## Out of scope until later milestones

Do not add in bootstrap follow-up work unless a later task owns it:

- domain models and database tables
- menu rendering
- ordering
- backend modules
- Mosaic integration code
- ARP.top integration
- hardcoded public routes or page paths
