<?php

declare(strict_types=1);

namespace Anatolkin\ArpRestaurant\Backend\Editor\Bulk;

final readonly class BulkDraftRow
{
    /**
     * @param list<string> $errors
     */
    public function __construct(
        public string $draftKey,
        public int $originalOrder,
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
}
