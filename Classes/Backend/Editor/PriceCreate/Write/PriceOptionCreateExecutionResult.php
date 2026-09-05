<?php

declare(strict_types=1);

namespace Anatolkin\ArpRestaurant\Backend\Editor\PriceCreate\Write;

final readonly class PriceOptionCreateExecutionResult
{
    public function __construct(
        public string $outcome,
        public bool $dataHandlerAttempted,
        public array $diagnostics = [],
        public ?int $createdUid = null,
    ) {}
}
