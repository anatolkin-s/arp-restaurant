<?php

declare(strict_types=1);

namespace Anatolkin\ArpRestaurant\Backend\Editor\Apply\Write;

use Anatolkin\ArpRestaurant\Backend\Editor\Apply\ApplyPlan;
use Anatolkin\ArpRestaurant\Backend\Editor\MenuGraphAssembler;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\WorkspaceRestriction;

/**
 * SELECT-only max sorting positions for append Apply.
 */
final class RestaurantApplySortPositionReader
{
    public function __construct(
        private readonly ConnectionPool $connectionPool,
    ) {}

    /**
     * @throws \RuntimeException on overflow / impossible context
     */
    public function buildContext(
        ApplyPlan $plan,
        int $pid,
        BackendUserAuthentication $backendUser,
        int $step = ApplySortContext::DEFAULT_STEP,
    ): ApplySortContext {
        if ($pid <= 0 || $step <= 0) {
            throw new \RuntimeException('Invalid sort context inputs', 1757000200);
        }

        $menuUid = $plan->targetMenu->uid;
        $categoryMax = $this->maxCategorySorting($pid, $menuUid, $backendUser);
        $categoryNext = $this->nextAfterMax($categoryMax, $step);

        $placementNextByReuse = [];
        foreach ($plan->categories as $category) {
            if ($category->status !== 'reuse' || $category->uid === null) {
                continue;
            }
            $needsPlacement = false;
            foreach ($plan->placements as $placement) {
                if ($placement->categoryLocalRef === $category->localRef) {
                    $needsPlacement = true;
                    break;
                }
            }
            if (!$needsPlacement) {
                continue;
            }
            $placementMax = $this->maxPlacementSorting($pid, $category->uid, $backendUser);
            $placementNextByReuse[$category->uid] = $this->nextAfterMax($placementMax, $step);
        }

        return new ApplySortContext(
            categoryNextSorting: $categoryNext,
            placementNextByReusedCategoryUid: $placementNextByReuse,
            step: $step,
            newCategoryPlacementBase: $step,
        );
    }

    private function maxCategorySorting(int $pid, int $menuUid, BackendUserAuthentication $backendUser): int
    {
        $qb = $this->connectionPool->getQueryBuilderForTable(MenuGraphAssembler::TABLE_CATEGORY);
        $qb->getRestrictions()->removeAll();
        $qb->getRestrictions()->add(new DeletedRestriction());
        $qb->getRestrictions()->add(new WorkspaceRestriction($backendUser->workspace));

        $value = $qb
            ->addSelectLiteral('MAX(' . $qb->quoteIdentifier('sorting') . ') AS ' . $qb->quoteIdentifier('max_sorting'))
            ->from(MenuGraphAssembler::TABLE_CATEGORY)
            ->where(
                $qb->expr()->eq('pid', $qb->createNamedParameter($pid, Connection::PARAM_INT)),
                $qb->expr()->eq('menu', $qb->createNamedParameter($menuUid, Connection::PARAM_INT)),
                $qb->expr()->eq('sys_language_uid', $qb->createNamedParameter(0, Connection::PARAM_INT)),
            )
            ->executeQuery()
            ->fetchOne();

        return $value === false || $value === null ? 0 : (int)$value;
    }

    private function maxPlacementSorting(int $pid, int $categoryUid, BackendUserAuthentication $backendUser): int
    {
        $qb = $this->connectionPool->getQueryBuilderForTable(MenuGraphAssembler::TABLE_PLACEMENT);
        $qb->getRestrictions()->removeAll();
        $qb->getRestrictions()->add(new DeletedRestriction());
        $qb->getRestrictions()->add(new WorkspaceRestriction($backendUser->workspace));

        $value = $qb
            ->addSelectLiteral('MAX(' . $qb->quoteIdentifier('sorting') . ') AS ' . $qb->quoteIdentifier('max_sorting'))
            ->from(MenuGraphAssembler::TABLE_PLACEMENT)
            ->where(
                $qb->expr()->eq('pid', $qb->createNamedParameter($pid, Connection::PARAM_INT)),
                $qb->expr()->eq('category', $qb->createNamedParameter($categoryUid, Connection::PARAM_INT)),
                $qb->expr()->eq('sys_language_uid', $qb->createNamedParameter(0, Connection::PARAM_INT)),
            )
            ->executeQuery()
            ->fetchOne();

        return $value === false || $value === null ? 0 : (int)$value;
    }

    private function nextAfterMax(int $max, int $step): int
    {
        if ($max < 0) {
            throw new \RuntimeException(ApplyDataMapBuilder::SORT_OVERFLOW, 1757000201);
        }
        if ($max > PHP_INT_MAX - $step) {
            throw new \RuntimeException(ApplyDataMapBuilder::SORT_OVERFLOW, 1757000202);
        }

        return $max + $step;
    }
}
