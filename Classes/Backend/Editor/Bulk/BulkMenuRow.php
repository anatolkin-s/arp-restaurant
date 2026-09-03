<?php

declare(strict_types=1);

namespace Anatolkin\ArpRestaurant\Backend\Editor\Bulk;

final readonly class BulkMenuRow
{
    /**
     * @param list<string> $errors
     */
    public function __construct(
        public int $sourceLine,
        public string $category,
        public string $item,
        public string $variant,
        public string $priceRaw,
        public ?int $amountMinor,
        public string $formattedAmount,
        public array $errors,
    ) {}

    public function isValid(): bool
    {
        return $this->errors === [];
    }

    public function getVariantDisplay(): string
    {
        return $this->variant === '' ? '—' : $this->variant;
    }
}
