<?php

declare(strict_types=1);

namespace Anatolkin\ArpRestaurant\Backend\Editor\PriceEdit;

/**
 * outcome: updateReady | preparationBlocked | noChanges
 *
 * @param list<PriceOptionEditBlocker> $blockers
 */
final readonly class PriceOptionUpdatePreparationResult
{
    public function __construct(
        public string $outcome,
        public PriceOptionEditContext $context,
        public ?PriceOptionUpdatePlan $plan,
        public array $blockers,
    ) {}
}
