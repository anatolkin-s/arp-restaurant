<?php

declare(strict_types=1);

namespace Anatolkin\ArpRestaurant\Backend\Editor;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Type\Bitmask\Permission;

final class BackendAccessGuard
{
    /**
     * @var list<string>
     */
    public const REQUIRED_TABLES = [
        MenuGraphAssembler::TABLE_MENU,
        MenuGraphAssembler::TABLE_CATEGORY,
        MenuGraphAssembler::TABLE_ITEM,
        MenuGraphAssembler::TABLE_PLACEMENT,
        MenuGraphAssembler::TABLE_PRICEOPTION,
    ];

    /**
     * @return array<string, mixed>|null page row when the user may see this pid
     */
    public function readPage(int $pid, BackendUserAuthentication $backendUser): ?array
    {
        if ($pid <= 0) {
            return null;
        }

        $page = BackendUtility::readPageAccess(
            $pid,
            $backendUser->getPagePermsClause(Permission::PAGE_SHOW)
        );

        return is_array($page) ? $page : null;
    }

    public function canSelectRestaurantTables(BackendUserAuthentication $backendUser): bool
    {
        foreach (self::REQUIRED_TABLES as $table) {
            if (!$backendUser->check('tables_select', $table)) {
                return false;
            }
        }

        return true;
    }
}
