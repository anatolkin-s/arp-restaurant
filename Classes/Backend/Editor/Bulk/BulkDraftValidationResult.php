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

    public function hasGlobalError(): bool
    {
        return $this->globalError !== '';
    }

    /**
     * Cell-valid and run-valid. Not apply-ready: identity resolution is EDITOR-2B2.
     */
    public function isDraftValid(): bool
    {
        return $this->globalError === '' && $this->invalidCount === 0 && $this->rows !== [];
    }
}
