<?php

declare(strict_types=1);

namespace Anatolkin\ArpRestaurant\Backend\Editor\Identity;

/**
 * Read-only candidate row for title-based identity matching.
 * Final title equality is decided in PHP, not by DB collation.
 */
final readonly class PersistedIdentityCandidate
{
    public function __construct(
        public int $uid,
        public int $pid,
        public string $title,
        public string $publicUuid,
        public int $tstamp,
    ) {}
}
