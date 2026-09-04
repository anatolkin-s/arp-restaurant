<?php

declare(strict_types=1);

namespace Anatolkin\ArpRestaurant\Backend\Editor\Apply\Write;

/**
 * Pure DataHandler datamap + NEW token map for one Apply.
 *
 * @param array<string, array<string, array<string, int|string>>> $dataMap table => [NEW => fields]
 * @param array<string, string> $localRefToNewToken
 * @param list<ApplyExpectedCreate> $expectedCreates
 */
final readonly class ApplyDataMap
{
    public function __construct(
        public array $dataMap,
        public array $localRefToNewToken,
        public array $expectedCreates,
    ) {}
}
