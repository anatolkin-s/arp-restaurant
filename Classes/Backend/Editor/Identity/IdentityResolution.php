<?php

declare(strict_types=1);

namespace Anatolkin\ArpRestaurant\Backend\Editor\Identity;

/**
 * Explicit create / reuse / ambiguous / inaccessible resolution for one
 * distinct draft Category or Item match key. Avoid get/is/has accessors that
 * collide with Fluid ObjectAccess on these property names.
 *
 * status values: create | reuse | ambiguous | inaccessible
 *
 * normalizedTitle: first cleanDisplayTitle seen in draft originalOrder (CREATE
 * proposal / display reference).
 * canonicalTitle: persisted candidate title on REUSE; empty otherwise.
 */
final readonly class IdentityResolution
{
    public function __construct(
        public string $status,
        public string $draftIdentityKey,
        public string $normalizedTitle,
        public int $matchCount,
        public ?int $uid = null,
        public ?string $publicUuid = null,
        public ?int $tstamp = null,
        public ?int $pid = null,
        public string $canonicalTitle = '',
    ) {}
}
