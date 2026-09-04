<?php

declare(strict_types=1);

namespace Anatolkin\ArpRestaurant\Backend\Editor\Bulk;

use Anatolkin\ArpRestaurant\Backend\Editor\Apply\BulkApplyPreparationResult;
use Anatolkin\ArpRestaurant\Backend\Editor\Identity\BulkIdentityResolutionResult;

final readonly class BulkPreviewView
{
    public function __construct(
        public string $formAction,
        public string $previewToken,
        public string $revalidateToken,
        public string $resetToken,
        public string $resolveToken,
        public string $prepareToken,
        public string $applyToken,
        public string $rawInput,
        public string $parseGlobalError,
        public ?BulkDraftValidationResult $draft,
        public string $requestError,
        public int $pid,
        public int $menuUid,
        public int $maxBytes,
        public int $maxRows,
        public ?BulkIdentityResolutionResult $identity = null,
        public ?BulkApplyPreparationResult $apply = null,
        public string $confirmationWarning = '',
    ) {}
}
