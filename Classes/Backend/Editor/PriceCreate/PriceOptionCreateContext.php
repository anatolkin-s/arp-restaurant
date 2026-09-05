<?php

declare(strict_types=1);

namespace Anatolkin\ArpRestaurant\Backend\Editor\PriceCreate;

/**
 * Server-authoritative snapshot for reviewing one new PriceOption under an existing Placement.
 *
 * @param list<ExistingPriceOptionSnapshot> $existingPriceOptions
 */
final readonly class PriceOptionCreateContext
{
    public int $existingPriceCount;

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
        public int $placementCategoryUid,
        public int $placementItemUid,
        public int $placementSorting,
        public int $placementHidden,
        public int $placementStarttime,
        public int $placementEndtime,
        public int $itemUid,
        public string $itemPublicUuid,
        public int $itemTstamp,
        public string $itemTitle,
        public int $itemHidden,
        public int $itemStarttime,
        public int $itemEndtime,
        public array $existingPriceOptions,
        public ?string $recordEditUrl = null,
    ) {
        $this->existingPriceCount = count($this->existingPriceOptions);
    }
}
