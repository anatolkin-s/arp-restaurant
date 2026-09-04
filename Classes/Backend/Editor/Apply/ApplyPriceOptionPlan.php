<?php

declare(strict_types=1);

namespace Anatolkin\ArpRestaurant\Backend\Editor\Apply;

/**
 * One future PriceOption on a Placement. amountMinor is integer only.
 * formattedAmount is confirmation display only and is not fingerprint input.
 */
final readonly class ApplyPriceOptionPlan
{
    public function __construct(
        public string $localRef,
        public string $draftKey,
        public int $sourceLine,
        public int $originalOrder,
        public string $label,
        public int $amountMinor,
        public string $formattedAmount = '',
    ) {}
}
