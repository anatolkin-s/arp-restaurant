<?php

declare(strict_types=1);

namespace Anatolkin\ArpRestaurant\Backend\Editor\Visibility;

/**
 * Immutable review plan for a PriceOption.hidden change. No persistence.
 *
 * currentHidden / requestedHidden are 0 or 1 only.
 */
final readonly class PriceOptionVisibilityPlan
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
        public int $currentHidden,
        public int $requestedHidden,
        public string $menuTitle,
        public string $categoryTitle,
        public string $itemTitle,
        public string $label,
        public string $formattedAmount,
        public string $fingerprint,
    ) {}
}
