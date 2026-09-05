<?php

declare(strict_types=1);

namespace Anatolkin\ArpRestaurant\Backend\Editor\PriceCreate;

/**
 * Fresh DB snapshot of one existing PriceOption on the selected Placement.
 */
final readonly class ExistingPriceOptionSnapshot
{
    public function __construct(
        public int $uid,
        public string $publicUuid,
        public int $tstamp,
        public string $label,
        public int $amountMinor,
        public int $sorting,
        public int $hidden,
    ) {}
}
