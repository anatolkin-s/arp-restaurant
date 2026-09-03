<?php

declare(strict_types=1);

namespace Anatolkin\ArpRestaurant\Backend\Editor\Identity;

/**
 * Per draft row link to Category/Item resolutions (same order as draft.rows).
 */
final readonly class IdentityBoundRow
{
    public function __construct(
        public string $draftKey,
        public ?IdentityResolution $categoryResolution,
        public ?IdentityResolution $itemResolution,
    ) {}
}
