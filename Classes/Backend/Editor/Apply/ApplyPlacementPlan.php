<?php

declare(strict_types=1);

namespace Anatolkin\ArpRestaurant\Backend\Editor\Apply;

/**
 * One future append Placement with one or more PriceOptions.
 *
 * Display title / status fields are confirmation projection only — not
 * alternate grouping authority (BulkDraftRunGrouping remains authoritative).
 *
 * @param list<ApplyPriceOptionPlan> $priceOptions
 */
final readonly class ApplyPlacementPlan
{
    public function __construct(
        public string $localRef,
        public string $categoryLocalRef,
        public string $itemLocalRef,
        public int $startOriginalOrder,
        public array $priceOptions,
        public string $categoryDisplayTitle = '',
        public string $itemDisplayTitle = '',
        public string $categoryStatus = '',
        public string $itemStatus = '',
    ) {}
}
