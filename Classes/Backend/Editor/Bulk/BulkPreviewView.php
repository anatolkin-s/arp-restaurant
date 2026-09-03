<?php

declare(strict_types=1);

namespace Anatolkin\ArpRestaurant\Backend\Editor\Bulk;

final readonly class BulkPreviewView
{
    public function __construct(
        public string $formAction,
        public string $formToken,
        public string $rawInput,
        public ?BulkMenuParseResult $result,
        public string $requestError,
        public int $pid,
        public int $menuUid,
        public int $maxBytes,
        public int $maxRows,
    ) {}
}
