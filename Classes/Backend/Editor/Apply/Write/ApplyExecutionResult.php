<?php

declare(strict_types=1);

namespace Anatolkin\ArpRestaurant\Backend\Editor\Apply\Write;

/**
 * outcome: applied | partialFailure | failed
 *
 * dataHandlerAttempted: true only if process_datamap() was invoked.
 *
 * @param list<string> $diagnostics bounded user-facing messages
 * @param array<string, int> $createdUidsByLocalRef localRef => uid when known
 */
final readonly class ApplyExecutionResult
{
    public function __construct(
        public string $outcome,
        public int $createdCategories,
        public int $createdItems,
        public int $createdPlacements,
        public int $createdPriceOptions,
        public bool $dataHandlerAttempted,
        public array $diagnostics = [],
        public array $createdUidsByLocalRef = [],
    ) {}
}
