<?php

declare(strict_types=1);

namespace Anatolkin\ArpRestaurant\Backend\Editor\PriceEdit;

/**
 * Pure review plan for an existing PriceOption label/amount update.
 * Carries concurrency snapshot fields for a future confirmed write step.
 */
final readonly class PriceOptionUpdatePlan
{
    public function __construct(
        public int $uid,
        public int $pid,
        public string $publicUuid,
        public int $tstamp,
        public int $placementUid,
        public PriceOptionUpdateValues $before,
        public PriceOptionUpdateValues $after,
        public string $menuTitle,
        public string $categoryTitle,
        public string $itemTitle,
    ) {}
}
