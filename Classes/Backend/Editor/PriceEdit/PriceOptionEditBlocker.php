<?php

declare(strict_types=1);

namespace Anatolkin\ArpRestaurant\Backend\Editor\PriceEdit;

/**
 * Load / preparation blocker for existing PriceOption edit review.
 *
 * code examples: missingPriceOption, wrongPid, wrongMenu, translatedPriceOption,
 * brokenPlacement, brokenCategory, missingPublicUuid, inaccessiblePriceOption,
 * fieldModifyDenied, invalidPrice, missingPrice, noChanges (outcome, not blocker)
 */
final readonly class PriceOptionEditBlocker
{
    public function __construct(
        public string $code,
        public string $detail = '',
    ) {}
}
