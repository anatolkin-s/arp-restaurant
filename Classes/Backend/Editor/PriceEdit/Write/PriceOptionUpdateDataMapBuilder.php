<?php

declare(strict_types=1);

namespace Anatolkin\ArpRestaurant\Backend\Editor\PriceEdit\Write;

use Anatolkin\ArpRestaurant\Backend\Editor\MenuGraphAssembler;
use Anatolkin\ArpRestaurant\Backend\Editor\PriceEdit\PriceOptionUpdatePlan;

/**
 * Pure datamap builder for existing PriceOption label/amount update.
 * No DataHandler / QueryBuilder.
 *
 * @return array<string, array<int, array{label: string, amount: int}>>
 */
final class PriceOptionUpdateDataMapBuilder
{
    /**
     * @return array<string, array<int, array{label: string, amount: int}>>
     */
    public function build(PriceOptionUpdatePlan $plan): array
    {
        if ($plan->uid <= 0) {
            throw new \InvalidArgumentException('PriceOption uid must be a positive existing uid', 1757000001);
        }

        return [
            MenuGraphAssembler::TABLE_PRICEOPTION => [
                $plan->uid => [
                    'label' => $plan->after->label,
                    'amount' => $plan->after->amountMinor,
                ],
            ],
        ];
    }
}
