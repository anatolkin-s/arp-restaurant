<?php

declare(strict_types=1);

namespace Anatolkin\ArpRestaurant\Backend\Editor\Apply\Write;

/**
 * Write-time append sorting positions. SELECT-derived; not identity.
 *
 * @param array<int, int> $placementNextByReusedCategoryUid category uid => next sorting value
 */
final readonly class ApplySortContext
{
    public const DEFAULT_STEP = 256;

    public function __construct(
        public int $categoryNextSorting,
        public array $placementNextByReusedCategoryUid,
        public int $step = self::DEFAULT_STEP,
        public int $newCategoryPlacementBase = self::DEFAULT_STEP,
    ) {}
}
