<?php

declare(strict_types=1);

namespace Anatolkin\ArpRestaurant\Backend\Editor\Visibility;

/**
 * Load / preparation blocker for PriceOption.hidden review.
 *
 * Graph codes match the PriceEdit membership vocabulary. Permission and
 * input codes are visibility-specific.
 */
final readonly class PriceOptionVisibilityBlocker
{
    public function __construct(
        public string $code,
        public string $detail = '',
    ) {}
}
