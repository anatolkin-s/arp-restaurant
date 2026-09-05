<?php

declare(strict_types=1);

namespace Anatolkin\ArpRestaurant\Backend\Editor\PriceCreate;

/**
 * outcome: loaded | blocked
 *
 * @param list<PriceOptionCreateBlocker> $blockers
 */
final readonly class PriceOptionCreateLoadResult
{
    public function __construct(
        public string $outcome,
        public ?PriceOptionCreateContext $context,
        public array $blockers,
    ) {}
}
