<?php

declare(strict_types=1);

namespace Anatolkin\ArpRestaurant\Backend\Editor\ViewModel;

final readonly class PriceOptionRow
{
    public function __construct(
        public int $uid,
        public string $label,
        public string $displayLabel,
        public int $amountMinor,
        public string $formattedAmount,
        public bool $hidden,
        public ?string $editUrl,
    ) {}
}
