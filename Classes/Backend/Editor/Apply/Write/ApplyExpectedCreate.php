<?php

declare(strict_types=1);

namespace Anatolkin\ArpRestaurant\Backend\Editor\Apply\Write;

/**
 * One expected CREATE after DataHandler for read-back verification.
 *
 * @param array<string, int|string> $expectedFields column => expected value
 */
final readonly class ApplyExpectedCreate
{
    public function __construct(
        public string $table,
        public string $newToken,
        public string $localRef,
        public string $entityKind,
        public array $expectedFields,
    ) {}
}
