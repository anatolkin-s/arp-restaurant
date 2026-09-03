<?php

declare(strict_types=1);

namespace Anatolkin\ArpRestaurant\Backend\Editor\ViewModel;

final readonly class CategorySection
{
    /**
     * @param list<string> $statusKeys
     * @param list<PlacementGroup> $placements
     */
    public function __construct(
        public int $uid,
        public string $title,
        public ?string $editUrl,
        public array $statusKeys,
        public array $placements,
    ) {}
}
