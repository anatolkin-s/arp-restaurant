<?php

declare(strict_types=1);

namespace Anatolkin\ArpRestaurant\Backend\Editor\Visibility;

/**
 * Server-authoritative snapshot of one PriceOption inside the selected Menu graph,
 * including the PriceOption.hidden flag this workflow owns.
 */
final readonly class PriceOptionVisibilityContext
{
    public function __construct(
        public int $uid,
        public int $pid,
        public string $publicUuid,
        public int $tstamp,
        public string $label,
        public int $amountMinor,
        public string $formattedAmount,
        public bool $hidden,
        public int $placementUid,
        public int $sorting,
        public int $menuUid,
        public string $menuTitle,
        public int $categoryUid,
        public string $categoryTitle,
        public int $itemUid,
        public string $itemTitle,
        public ?string $recordEditUrl = null,
    ) {}
}
