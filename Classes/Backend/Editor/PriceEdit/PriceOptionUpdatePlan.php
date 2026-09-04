<?php

declare(strict_types=1);

namespace Anatolkin\ArpRestaurant\Backend\Editor\PriceEdit;

/**
 * Pure review/confirm plan for an existing PriceOption label/amount update.
 */
final readonly class PriceOptionUpdatePlan
{
    public function __construct(
        public int $uid,
        public int $pid,
        public string $publicUuid,
        public int $tstamp,
        public int $placementUid,
        public int $menuUid,
        public int $categoryUid,
        public int $itemUid,
        public PriceOptionUpdateValues $before,
        public PriceOptionUpdateValues $after,
        public string $menuTitle,
        public string $categoryTitle,
        public string $itemTitle,
        public string $fingerprint,
    ) {}
}
