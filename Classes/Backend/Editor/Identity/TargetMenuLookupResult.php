<?php

declare(strict_types=1);

namespace Anatolkin\ArpRestaurant\Backend\Editor\Identity;

/**
 * Result of re-resolving the claimed target Menu uid against pid / language.
 * blocker: '' | missingTargetMenu | wrongPidTargetMenu | translatedTargetMenu | unusableTargetMenuUuid
 */
final readonly class TargetMenuLookupResult
{
    public function __construct(
        public ?TargetMenuSnapshot $snapshot = null,
        public string $blocker = '',
    ) {}
}
