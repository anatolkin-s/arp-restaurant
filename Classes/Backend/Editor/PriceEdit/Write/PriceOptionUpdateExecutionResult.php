<?php

declare(strict_types=1);

namespace Anatolkin\ArpRestaurant\Backend\Editor\PriceEdit\Write;

/**
 * outcome: updated | partialFailure | failed
 *
 * @param list<string> $diagnostics
 */
final readonly class PriceOptionUpdateExecutionResult
{
    public function __construct(
        public string $outcome,
        public bool $dataHandlerAttempted,
        public array $diagnostics = [],
    ) {}
}
