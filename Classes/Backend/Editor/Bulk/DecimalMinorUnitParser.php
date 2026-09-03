<?php

declare(strict_types=1);

namespace Anatolkin\ArpRestaurant\Backend\Editor\Bulk;

/**
 * Parses decimal price strings into integer minor units.
 *
 * Uses only integer arithmetic. Does not guess commas, currency symbols,
 * or more than the configured number of fractional digits.
 */
final class DecimalMinorUnitParser
{
    public function __construct(
        private readonly int $fractionDigits = 2,
    ) {
        if ($this->fractionDigits < 0) {
            throw new \InvalidArgumentException('Fraction digits must be >= 0', 1756920000);
        }
    }

    /**
     * @return array{ok: true, amountMinor: int}|array{ok: false, error: string}
     */
    public function parse(string $raw): array
    {
        $value = trim($raw);
        if ($value === '') {
            return ['ok' => false, 'error' => 'missingPrice'];
        }

        if (str_starts_with($value, '-')) {
            return ['ok' => false, 'error' => 'negativePrice'];
        }

        if (!preg_match('/^\d+(\.\d+)?$/', $value)) {
            return ['ok' => false, 'error' => 'invalidPrice'];
        }

        $parts = explode('.', $value, 2);
        $wholeDigits = $parts[0];
        $fractionDigits = $parts[1] ?? '';

        if (strlen($fractionDigits) > $this->fractionDigits) {
            return ['ok' => false, 'error' => 'tooManyDecimals'];
        }

        if (strlen($wholeDigits) > 9) {
            return ['ok' => false, 'error' => 'invalidPrice'];
        }

        $fractionDigits = str_pad($fractionDigits, $this->fractionDigits, '0');
        $scale = $this->integerScale();
        $amountMinor = ((int)$wholeDigits * $scale) + (int)$fractionDigits;

        return ['ok' => true, 'amountMinor' => $amountMinor];
    }

    private function integerScale(): int
    {
        $scale = 1;
        for ($i = 0; $i < $this->fractionDigits; ++$i) {
            $scale *= 10;
        }

        return $scale;
    }
}
