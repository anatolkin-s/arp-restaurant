<?php

declare(strict_types=1);

namespace Anatolkin\ArpRestaurant\Backend\Editor\Apply;

use Anatolkin\ArpRestaurant\Backend\Editor\Identity\TargetMenuSnapshot;

/**
 * Exact read-only description of a future append Apply. fingerprint is
 * confirmation continuity only — not CSRF, auth, or external identity.
 *
 * @param list<ApplyEntityReference> $categories
 * @param list<ApplyEntityReference> $items
 * @param list<ApplyPlacementPlan> $placements
 */
final readonly class ApplyPlan
{
    public function __construct(
        public TargetMenuSnapshot $targetMenu,
        public array $categories,
        public array $items,
        public array $placements,
        public ApplyPlanSummary $summary,
        public string $fingerprint,
    ) {}
}
