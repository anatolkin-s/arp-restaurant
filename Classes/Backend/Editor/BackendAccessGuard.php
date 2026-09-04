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

    /**
     * @var array<string, list<string>>
     */
    private const REQUIRED_EXCLUDE_FIELDS = [
        MenuGraphAssembler::TABLE_CATEGORY => ['title', 'menu'],
        MenuGraphAssembler::TABLE_ITEM => ['title'],
        MenuGraphAssembler::TABLE_PLACEMENT => ['category', 'item'],
        MenuGraphAssembler::TABLE_PRICEOPTION => ['label', 'amount', 'placement'],
    ];

    /**
     * Conservative FUTURE-APPLY / real-Apply preflight.
     * Does not write. DataHandler remains final permission authority.
     *
     * @param array<string, mixed> $pageRow page row already readable via readPage()
     * @return string empty when OK; otherwise a blocker code
     */
    public function futureApplyPermissionBlocker(
        array $pageRow,
        BackendUserAuthentication $backendUser,
    ): string {
        if ((int)$backendUser->workspace !== 0) {
            return 'nonLiveWorkspace';
        }

        if (
            !$backendUser->isAdmin()
            && !$backendUser->doesUserHaveAccess($pageRow, Permission::CONTENT_EDIT)
        ) {
            return 'pageContentEditDenied';
        }

        foreach (self::REQUIRED_TABLES as $table) {
            if (!$backendUser->check('tables_modify', $table)) {
                return 'tablesModifyDenied';
            }
        }

        if (!$backendUser->isAdmin()) {
            foreach (self::REQUIRED_EXCLUDE_FIELDS as $table => $fields) {
                foreach ($fields as $field) {
                    if (!$this->canModifyField($backendUser, $table, $field)) {
                        return 'fieldModifyDenied';
                    }
                }
            }
        }

        return '';
    }

    /**
     * Conservative FUTURE PriceOption-edit preflight (label + amount only).
     * Does not write. DataHandler remains final permission authority.
     *
     * @param array<string, mixed> $pageRow page row already readable via readPage()
     * @return string empty when OK; otherwise a blocker code
     */
    public function priceOptionEditPermissionBlocker(
        array $pageRow,
        BackendUserAuthentication $backendUser,
    ): string {
        if ((int)$backendUser->workspace !== 0) {
            return 'nonLiveWorkspace';
        }

        if (
            !$backendUser->isAdmin()
            && !$backendUser->doesUserHaveAccess($pageRow, Permission::CONTENT_EDIT)
        ) {
            return 'pageContentEditDenied';
        }

        if (!$backendUser->check('tables_modify', MenuGraphAssembler::TABLE_PRICEOPTION)) {
            return 'tablesModifyDenied';
        }

        if (!$backendUser->isAdmin()) {
            foreach (['label', 'amount'] as $field) {
                if (!$this->canModifyField($backendUser, MenuGraphAssembler::TABLE_PRICEOPTION, $field)) {
                    return 'fieldModifyDenied';
                }
            }
        }

        return '';
    }

    private function canModifyField(
        BackendUserAuthentication $backendUser,
        string $table,
        string $field,
    ): bool {
        $column = $GLOBALS['TCA'][$table]['columns'][$field] ?? null;
        if (!is_array($column)) {
            return false;
        }
        if (empty($column['exclude'])) {
            return true;
        }

        return $backendUser->check('non_exclude_fields', $table . ':' . $field);
    }
}
