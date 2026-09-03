<?php

declare(strict_types=1);

namespace Anatolkin\ArpRestaurant\Backend\DataHandling;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\DataHandling\DataHandler;

/**
 * Aligns public_uuid on copied connected translations after DataHandler copy.
 *
 * Registered as processCmdmapClass; uses processCmdmap_afterFinish so the
 * merged copy map is complete. Only public_uuid is written.
 */
final class CopiedTranslationUuidHook
{
    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly CopiedTranslationUuidDecision $decision,
    ) {}

    public function processCmdmap_afterFinish(DataHandler $dataHandler): void
    {
        $copyMap = $this->getMergedCopyMap($dataHandler);
        if ($copyMap === []) {
            return;
        }

        foreach (CopiedTranslationUuidDecision::TABLES as $table) {
            $tableMap = $copyMap[$table] ?? [];
            if (!is_array($tableMap) || $tableMap === []) {
                continue;
            }

            $copyUids = [];
            foreach ($tableMap as $copyUid) {
                $uid = (int)$copyUid;
                if ($uid > 0) {
                    $copyUids[$uid] = $uid;
                }
            }
            if ($copyUids === []) {
                continue;
            }

            $copiedRecords = $this->loadCopiedRecords($table, array_values($copyUids));
            $updates = $this->decision->decideAlignments($copiedRecords);
            foreach ($updates as $uid => $uuid) {
                $this->updatePublicUuid($table, (int)$uid, $uuid);
            }
        }
    }

    /**
     * Isolates DataHandler::$copyMappingArray_merged.
     *
     * TYPO3 Explained 13.4/14.3 documents this public property as the way to
     * resolve source UID → copy UID after process_cmdmap(). DataHandler marks
     * it @internal and there is no public getter in 13.4 or 14.3. The supported
     * hook processCmdmap_afterFinish is when the merged map is complete.
     *
     * @return array<string, array<int|string, int|string>>
     */
    private function getMergedCopyMap(DataHandler $dataHandler): array
    {
        $map = $dataHandler->copyMappingArray_merged ?? [];
        return is_array($map) ? $map : [];
    }

    /**
     * @param list<int> $uids
     * @return array<int, array{uid: int, sys_language_uid: int, l10n_parent: int, public_uuid: string}>
     */
    private function loadCopiedRecords(string $table, array $uids): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()->removeAll();

        $rows = $queryBuilder
            ->select('uid', 'sys_language_uid', 'l10n_parent', 'public_uuid')
            ->from($table)
            ->where(
                $queryBuilder->expr()->in(
                    'uid',
                    $queryBuilder->createNamedParameter($uids, Connection::PARAM_INT_ARRAY)
                )
            )
            ->executeQuery()
            ->fetchAllAssociative();

        $records = [];
        foreach ($rows as $row) {
            $uid = (int)($row['uid'] ?? 0);
            if ($uid <= 0) {
                continue;
            }
            $records[$uid] = [
                'uid' => $uid,
                'sys_language_uid' => (int)($row['sys_language_uid'] ?? 0),
                'l10n_parent' => (int)($row['l10n_parent'] ?? 0),
                'public_uuid' => (string)($row['public_uuid'] ?? ''),
            ];
        }

        return $records;
    }

    private function updatePublicUuid(string $table, int $uid, string $uuid): void
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()->removeAll();
        $queryBuilder
            ->update($table)
            ->set('public_uuid', $uuid)
            ->where(
                $queryBuilder->expr()->eq(
                    'uid',
                    $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)
                )
            )
            ->executeStatement();
    }
}
