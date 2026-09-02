# ARP Restaurant

ARP Restaurant is a TYPO3 extension for restaurant domain data, presentation, and simple direct ordering.

This repository currently contains only the canonical extension foundation. Menus, categories, items, prices, opening hours, ordering, Mosaic Gallery integration, and ARP.top connectivity are not implemented yet.

## Requirements

- TYPO3 CMS 13.4 LTS or TYPO3 14.3 LTS
- PHP 8.2 through 8.5

## Status

Pre-release bootstrap toward **0.1.0**. There is no Git tag and no public release yet.

The Composer package name is `anatolkin/arp-restaurant`. The TYPO3 extension key is `arp_restaurant`.

## Installation

This package is not released on Packagist yet. In a Composer-based TYPO3 project, add a path or VCS repository and require the package, then run extension setup:

```bash
composer require anatolkin/arp-restaurant:dev-main
php vendor/bin/typo3 extension:setup
```

In the TYPO3 backend:

1. Open **Site Management → Sites**.
2. Edit the site configuration.
3. Under **Sets for this Site**, add **ARP Restaurant**.
4. Save the site configuration.

TYPO3 page configuration owns public page paths. This extension does not hardcode URLs such as `/menu`, `/gallery`, `/contact`, `/order`, or `/item`.

## Optional integrations

- **Mosaic Gallery** is a planned optional integration. It is not implemented yet and is not a Composer dependency.
- **ARP.top** operational integration is future work and is not included in this bootstrap.

Local/free restaurant ordering is intended to remain usable later without ARP.top.

## Documentation

- [Architecture](Documentation/ARCHITECTURE.md)
- [Domain model](Documentation/DOMAIN_MODEL.md)
- [Roadmap](Documentation/ROADMAP.md)
- [Development](Documentation/DEVELOPMENT.md)

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
