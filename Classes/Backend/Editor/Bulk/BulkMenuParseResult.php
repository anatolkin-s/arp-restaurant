<?php

declare(strict_types=1);

namespace Anatolkin\ArpRestaurant\Backend\Editor\Bulk;

final readonly class BulkMenuParseResult
{
    /**
     * @param list<BulkMenuRow> $rows
     * @param list<BulkMenuPreviewSection> $sections
     */
    public function __construct(
        public array $rows,
        public array $sections,
        public int $validCount,
        public int $invalidCount,
        public string $globalError = '',
    ) {}

    public function hasGlobalError(): bool
    {
        return $this->globalError !== '';
    }
}
