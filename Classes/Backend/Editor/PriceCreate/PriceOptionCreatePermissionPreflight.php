<?php

declare(strict_types=1);

namespace Anatolkin\ArpRestaurant\Backend\Editor\PriceCreate;

/**
 * Pure permission decision for reviewing creation of one PriceOption.
 * Does not require Menu / Category / Item / Placement tables_modify.
 * Does not require PriceOption.hidden.
 */
final class PriceOptionCreatePermissionPreflight
{
    /**
     * @return string empty when OK; otherwise a blocker code
     */
    public function blocker(
        int $workspace,
        bool $isAdmin,
        bool $hasContentEdit,
        bool $canModifyPriceOptionTable,
        bool $canModifyLabelField,
        bool $canModifyAmountField,
        bool $canModifyPlacementField,
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

        if (
            !$isAdmin
            && (!$canModifyLabelField || !$canModifyAmountField || !$canModifyPlacementField)
        ) {
            return 'fieldModifyDenied';
        }

        return '';
    }
}
