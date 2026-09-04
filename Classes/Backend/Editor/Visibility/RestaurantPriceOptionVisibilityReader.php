<?php

declare(strict_types=1);

namespace Anatolkin\ArpRestaurant\Backend\Editor\Visibility;

use Anatolkin\ArpRestaurant\Backend\Editor\MenuGraphAssembler;
use Anatolkin\ArpRestaurant\Backend\Editor\RecordEditUrlBuilder;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\WorkspaceRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * SELECT-only loader for an existing PriceOption visibility review context.
 *
 * Proves membership: selected pid → Menu → Category → Placement → PriceOption.
 * Does not accept a PriceOption merely because uid/pid exist.
 *
 * Restrictions aligned with MenuGraphReader:
 * - DeletedRestriction
 * - WorkspaceRestriction
 * - sys_language_uid = 0
 * - selected pid only
 * - hidden included (no HiddenRestriction)
 *
 * SELECT / executeQuery only. No insert/update/delete.
 */
final class RestaurantPriceOptionVisibilityReader
{
    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly PriceOptionVisibilityGraphAssessor $graphAssessor,
    ) {}

    public function load(
        int $pid,
        int $selectedMenuUid,
        int $priceOptionUid,
        BackendUserAuthentication $backendUser,
        ?RecordEditUrlBuilder $editUrlBuilder = null,
    ): PriceOptionVisibilityLoadResult {
        if ($priceOptionUid <= 0 || $pid <= 0 || $selectedMenuUid <= 0) {
            return new PriceOptionVisibilityLoadResult(
                outcome: 'blocked',
                context: null,
                blockers: [new PriceOptionVisibilityBlocker('missingPriceOption')],
            );
        }

        $priceOption = $this->fetchByUid(
            MenuGraphAssembler::TABLE_PRICEOPTION,
            $priceOptionUid,
            $backendUser,
            ['uid', 'pid', 'placement', 'label', 'amount', 'sorting', 'hidden', 'public_uuid', 'tstamp', 'sys_language_uid'],
        );

        $placementUid = is_array($priceOption) ? (int)($priceOption['placement'] ?? 0) : 0;
        $placement = $placementUid > 0 ? $this->fetchByUid(
            MenuGraphAssembler::TABLE_PLACEMENT,
            $placementUid,
            $backendUser,
            ['uid', 'pid', 'category', 'item', 'sorting', 'hidden', 'sys_language_uid'],
        ) : null;

        $categoryUid = is_array($placement) ? (int)($placement['category'] ?? 0) : 0;
        $category = $categoryUid > 0 ? $this->fetchByUid(
            MenuGraphAssembler::TABLE_CATEGORY,
            $categoryUid,
            $backendUser,
            ['uid', 'pid', 'menu', 'title', 'sorting', 'hidden', 'sys_language_uid'],
        ) : null;

        $itemUid = is_array($placement) ? (int)($placement['item'] ?? 0) : 0;
        $item = $itemUid > 0 ? $this->fetchByUid(
            MenuGraphAssembler::TABLE_ITEM,
            $itemUid,
            $backendUser,
            ['uid', 'pid', 'title', 'hidden', 'sys_language_uid'],
        ) : null;

        $menuUid = is_array($category) ? (int)($category['menu'] ?? 0) : 0;
        $menu = $menuUid > 0 ? $this->fetchByUid(
            MenuGraphAssembler::TABLE_MENU,
            $menuUid,
            $backendUser,
            ['uid', 'pid', 'title', 'hidden', 'sys_language_uid'],
        ) : null;

        $recordEditUrl = null;
        if ($editUrlBuilder !== null && is_array($priceOption)) {
            $recordEditUrl = $editUrlBuilder->build(
                MenuGraphAssembler::TABLE_PRICEOPTION,
                (int)($priceOption['uid'] ?? 0),
            );
        }

        return $this->graphAssessor->assess(
            $pid,
            $selectedMenuUid,
            $priceOption,
            $placement,
            $category,
            $item,
            $menu,
            $recordEditUrl,
        );
    }

    /**
     * @param list<string> $fields
     * @return array<string, mixed>|null
     */
    private function fetchByUid(
        string $table,
        int $uid,
        BackendUserAuthentication $backendUser,
        array $fields,
    ): ?array {
        $queryBuilder = $this->createQueryBuilder($table, $backendUser);
        $row = $queryBuilder
            ->select(...$fields)
            ->from($table)
            ->where(
                $queryBuilder->expr()->eq(
                    'uid',
                    $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)
                ),
            )
            ->executeQuery()
            ->fetchAssociative();

        return is_array($row) ? $row : null;
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
