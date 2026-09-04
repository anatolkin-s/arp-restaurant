<?php

declare(strict_types=1);

namespace Anatolkin\ArpRestaurant\Backend\Editor\Apply\Write;

/**
 * Shared public_uuid usability check for Apply verification.
 * Matches the identity-resolution UUID contract.
 */
final class ApplyPublicUuid
{
    private const UUID_PATTERN = '/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/';

    public static function isUsable(string $uuid): bool
    {
        return $uuid !== '' && preg_match(self::UUID_PATTERN, $uuid) === 1;
    }
}
