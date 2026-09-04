<?php

declare(strict_types=1);

namespace Anatolkin\ArpRestaurant\Backend\Editor\Visibility;

/**
 * Pure permission decision for PriceOption.hidden review. No persistence.
 */
final class PriceOptionVisibilityPermissionPreflight
{
    /**
     * @return string empty when OK; otherwise a blocker code
     */
    public function blocker(
        int $workspace,
        bool $isAdmin,
        bool $hasContentEdit,
        bool $canModifyPriceOptionTable,
        bool $canModifyHiddenField,
    ): string {
        if ($workspace !== 0) {
            return 'nonLiveWorkspace';
        }

        if (!$isAdmin && !$hasContentEdit) {
            return 'pageContentEditDenied';
        }

        if (!$canModifyPriceOptionTable) {
            return 'tablesModifyDenied';
        }

        if (!$isAdmin && !$canModifyHiddenField) {
            return 'fieldModifyDenied';
        }

        return '';
    }
}
