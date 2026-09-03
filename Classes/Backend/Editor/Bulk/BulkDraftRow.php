<?php

declare(strict_types=1);

namespace Anatolkin\ArpRestaurant\Backend\Editor\Bulk;

final readonly class BulkDraftRow
{
    /**
     * @param list<string> $errors Blocking codes only
     * @param list<string> $warnings Non-blocking advisories
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
        public array $warnings = [],
    ) {}

    public function isValid(): bool
    {
        return $this->errors === [];
    }
}
