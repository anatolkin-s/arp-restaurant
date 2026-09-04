<?php

declare(strict_types=1);

namespace Anatolkin\ArpRestaurant\Backend\Editor\Apply;

use Anatolkin\ArpRestaurant\Backend\Editor\Identity\BulkIdentityResolutionResult;

/**
 * outcome: applyReady | preparationBlocked
 *
 * @param list<ApplyPlanBlocker> $blockers
 */
final readonly class BulkApplyPreparationResult
{
    public function __construct(
        public string $outcome,
        public BulkIdentityResolutionResult $identity,
        public ?ApplyPlan $plan,
        public array $blockers,
    ) {}
}
