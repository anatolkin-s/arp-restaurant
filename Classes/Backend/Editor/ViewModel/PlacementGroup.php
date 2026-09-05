<?php

declare(strict_types=1);

namespace Anatolkin\ArpRestaurant\Backend\Editor\ViewModel;

final readonly class PlacementGroup
{
    /**
     * @param list<string> $statusKeys
     * @param list<PriceOptionRow> $priceOptions
     */
    public function __construct(
        public int $uid,
        public string $itemTitle,
        public ?string $itemEditUrl,
        public ?string $editUrl,
        public array $statusKeys,
        public array $priceOptions,
        public ?string $addPriceOptionUrl = null,
    ) {}
}
