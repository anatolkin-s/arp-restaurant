<?php

declare(strict_types=1);

namespace Anatolkin\ArpRestaurant\Backend\DataHandling;

/**
 * Pure copy/translation UUID alignment decisions.
 *
 * Default-language copies keep whatever UUID Core already assigned.
 * Connected translation copies must share the copied default-language UUID.
 */
final class CopiedTranslationUuidDecision
{
    public const TABLES = [
        'tx_arprestaurant_domain_model_menu',
        'tx_arprestaurant_domain_model_category',
        'tx_arprestaurant_domain_model_item',
        'tx_arprestaurant_domain_model_placement',
        'tx_arprestaurant_domain_model_priceoption',
    ];

    /**
     * @param array<int, array{
     *     uid: int,
     *     sys_language_uid: int,
     *     l10n_parent: int,
     *     public_uuid: string
     * }> $copiedRecords Copied rows keyed by uid
     * @return array<int, string> uid => public_uuid to persist
     */
    public function decideAlignments(array $copiedRecords): array
    {
        $updates = [];

        foreach ($copiedRecords as $record) {
            $uid = (int)($record['uid'] ?? 0);
            if ($uid <= 0) {
                continue;
            }

            if ((int)($record['sys_language_uid'] ?? 0) <= 0) {
                continue;
            }

            $parentUid = (int)($record['l10n_parent'] ?? 0);
            if ($parentUid <= 0 || !isset($copiedRecords[$parentUid])) {
                continue;
            }

            $parent = $copiedRecords[$parentUid];
            if ((int)($parent['sys_language_uid'] ?? 0) > 0) {
                continue;
            }

            $parentUuid = trim((string)($parent['public_uuid'] ?? ''));
            $currentUuid = trim((string)($record['public_uuid'] ?? ''));
            if ($parentUuid === '' || $parentUuid === $currentUuid) {
                continue;
            }

            $updates[$uid] = $parentUuid;
        }

        return $updates;
    }

    public function isRestaurantTable(string $table): bool
    {
        return in_array($table, self::TABLES, true);
    }
}
