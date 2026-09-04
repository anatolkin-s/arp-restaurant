<?php

declare(strict_types=1);

namespace Anatolkin\ArpRestaurant\Backend\Editor\Visibility;

/**
 * outcome: visibilityUpdateReady | preparationBlocked | noChanges
 *
 * @param list<PriceOptionVisibilityBlocker> $blockers
 */
final readonly class PriceOptionVisibilityPreparationResult
{
    public function __construct(
        public string $outcome,
        public PriceOptionVisibilityContext $context,
        public ?PriceOptionVisibilityPlan $plan,
        public array $blockers,
    ) {}
}
