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

External integrations should eventually use stable public identifiers rather than TYPO3 `uid` values.

TYPO3 uids are local to one CMS instance. Public restaurant, menu, category, item, placement, and price-option identifiers are UUID values defined in the domain contract. They identify logical localized entities, not physical translation rows. They must stay stable across environments and external systems.

A default-language business copy receives a new logical UUID. Connected translations of that copy share the new UUID. `t3_origuid` may still record TYPO3 copy provenance; it is not the public business identity.
