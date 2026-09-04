<?php

declare(strict_types=1);

namespace Anatolkin\ArpRestaurant\Backend\Editor\PriceEdit;

/**
 * outcome: loaded | blocked
 *
 * @param list<PriceOptionEditBlocker> $blockers
 */
final readonly class PriceOptionEditLoadResult
{
    public function __construct(
        public string $outcome,
        public ?PriceOptionEditContext $context,
        public array $blockers,
    ) {}
}
