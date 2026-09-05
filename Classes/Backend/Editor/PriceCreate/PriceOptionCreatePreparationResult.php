<?php

declare(strict_types=1);

namespace Anatolkin\ArpRestaurant\Backend\Editor\PriceCreate;

/**
 * outcome: createReady | preparationBlocked
 *
 * @param list<PriceOptionCreateBlocker> $blockers
 */
final readonly class PriceOptionCreatePreparationResult
{
    public function __construct(
        public string $outcome,
        public PriceOptionCreateContext $context,
        public ?PriceOptionCreatePlan $plan,
        public array $blockers,
    ) {}
}
