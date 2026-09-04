<?php

declare(strict_types=1);

namespace Anatolkin\ArpRestaurant\Backend\Editor\Bulk;

final readonly class BulkDraftValidationResult
{
    /**
     * @param list<BulkDraftRow> $rows Canonical originalOrder, not request array order
     */
    public function __construct(
        public array $rows,
        public int $validCount,
        public int $invalidCount,
        public string $globalError = '',
    ) {}

    /**
     * Cell-valid and run-valid. Warnings do not block. Not apply-ready alone:
     * identity resolution (EDITOR-2B2) and Prepare apply (EDITOR-2B3) are separate.
     */
    public function isDraftValid(): bool
    {
        return $this->globalError === '' && $this->invalidCount === 0 && $this->rows !== [];
    }
}
