<?php

declare(strict_types=1);

namespace Anatolkin\ArpRestaurant\Backend\Editor;

use Anatolkin\ArpRestaurant\Backend\Editor\ViewModel\EditorScreen;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\WorkspaceRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Loads default-language restaurant rows for one storage pid.
 * Hidden and scheduled records are included; deleted rows are not.
 * Item rows are limited to the same selected pid as the rest of the graph.
 */
final class MenuGraphReader
{
    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly MenuGraphAssembler $assembler,
    ) {}

    /**
     * @param callable(int): string $moduleUrlBuilder
     * @param callable(int, int): string|null $priceEditUrlBuilder optionUid, menuUid
     * @param callable(int, int): string|null $priceVisibilityUrlBuilder optionUid, menuUid
     * @param callable(int, int): string|null $priceOptionCreateUrlBuilder placementUid, menuUid
     */
    public function load(
        int $pid,
        string $pageTitle,
        int $selectedMenuUid,
        int $now,
        BackendUserAuthentication $backendUser,
        callable $moduleUrlBuilder,
        RecordEditUrlBuilder $editUrlBuilder,
        ?callable $priceEditUrlBuilder = null,
        ?callable $priceVisibilityUrlBuilder = null,
        ?callable $priceOptionCreateUrlBuilder = null,
    ): EditorScreen {
        $menus = $this->fetchTable(
            MenuGraphAssembler::TABLE_MENU,
            $pid,
            $backendUser,
            ['uid', 'title', 'hidden', 'starttime', 'endtime'],
        );
        $menuUids = $this->uids($menus);

        $categories = $menuUids === [] ? [] : $this->fetchChildren(
            MenuGraphAssembler::TABLE_CATEGORY,
            $pid,
            $backendUser,
            ['uid', 'title', 'menu', 'sorting', 'hidden'],
            'menu',
            $menuUids,
        );
        $categoryUids = $this->uids($categories);

        $placements = $categoryUids === [] ? [] : $this->fetchChildren(
            MenuGraphAssembler::TABLE_PLACEMENT,
            $pid,
            $backendUser,
            ['uid', 'category', 'item', 'sorting', 'hidden', 'starttime', 'endtime'],
            'category',
            $categoryUids,
        );
        $placementUids = $this->uids($placements);
        $itemUids = [];
        foreach ($placements as $placement) {
            $itemUid = (int)($placement['item'] ?? 0);
            if ($itemUid > 0) {
                $itemUids[$itemUid] = $itemUid;
            }
        }

        $items = $itemUids === [] ? [] : $this->fetchByUids(
            MenuGraphAssembler::TABLE_ITEM,
            $pid,
            $backendUser,
            ['uid', 'title', 'hidden'],
            array_values($itemUids),
        );

        $priceOptions = $placementUids === [] ? [] : $this->fetchChildren(
            MenuGraphAssembler::TABLE_PRICEOPTION,
            $pid,
            $backendUser,
            ['uid', 'placement', 'label', 'amount', 'sorting', 'hidden'],
            'placement',
            $placementUids,
        );

        return $this->assembler->assemble(
            $pid,
            $pageTitle,
            $menus,
            $categories,
            $placements,
            $items,
            $priceOptions,
            $selectedMenuUid,
            $now,
            $moduleUrlBuilder,
            $editUrlBuilder,
            $priceEditUrlBuilder,
            $priceVisibilityUrlBuilder,
            $priceOptionCreateUrlBuilder,
        );
    }

    /**
     * @param list<string> $fields
     * @return list<array<string, mixed>>
     */
    private function fetchTable(
        string $table,
        int $pid,
        BackendUserAuthentication $backendUser,
        array $fields,
    ): array {
        $queryBuilder = $this->createQueryBuilder($table, $backendUser);
        $rows = $queryBuilder
            ->select(...$fields)
            ->from($table)
            ->where(
                $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($pid, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
            )
            ->executeQuery()
            ->fetchAllAssociative();

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param list<string> $fields
     * @param list<int> $parentUids
     * @return list<array<string, mixed>>
     */
    private function fetchChildren(
        string $table,
        int $pid,
        BackendUserAuthentication $backendUser,
        array $fields,
        string $parentField,
        array $parentUids,
    ): array {
        $queryBuilder = $this->createQueryBuilder($table, $backendUser);
        $rows = $queryBuilder
            ->select(...$fields)
            ->from($table)
            ->where(
                $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($pid, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
                $queryBuilder->expr()->in(
                    $parentField,
                    $queryBuilder->createNamedParameter($parentUids, Connection::PARAM_INT_ARRAY)
                ),
            )
            ->executeQuery()
            ->fetchAllAssociative();

        return is_array($rows) ? $rows : [];
    }

    /**
     * Items are loaded only from the selected pid. Cross-pid reuse is out of
     * scope until a later task can check the Item's source-page ACL.
     *
     * @param list<string> $fields
     * @param list<int> $uids
     * @return list<array<string, mixed>>
     */
    private function fetchByUids(
        string $table,
        int $pid,
        BackendUserAuthentication $backendUser,
        array $fields,
        array $uids,
    ): array {
        $queryBuilder = $this->createQueryBuilder($table, $backendUser);
        $rows = $queryBuilder
            ->select(...$fields)
            ->from($table)
            ->where(
                $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($pid, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
                $queryBuilder->expr()->in(
                    'uid',
                    $queryBuilder->createNamedParameter($uids, Connection::PARAM_INT_ARRAY)
                ),
            )
            ->executeQuery()
            ->fetchAllAssociative();

        return is_array($rows) ? $rows : [];
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

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<int>
     */
    private function uids(array $rows): array
    {
        $uids = [];
        foreach ($rows as $row) {
            $uid = (int)($row['uid'] ?? 0);
            if ($uid > 0) {
                $uids[] = $uid;
            }
        }

        return $uids;
    }
}
