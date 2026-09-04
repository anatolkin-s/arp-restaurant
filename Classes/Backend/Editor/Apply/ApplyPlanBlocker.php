<?php

declare(strict_types=1);

namespace Anatolkin\ArpRestaurant\Backend\Editor\Apply;

/**
 * Preparation-level blocker distinct from draft errors and identity blockers.
 * code examples: draftNotValid, identityNotResolved, applyPlanInvariant,
 * missingBoundResolution, missingAmountMinor
 */
final readonly class ApplyPlanBlocker
{
    public function __construct(
        public string $code,
        public string $detail = '',
    ) {}
}
