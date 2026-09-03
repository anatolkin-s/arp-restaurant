<?php

declare(strict_types=1);

namespace Anatolkin\ArpRestaurant\Backend\Editor\Bulk;

final readonly class BulkMenuPreviewSection
{
    /**
     * @param list<BulkMenuRow> $rows
     */
    public function __construct(
        public string $category,
        public array $rows,
    ) {}
}
