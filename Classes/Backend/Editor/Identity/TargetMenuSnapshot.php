<?php

declare(strict_types=1);

namespace Anatolkin\ArpRestaurant\Backend\Editor\Identity;

/**
 * Last-seen target Menu identity for concurrency (future Apply).
 */
final readonly class TargetMenuSnapshot
{
    public function __construct(
        public int $uid,
        public int $pid,
        public string $publicUuid,
        public int $tstamp,
        public string $title,
    ) {}
}
