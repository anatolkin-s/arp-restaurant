<?php

declare(strict_types=1);

namespace Anatolkin\ArpRestaurant\Backend\Editor\Identity;

use Anatolkin\ArpRestaurant\Backend\Editor\MenuGraphAssembler;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\WorkspaceRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Read-only QueryBuilder SELECTs for editor identity resolution.
 *
 * Restrictions (aligned with MenuGraphReader / EDITOR_WRITE_CONTRACT):
 * - DeletedRestriction
 * - WorkspaceRestriction (live module: workspace 0)
 * - sys_language_uid = 0 for match sets
 * - selected pid only
 * - hidden/scheduled included (no HiddenRestriction / endtime filter)
 * - Item candidates: pid-wide
 * - Category candidates: target Menu only
 *
 * SELECT / executeQuery only. No insert/update/delete.
 *
 * Title IN(...) may over-fetch under case-insensitive collations; callers must
 * confirm matches with PHP strict equality before CREATE/REUSE/AMBIGUOUS.
 */
final class RestaurantIdentityReader
{
    private const UUID_PATTERN = '/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/';

    public function __construct(
        private readonly ConnectionPool $connectionPool,
    ) {}

    public function lookupTargetMenu(
        int $pid,
        int $claimedMenuUid,
        BackendUserAuthentication $backendUser,
    ): TargetMenuLookupResult {
        if ($claimedMenuUid <= 0 || $pid <= 0) {
            return new TargetMenuLookupResult(blocker: 'missingTargetMenu');
        }

        $queryBuilder = $this->createQueryBuilder(MenuGraphAssembler::TABLE_MENU, $backendUser);
        $row = $queryBuilder
            ->select('uid', 'pid', 'title', 'public_uuid', 'tstamp', 'sys_language_uid')
            ->from(MenuGraphAssembler::TABLE_MENU)
            ->where(
                $queryBuilder->expr()->eq(
                    'uid',
                    $queryBuilder->createNamedParameter($claimedMenuUid, Connection::PARAM_INT)
                ),
            )
            ->executeQuery()
            ->fetchAssociative();

        if (!is_array($row)) {
            return new TargetMenuLookupResult(blocker: 'missingTargetMenu');
        }

        if ((int)($row['sys_language_uid'] ?? -1) !== 0) {
            return new TargetMenuLookupResult(blocker: 'translatedTargetMenu');
        }

        if ((int)($row['pid'] ?? -1) !== $pid) {
            return new TargetMenuLookupResult(blocker: 'wrongPidTargetMenu');
        }

        $publicUuid = trim((string)($row['public_uuid'] ?? ''));
        if ($publicUuid === '' || preg_match(self::UUID_PATTERN, $publicUuid) !== 1) {
            return new TargetMenuLookupResult(blocker: 'unusableTargetMenuUuid');
        }

        return new TargetMenuLookupResult(
            snapshot: new TargetMenuSnapshot(
                uid: (int)$row['uid'],
                pid: (int)$row['pid'],
                publicUuid: $publicUuid,
                tstamp: (int)($row['tstamp'] ?? 0),
                title: trim((string)($row['title'] ?? '')),
            ),
        );
    }

    /**
     * @param list<string> $normalizedTitles Distinct trimmed draft Item titles
     * @return list<PersistedIdentityCandidate>
     */
    public function findItemCandidates(
        int $pid,
        array $normalizedTitles,
        BackendUserAuthentication $backendUser,
    ): array {
        return $this->findCandidatesByTitles(
            MenuGraphAssembler::TABLE_ITEM,
            $pid,
            $normalizedTitles,
            $backendUser,
            null,
        );
    }

    /**
     * @param list<string> $normalizedTitles Distinct trimmed draft Category titles
     * @return list<PersistedIdentityCandidate>
     */
    public function findCategoryCandidates(
        int $pid,
        int $targetMenuUid,
        array $normalizedTitles,
        BackendUserAuthentication $backendUser,
    ): array {
        return $this->findCandidatesByTitles(
            MenuGraphAssembler::TABLE_CATEGORY,
            $pid,
            $normalizedTitles,
            $backendUser,
            $targetMenuUid,
        );
    }

    /**
     * @param list<string> $normalizedTitles
     * @return list<PersistedIdentityCandidate>
     */
    private function findCandidatesByTitles(
        string $table,
        int $pid,
        array $normalizedTitles,
        BackendUserAuthentication $backendUser,
        ?int $targetMenuUid,
    ): array {
        $titles = [];
        foreach ($normalizedTitles as $title) {
            if (is_string($title) && $title !== '') {
                $titles[$title] = $title;
            }
        }
        if ($titles === [] || $pid <= 0) {
            return [];
        }

        $titleList = array_values($titles);
        $queryBuilder = $this->createQueryBuilder($table, $backendUser);
        $predicates = [
            $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($pid, Connection::PARAM_INT)),
            $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
            $queryBuilder->expr()->in(
                'title',
                $queryBuilder->createNamedParameter($titleList, Connection::PARAM_STR_ARRAY)
            ),
        ];
        if ($targetMenuUid !== null) {
            $predicates[] = $queryBuilder->expr()->eq(
                'menu',
                $queryBuilder->createNamedParameter($targetMenuUid, Connection::PARAM_INT)
            );
        }

        $rows = $queryBuilder
            ->select('uid', 'pid', 'title', 'public_uuid', 'tstamp')
            ->from($table)
            ->where(...$predicates)
            ->executeQuery()
            ->fetchAllAssociative();

        if (!is_array($rows)) {
            return [];
        }

        $candidates = [];
        foreach ($rows as $row) {
            $candidates[] = new PersistedIdentityCandidate(
                uid: (int)($row['uid'] ?? 0),
                pid: (int)($row['pid'] ?? 0),
                title: (string)($row['title'] ?? ''),
                publicUuid: trim((string)($row['public_uuid'] ?? '')),
                tstamp: (int)($row['tstamp'] ?? 0),
            );
        }

        return $candidates;
    }

    private function createQueryBuilder(string $table, BackendUserAuthentication $backendUser): QueryBuilder
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()->removeAll();
        $queryBuilder->getRestrictions()->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        $queryBuilder->getRestrictions()->add(
            GeneralUtility::makeInstance(WorkspaceRestriction::class, $backendUser->workspace)
        );

        return $queryBuilder;
    }
}
