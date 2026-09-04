<?php

declare(strict_types=1);

namespace Anatolkin\ArpRestaurant\Backend\Editor\PriceEdit;

/**
 * Server-authoritative snapshot of one PriceOption inside the selected Menu graph.
 * public_uuid is the logical identity; uid is TYPO3-internal only.
 */
final readonly class PriceOptionEditContext
{
    public function __construct(
        public int $uid,
        public int $pid,
        public string $publicUuid,
        public int $tstamp,
        public string $label,
        public int $amountMinor,
        public string $formattedAmount,
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
