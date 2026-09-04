<?php

declare(strict_types=1);

namespace Anatolkin\ArpRestaurant\Backend\Editor\Apply;

/**
 * Exact structural counts for the built ApplyPlan.
 */
final readonly class ApplyPlanSummary
{
    public function __construct(
        public int $createCategories,
        public int $createItems,
        public int $createPlacements,
        public int $createPriceOptions,
        public int $reuseCategories,
        public int $reuseItems,
    ) {}
}
