<?php

declare(strict_types=1);

namespace Anatolkin\ArpRestaurant\Backend\Editor\Identity;

/**
 * Future confirmation preview counts. Append semantics: Placement counts are
 * draft-run derived and do not deduct existing stored Placements.
 */
final readonly class IdentityResolutionSummary
{
    public function __construct(
        public int $createCategories,
        public int $createItems,
        public int $createPlacements,
        public int $createPriceOptions,
        public int $reuseCategories,
        public int $reuseItems,
        public int $ambiguousCategories,
        public int $ambiguousItems,
    ) {}
}
