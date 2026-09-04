<?php

declare(strict_types=1);

namespace Anatolkin\ArpRestaurant\Backend\Editor\Apply;

/**
 * Draft-local Category or Item plan entity. localRef is not a TYPO3 uid and
 * not an integration identity.
 *
 * status: create | reuse
 */
final readonly class ApplyEntityReference
{
    public function __construct(
        public string $localRef,
        public string $status,
        public string $displayTitle,
        public string $canonicalTitle = '',
        public ?int $uid = null,
        public ?string $publicUuid = null,
        public ?int $tstamp = null,
        public ?int $pid = null,
    ) {}
}
