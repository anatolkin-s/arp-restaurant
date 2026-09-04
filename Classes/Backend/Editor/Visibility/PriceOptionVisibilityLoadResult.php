<?php

declare(strict_types=1);

namespace Anatolkin\ArpRestaurant\Backend\Editor\Visibility;

/**
 * outcome: loaded | blocked
 *
 * @param list<PriceOptionVisibilityBlocker> $blockers
 */
final readonly class PriceOptionVisibilityLoadResult
{
    public function __construct(
        public string $outcome,
        public ?PriceOptionVisibilityContext $context,
        public array $blockers,
    ) {}
}
