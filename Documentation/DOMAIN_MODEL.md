# Domain model (0.1 contract)

This document is the canonical restaurant domain contract for milestone 0.1.

It defines ownership, relations, and TYPO3 record semantics. It does **not** implement TCA, SQL, PHP models, frontend rendering, ordering, Mosaic, or ARP.top.

Canonical supported TYPO3 lines remain **TYPO3 13.4 LTS** and **TYPO3 14.3 LTS**.

## Chosen model

**Menu → Category → Placement → Item**

Item is a reusable canonical dish. Category belongs to exactly one Menu. Placement is the canonical contextual appearance of an Item in one Category (and therefore in one Menu). Commercial price belongs to that Placement as PriceOption rows.

This is alternative **B** in the evaluation below.

## Alternative evaluation

### A. Menu → Category → Item

An Item belongs directly to exactly one Category.

- Editor UX is a simple tree: menu, then section, then dishes.
- The same dish on Lunch and Dinner must be copied as two Item records.
- Media, translations, and later OrderItem snapshots then split across duplicates.
- Context-specific price is easy (it lives on the Item), but the Item is no longer a stable product identity.

### B. Menu → Category → Placement → Item

An Item is stored once. Placement is the menu-context listing and the offer that carries price.

- The same Grilled Salmon can appear in Lunch/Mains and Dinner/Entrees without duplicate Item records.
- Lunch can offer it at $23 and Dinner at $29 because PriceOption belongs to Placement, not Item.
- Category remains a per-menu section, so Lunch Mains and Dinner Entrees can still differ.
- Editor UX can still look simple: a Category lists placements, and an editor either creates a new Item or reuses an existing one.
- Extra entity cost is one small join record, not an e-commerce catalog (no SKU inventory, no product types, no variant graphs).

**Choice: B.** It avoids forced Item duplication for real restaurant menus while remaining small enough for 0.1. Every on-menu appearance uses a Placement, including items that appear only once.

## A. Entity list

| Entity | Record or setting | 0.1 |
|---|---|---|
| Restaurant profile | TYPO3 Site Settings | yes |
| Semantic page roles | TYPO3 Site Settings | yes |
| Menu | domain record | yes |
| Category | domain record | yes |
| Item | domain record | yes |
| Placement | domain record | yes |
| PriceOption | domain record | yes |
| OpeningHourPeriod | domain record | yes |
| Special/holiday hours | extension point | no |
| Order / Cart | milestone 0.3 | no |
| Mosaic gallery | optional later integration | no |

There is no Restaurant domain record in 0.1. One TYPO3 site presents one restaurant.

## B. Responsibility of each entity

### Restaurant profile (Site Settings)

Site-wide identity and configuration that is not menu content:

- restaurant name
- phone
- email
- structured address:
  - street
  - locality/city
  - region/state
  - postal code
  - country code
- currency (ISO 4217)
- timezone (IANA)
- reservation URL
- public restaurant UUID
- semantic page role UIDs (menu, contact, and later order/gallery as those features exist)

Site Settings fields are not implemented in this contract task.

### Menu

A named, publishable menu: Breakfast, Lunch, Dinner, Drinks, Catering.

May have zero or more categories. May be hidden or scheduled. Does not own a public URL.

### Category

A section inside one Menu: Appetizers, Soups, Pizza, Entrees, Desserts.

Belongs to exactly one Menu. May have zero or more placements. Not a global taxonomy and not TYPO3 `sys_category`. Lunch Pizza and Dinner Pizza are two Category records if both menus need that section.

### Item

Canonical dish/product identity: Margherita Pizza, Caesar Salad, Grilled Salmon.

Owns editorial identity, description, and FAL media. Does not own commercial price. Does not belong to a Category. An Item with no Placement exists in the catalog but is not on any menu.

### Placement

The canonical contextual appearance of one Item in one Category.

Owns sort order in that section, visibility in that context, and commercial PriceOptions for that offer. This is the menu-facing occurrence later OrderItem snapshots should cite together with Item and PriceOption identity.

Placement is localization-aware as a **structural** TYPO3 record so connected localization and IRRE can keep:

```
default Menu → default Category → default Placement
translated Menu → translated Category → translated Placement
```

It has no independently translated editorial fields in 0.1. Visible dish names come from the Item overlay.

### PriceOption

A priced offer row owned by exactly one Placement.

- Simple dish: one PriceOption (empty/default label, amount $16).
- Structured variants: several PriceOptions, for example Small — $12 and Large — $18.

Not a general modifier/add-on system. Not owned by Item.

### OpeningHourPeriod

One weekly service window: weekday + open + close.

Several periods may exist for one weekday, for example Monday 11:00–14:30 and 17:00–22:00. A closed weekday has no periods. Restaurant-wide, not per menu. Schedule implementation data; not an externally addressed business entity in 0.1.

Special/holiday exceptions are a future extension point and are not designed as 0.1 records.

## C. Relations / cardinality

Domain/storage cardinalities allow drafts and unused catalog items. Parents are not required to already contain children.

- One site → one restaurant profile (Site Settings).
- Menu `0..*` Category.
- Category belongs to exactly one Menu.
- Category `0..*` Placement.
- Item `0..*` Placement.
- Placement belongs to exactly one Category.
- Placement belongs to exactly one Item.
- Placement `0..*` PriceOption.
- PriceOption belongs to exactly one Placement.
- OpeningHourPeriod has no parent domain record; it is restaurant-wide in the configured storage folder.

Placement never points at two menus. Menu membership is always through Category.

## D. Localization rules

Use normal TYPO3 localization and Site Languages. Do not add a custom translation system.

| Entity | Localizable | Translated fields | Language-independent / synchronized |
|---|---|---|---|
| Menu | yes | title, description | public UUID, hidden, sorting, starttime/endtime |
| Category | yes | title, description | public UUID, sorting, hidden. Physical menu relation may point to the translated Menu row; logical Menu identity stays synchronized. |
| Item | yes | title, description, media metadata overlays | public UUID, FAL files (TYPO3-native) |
| Placement | yes (structural) | none in 0.1 | public UUID; logical Item identity; sorting, visibility, scheduling, and commercial structure stay synchronized with the default-language Placement. Physical category/item relations may point to translated parent rows. |
| PriceOption | yes | label only (optional) | public UUID, amount. Placement relation and structural/commercial fields stay synchronized with the default-language PriceOption. Physical placement relation may point to the translated Placement row. |
| OpeningHourPeriod | yes | optional label | weekday, open, close, sorting |

Rules:

- Connected translation mode. Default-language record is the source of logical identity.
- TYPO3 translations are **separate physical rows** (`sys_language_uid` / `l10n_parent` / `l10n_source`).
- Connected translations represent the same logical entity and share its public UUID. A translation must not mint a second UUID.
- Public UUID identifies the logical localized entity, not a physical TYPO3 row. Do not assume `public_uuid` can have a global UNIQUE database constraint across all translated rows.
- Do not require physical `uid` equality across translations. Translated structural relations may point to translated parent rows.
- Placement is localization-aware so TYPO3 IRRE can maintain translated Menu → Category → Placement trees without custom lookup semantics.
- A localized Placement is a separate TYPO3 row but the same logical Placement (example: default uid 100 and German uid 145 share one public UUID).
- Logical Item identity on a Placement stays synchronized across translations.
- Sorting, visibility, scheduling, and commercial structure of a Placement stay synchronized with the default-language logical Placement.
- Exact TCA/localization implementation is deferred to DOMAIN-1A.
- External/API lookup must respect TYPO3 localization and default-language identity.
- Price amounts and currency are not translated. Currency lives in Site Settings.
- Opening-hour clock times are not translated.

## E. Public UUID rules

TYPO3 `uid` is local database identity only. External systems, including a future ARP.top connector, must not use `uid`.

For 0.1, public UUID version 4 strings (`char(36)`) are required on:

- restaurant/site identity (Site Settings)
- Menu
- Category
- Item
- Placement
- PriceOption

OpeningHourPeriod has **no** public UUID in 0.1. It is weekly schedule data, not an externally addressed business entity.

- UUID is assigned on first insert of the default-language record and is immutable.
- Localization overlays share the parent UUID. They are separate rows, so uniqueness is logical (default-language identity), not a global UNIQUE over every language row.
- Copies/duplicates receive a new UUID.

Copy semantics:

- A default-language business copy creates a new logical UUID.
- Connected translations of that copy share the new UUID.
- `t3_origuid` may retain TYPO3 copy provenance.
- `public_uuid` represents business identity, not copy provenance.

Orders and OrderItems are out of scope for 0.1. When ordering is designed later, an OrderItem snapshot should be able to reference/copy:

- Item public UUID
- Placement public UUID
- PriceOption public UUID
- item/title snapshot
- price-option label snapshot where applicable
- unit price snapshot
- currency snapshot

Those snapshots must not depend on live `uid` joins remaining valid.

## F. Pricing ownership

Currency is a Site Setting. PriceOption amounts are integer **minor units** (cents for USD/EUR) to avoid floating-point money.

Canonical relation: **Placement `1` → `0..*` PriceOption**. A PriceOption belongs to exactly one Placement. Item has no price field and no PriceOption children.

**Simple case** (Margherita Pizza — $16):

- The Placement has one PriceOption with that amount.

**Structured case** (Small — $12, Large — $18):

- The Placement has two PriceOptions, Small and Large.

**Context case** (same Item, different menu price):

```
Item: Grilled Salmon
  Placement: Lunch / Mains     -> PriceOption $23
  Placement: Dinner / Entrees  -> PriceOption $29
```

No Item-level price and no later price-override mechanism are required. 0.1 does not implement pricing UI or code.

## G. Site Settings vs records

### Site Settings

- restaurant name, phone, email
- structured address (street, locality/city, region/state, postal code, country code)
- currency, timezone
- reservation URL
- public restaurant UUID
- semantic page role UIDs
- later ordering switches/configuration

### Domain records

- menus, categories, items, placements
- price options (owned by placements)
- weekly OpeningHourPeriod rows
- FAL media attached to items

Do not duplicate restaurant name/phone/currency/address into Menu or Item records. Do not store public page paths in records or settings. Page roles store page UIDs; TYPO3 routing owns the URL.

## H. TYPO3 record semantics

Expected on domain tables (TCA later):

| Concern | 0.1 expectation |
|---|---|
| `pid` | Storage folder. Not the public page. Frontend pages are selected via Site Setting page roles. |
| `hidden` | Yes for Menu, Category, Item, Placement, PriceOption, OpeningHourPeriod. |
| `starttime` / `endtime` | Menu, Item, Placement (seasonal menus and limited-time dishes). Not used on PriceOption or weekly hours. |
| `sorting` | Category in Menu, Placement in Category, PriceOption on Placement, OpeningHourPeriod in weekday order. |
| Localization | As section D. Placement is localization-aware as a structural record, with no independently translated editorial fields in 0.1. |
| `deleted` | Soft delete on all domain tables. |
| `crdate` / `tstamp` | Standard TYPO3 timestamps. |
| Media | Item images via FAL `sys_file_reference`. No Mosaic classes or Mosaic-specific fields. |

Hidden parent records hide their live appearance: a hidden Menu hides its categories/placements; a hidden Item hides all placements of that Item.

## I. Future extension points

- Special/holiday opening hours
- Item modifiers/add-ons beyond PriceOption variants
- Allergen/dietary tags
- Multi-location restaurants (would require a Restaurant record)
- Mosaic Gallery rendering of Item FAL media
- Order, Cart, and immutable OrderItem snapshots (0.3+)
- ARP.top connector using public UUIDs (0.5)

## J. Explicit non-goals for 0.1

- TCA, SQL, Extbase models, repositories, controllers
- Frontend plugins, menu rendering, item detail pages
- Hardcoded public paths (`/menu`, `/item`, `/order`, `/gallery`, `/contact`)
- Order, Cart, checkout, payments
- Mosaic integration or Composer Mosaic dependency
- ARP.top integration
- Custom translation storage
- Inventory, SKUs, tax rules, multi-currency
- TYPO3 `sys_category` as the menu section model
- Holiday hours, modifiers, and multi-location
- Item-owned prices or a price-override layer on top of Placement

## K. Proposed table names

For later implementation (not created now):

| Entity | Table |
|---|---|
| Menu | `tx_arprestaurant_domain_model_menu` |
| Category | `tx_arprestaurant_domain_model_category` |
| Item | `tx_arprestaurant_domain_model_item` |
| Placement | `tx_arprestaurant_domain_model_placement` |
| PriceOption | `tx_arprestaurant_domain_model_priceoption` |
| OpeningHourPeriod | `tx_arprestaurant_domain_model_openinghourperiod` |

Item media uses core `sys_file_reference`. No Restaurant table in 0.1.

## L. Relationship diagram

```
TYPO3 Site
  |- Site Settings
  |    restaurant profile, structured address
  |    currency, timezone
  |    public restaurant UUID
  |    page role UIDs  -->  TYPO3 pages / routing
  |
  `- storage pid
       |
       |- Menu 0..* Category 0..* Placement 1 Item
       |                              |
       |                              `- 0..* PriceOption
       |
       `- OpeningHourPeriod (weekly windows; no public UUID)
```

Placement is the only link from a menu section to an Item, and the only owner of commercial price:

```
Menu
  `- Category
       `- Placement ---- Item
            `- 0..* PriceOption
```
