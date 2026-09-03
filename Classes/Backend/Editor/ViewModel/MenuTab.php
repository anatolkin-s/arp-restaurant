<?php

declare(strict_types=1);

namespace Anatolkin\ArpRestaurant\Backend\Editor\ViewModel;

final readonly class MenuTab
{
    /**
     * @param list<string> $statusKeys
     */
    public function __construct(
        public int $uid,
        public string $title,
        public string $url,
        public bool $active,
        public ?string $editUrl,
        public array $statusKeys,
    ) {}
}
