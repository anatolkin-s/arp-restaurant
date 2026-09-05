<?php

declare(strict_types=1);

namespace Anatolkin\ArpRestaurant\Backend\Editor\PriceCreate;

/**
 * Load / preparation blocker for adding one PriceOption under an existing Placement.
 */
final readonly class PriceOptionCreateBlocker
{
    public function __construct(
        public string $code,
        public string $detail = '',
    ) {}
}
