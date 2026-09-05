<?php

declare(strict_types=1);

namespace Anatolkin\ArpRestaurant\Backend\Editor\PriceCreate;

/**
 * Review-only plan for creating one PriceOption under an existing Placement.
 * Does not mint uid, public_uuid, crdate, or tstamp for the future row.
 *
 * @param list<ExistingPriceOptionSnapshot> $existingPriceOptions
 */
final readonly class PriceOptionCreatePlan
{
    /**
     * @param list<ExistingPriceOptionSnapshot> $existingPriceOptions
     */
    public function __construct(
        public int $pid,
        public int $menuUid,
        public string $menuPublicUuid,
        public int $menuTstamp,
        public string $menuTitle,
        public int $categoryUid,
        public string $categoryPublicUuid,
        public int $categoryTstamp,
        public string $categoryTitle,
        public int $placementUid,
        public string $placementPublicUuid,
        public int $placementTstamp,
        public int $itemUid,
        public string $itemPublicUuid,
        public int $itemTstamp,
        public string $itemTitle,
        public string $label,
        public int $amountMinor,
        public string $formattedAmount,
        public int $plannedSorting,
        public array $existingPriceOptions,
    ) {}
}
