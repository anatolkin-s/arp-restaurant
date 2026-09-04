<?php

declare(strict_types=1);

namespace Anatolkin\ArpRestaurant\Backend\Editor\PriceEdit;

final readonly class PriceOptionUpdateValues
{
    public function __construct(
        public string $label,
        public int $amountMinor,
        public string $formattedAmount,
    ) {}
}
