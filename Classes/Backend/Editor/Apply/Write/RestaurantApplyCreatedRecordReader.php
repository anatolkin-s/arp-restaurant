<?php

declare(strict_types=1);

namespace Anatolkin\ArpRestaurant\Backend\Editor\Apply\Write;

use Anatolkin\ArpRestaurant\Backend\Editor\MenuGraphAssembler;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\WorkspaceRestriction;

/**
 * SELECT-only fetch of records created by Apply for verification.
 */
final class RestaurantApplyCreatedRecordReader
{
    private const TABLES = [
        MenuGraphAssembler::TABLE_ITEM,
        MenuGraphAssembler::TABLE_CATEGORY,
        MenuGraphAssembler::TABLE_PLACEMENT,
        MenuGraphAssembler::TABLE_PRICEOPTION,
    ];

    public function __construct(
        private readonly ConnectionPool $connectionPool,
    ) {}

    /**
     * @param list<array{table: string, uid: int}> $targets
     * @return array<string, array<string, mixed>> key table:uid
     */
    public function fetchMany(array $targets, BackendUserAuthentication $backendUser): array
    {
        $byTable = [];
        foreach ($targets as $target) {
            $table = $target['table'];
            $uid = $target['uid'];
            if (!in_array($table, self::TABLES, true) || $uid <= 0) {
                continue;
            }
            $byTable[$table][] = $uid;
        }

        $out = [];
        foreach ($byTable as $table => $uids) {
            $uids = array_values(array_unique($uids));
            $qb = $this->connectionPool->getQueryBuilderForTable($table);
            $qb->getRestrictions()->removeAll();
            $qb->getRestrictions()->add(new DeletedRestriction());
            $qb->getRestrictions()->add(new WorkspaceRestriction($backendUser->workspace));

            $rows = $qb
                ->select('*')
                ->from($table)
                ->where(
                    $qb->expr()->in(
                        'uid',
                        $qb->createNamedParameter($uids, Connection::PARAM_INT_ARRAY)
                    )
                )
                ->executeQuery()
                ->fetchAllAssociative();

            foreach ($rows as $row) {
                $uid = (int)($row['uid'] ?? 0);
                if ($uid > 0) {
                    $out[$table . ':' . $uid] = $row;
                }
            }
        }

        return $out;
    }
}
