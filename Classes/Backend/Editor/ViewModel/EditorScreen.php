<?php

declare(strict_types=1);

namespace Anatolkin\ArpRestaurant\Backend\Editor\ViewModel;

final readonly class EditorScreen
{
    /**
     * @param list<MenuTab> $menus
     */
    public function __construct(
        public int $pid,
        public string $pageTitle,
        public bool $canRead,
        public string $emptyState,
        public array $menus,
        public ?MenuDetail $selectedMenu,
    ) {}
}
