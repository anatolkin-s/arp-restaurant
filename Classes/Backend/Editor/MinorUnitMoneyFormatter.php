<?php

declare(strict_types=1);

namespace Anatolkin\ArpRestaurant\Backend\Editor;

/**
 * Display-only formatter for integer minor units.
 *
 * Fraction digits are injected so Site Settings can later own currency scale.
 * Callers must not divide amounts by 100 in templates or controllers.
 */
final class MinorUnitMoneyFormatter
{
    public function __construct(
        private readonly int $fractionDigits = 2,
    ) {
        if ($this->fractionDigits < 0) {
            throw new \InvalidArgumentException('Fraction digits must be >= 0', 1756900000);
        }
    }

    public function format(int $amountMinor): string
    {
        $scale = 10 ** $this->fractionDigits;
        $negative = $amountMinor < 0;
        $absolute = abs($amountMinor);
        $whole = intdiv($absolute, $scale);
        $fraction = $absolute % $scale;

        $sign = $negative ? '-' : '';
        if ($this->fractionDigits === 0) {
            return $sign . (string)$whole;
        }

        return sprintf('%s%d.%0' . $this->fractionDigits . 'd', $sign, $whole, $fraction);
    }
}
