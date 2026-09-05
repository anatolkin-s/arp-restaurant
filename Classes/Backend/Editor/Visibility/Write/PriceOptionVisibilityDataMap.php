<?php

declare(strict_types=1);

namespace Anatolkin\ArpRestaurant\Backend\Editor\Visibility\Write;

use Anatolkin\ArpRestaurant\Backend\Editor\MenuGraphAssembler;
use Anatolkin\ArpRestaurant\Backend\Editor\Visibility\PriceOptionVisibilityPlan;

/**
 * Pure datamap for existing PriceOption.hidden only. No DataHandler / QueryBuilder.
 *
 * @param array<string, array<int, array{hidden: int}>> $payload
 */
final readonly class PriceOptionVisibilityDataMap
{
    /**
     * @param array<string, array<int, array{hidden: int}>> $payload
     */
    public function __construct(
        public array $payload,
    ) {}

    public static function fromPlan(PriceOptionVisibilityPlan $plan): self
    {
        if ($plan->uid <= 0) {
            throw new \InvalidArgumentException('PriceOption uid must be a positive existing uid', 1757000101);
        }

        if ($plan->requestedHidden !== 0 && $plan->requestedHidden !== 1) {
            throw new \InvalidArgumentException('PriceOption hidden must be 0 or 1', 1757000102);
        }

        return new self([
            MenuGraphAssembler::TABLE_PRICEOPTION => [
                $plan->uid => [
                    'hidden' => $plan->requestedHidden,
                ],
            ],
        ]);
    }
}
