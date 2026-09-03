<?php

declare(strict_types=1);

namespace Anatolkin\ArpRestaurant\Backend\Editor\ViewModel;

final readonly class MenuDetail
{
    /**
     * @param list<string> $statusKeys
     * @param list<CategorySection> $categories
     */
    public function __construct(
        public int $uid,
        public string $title,
        public ?string $editUrl,
        public array $statusKeys,
        public array $categories,
    ) {}
}
