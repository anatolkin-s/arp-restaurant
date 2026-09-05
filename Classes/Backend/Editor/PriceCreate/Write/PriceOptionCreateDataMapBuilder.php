<?php

declare(strict_types=1);

namespace Anatolkin\ArpRestaurant\Backend\Editor\PriceCreate\Write;

use Anatolkin\ArpRestaurant\Backend\Editor\MenuGraphAssembler;
use Anatolkin\ArpRestaurant\Backend\Editor\PriceCreate\PriceOptionCreatePlan;
use Anatolkin\ArpRestaurant\Backend\Editor\RestaurantTitleNormalizer;

/** Pure, exact six-field create map. Identity and enable fields belong to Core. */
final class PriceOptionCreateDataMapBuilder
{
    public function build(PriceOptionCreatePlan $plan): PriceOptionCreateDataMap
    {
        $length = preg_match_all('/./us', $plan->label);
        if ($plan->pid <= 0 || $plan->placementUid <= 0
            || $plan->plannedSorting < -2147483648 || $plan->plannedSorting > 2147483647
            || $plan->amountMinor < 0 || $plan->amountMinor > 99999999999
            || $length === false || $length > 255
            || (new RestaurantTitleNormalizer())->cleanDisplayTitle($plan->label) !== $plan->label
        ) {
            throw new \InvalidArgumentException('Invalid create plan');
        }
        $keys = [];
        $maxSorting = null;
        $normalizer = new RestaurantTitleNormalizer();
        foreach ($plan->existingPriceOptions as $row) {
            $key = $normalizer->matchKey($row->label);
            if ($key === '' || in_array($key, $keys, true) || $key === $normalizer->matchKey($plan->label)) {
                throw new \InvalidArgumentException('Invalid variant set');
            }
            $keys[] = $key;
            $maxSorting = $maxSorting === null ? $row->sorting : max($maxSorting, $row->sorting);
        }
        if (($keys !== [] && $plan->label === '')
            || ($maxSorting !== null && $maxSorting > PHP_INT_MAX - 256)
            || $plan->plannedSorting !== ($maxSorting === null ? 256 : $maxSorting + 256)
        ) {
            throw new \InvalidArgumentException('Invalid planned sorting or label');
        }
        $newToken = 'NEWarpPriceCreate' . substr($plan->fingerprint, 0, 24);

        return new PriceOptionCreateDataMap($newToken, [MenuGraphAssembler::TABLE_PRICEOPTION => [$newToken => [
            'pid' => $plan->pid,
            'placement' => $plan->placementUid,
            'label' => $plan->label,
            'amount' => $plan->amountMinor,
            'sorting' => $plan->plannedSorting,
            'sys_language_uid' => 0,
        ]]]);
    }
}
