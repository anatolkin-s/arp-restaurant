<?php

declare(strict_types=1);

namespace Anatolkin\ArpRestaurant\Backend\Editor;

use Anatolkin\ArpRestaurant\Backend\Editor\ViewModel\CategorySection;
use Anatolkin\ArpRestaurant\Backend\Editor\ViewModel\EditorScreen;
use Anatolkin\ArpRestaurant\Backend\Editor\ViewModel\MenuDetail;
use Anatolkin\ArpRestaurant\Backend\Editor\ViewModel\MenuTab;
use Anatolkin\ArpRestaurant\Backend\Editor\ViewModel\PlacementGroup;
use Anatolkin\ArpRestaurant\Backend\Editor\ViewModel\PriceOptionRow;

/**
 * Pure graph → view-model mapping. No TYPO3 runtime types.
 *
 * Each Placement remains its own UI group, even when category+item repeat.
 */
final class MenuGraphAssembler
{
    public const TABLE_MENU = 'tx_arprestaurant_domain_model_menu';
    public const TABLE_CATEGORY = 'tx_arprestaurant_domain_model_category';
    public const TABLE_ITEM = 'tx_arprestaurant_domain_model_item';
    public const TABLE_PLACEMENT = 'tx_arprestaurant_domain_model_placement';
    public const TABLE_PRICEOPTION = 'tx_arprestaurant_domain_model_priceoption';

    public function __construct(
        private readonly MinorUnitMoneyFormatter $moneyFormatter,
    ) {}

    /**
     * @param list<array<string, mixed>> $menus
     * @param list<array<string, mixed>> $categories
     * @param list<array<string, mixed>> $placements
     * @param list<array<string, mixed>> $items
     * @param list<array<string, mixed>> $priceOptions
     * @param callable(int): string|null $moduleUrlBuilder
     * @param callable(int, int): string|null $priceEditUrlBuilder optionUid, menuUid
     */
    public function assemble(
        int $pid,
        string $pageTitle,
        array $menus,
        array $categories,
        array $placements,
        array $items,
        array $priceOptions,
        int $selectedMenuUid,
        int $now,
        ?callable $moduleUrlBuilder = null,
        ?RecordEditUrlBuilder $editUrlBuilder = null,
        ?callable $priceEditUrlBuilder = null,
    ): EditorScreen {
        $menus = $this->sortRows($menus, 'title', true);
        $categories = $this->sortRows($categories, 'sorting');
        $placements = $this->sortRows($placements, 'sorting');
        $priceOptions = $this->sortRows($priceOptions, 'sorting');

        $itemsByUid = [];
        foreach ($items as $item) {
            $uid = (int)($item['uid'] ?? 0);
            if ($uid > 0) {
                $itemsByUid[$uid] = $item;
            }
        }

        $priceOptionsByPlacement = [];
        foreach ($priceOptions as $priceOption) {
            $placementUid = (int)($priceOption['placement'] ?? 0);
            $priceOptionsByPlacement[$placementUid][] = $priceOption;
        }

        $placementsByCategory = [];
        foreach ($placements as $placement) {
            $categoryUid = (int)($placement['category'] ?? 0);
            $placementsByCategory[$categoryUid][] = $placement;
        }

        $categoriesByMenu = [];
        foreach ($categories as $category) {
            $menuUid = (int)($category['menu'] ?? 0);
            $categoriesByMenu[$menuUid][] = $category;
        }

        if ($menus === []) {
            return new EditorScreen(
                pid: $pid,
                pageTitle: $pageTitle,
                canRead: true,
                emptyState: 'noMenus',
                menus: [],
                selectedMenu: null,
            );
        }

        $resolvedSelectedUid = $this->resolveSelectedMenuUid($menus, $selectedMenuUid);
        $tabs = [];
        $selectedDetail = null;

        foreach ($menus as $menu) {
            $menuUid = (int)($menu['uid'] ?? 0);
            if ($menuUid <= 0) {
                continue;
            }

            $isActive = $menuUid === $resolvedSelectedUid;
            $tabs[] = new MenuTab(
                uid: $menuUid,
                title: $this->title((string)($menu['title'] ?? ''), 'Untitled menu'),
                url: $moduleUrlBuilder !== null ? (string)$moduleUrlBuilder($menuUid) : '',
                active: $isActive,
                editUrl: $this->editUrl($editUrlBuilder, self::TABLE_MENU, $menuUid),
                statusKeys: $this->recordStatusKeys($menu, $now, false),
            );

            if (!$isActive) {
                continue;
            }

            $categorySections = [];
            foreach ($categoriesByMenu[$menuUid] ?? [] as $category) {
                $categoryUid = (int)($category['uid'] ?? 0);
                if ($categoryUid <= 0) {
                    continue;
                }

                $placementGroups = [];
                foreach ($placementsByCategory[$categoryUid] ?? [] as $placement) {
                    $placementUid = (int)($placement['uid'] ?? 0);
                    if ($placementUid <= 0) {
                        continue;
                    }

                    $itemUid = (int)($placement['item'] ?? 0);
                    $item = $itemsByUid[$itemUid] ?? null;
                    $itemTitle = $item === null
                        ? 'Unavailable item'
                        : $this->title((string)($item['title'] ?? ''), 'Untitled item');

                    $optionRows = [];
                    foreach ($priceOptionsByPlacement[$placementUid] ?? [] as $priceOption) {
                        $optionUid = (int)($priceOption['uid'] ?? 0);
                        if ($optionUid <= 0) {
                            continue;
                        }
                        $label = trim((string)($priceOption['label'] ?? ''));
                        $amountMinor = (int)($priceOption['amount'] ?? 0);
                        $optionRows[] = new PriceOptionRow(
                            uid: $optionUid,
                            label: $label,
                            displayLabel: $label === '' ? '—' : $label,
                            amountMinor: $amountMinor,
                            formattedAmount: $this->moneyFormatter->format($amountMinor),
                            hidden: (int)($priceOption['hidden'] ?? 0) === 1,
                            editUrl: $this->editUrl($editUrlBuilder, self::TABLE_PRICEOPTION, $optionUid),
                            editPriceUrl: $priceEditUrlBuilder !== null
                                ? $priceEditUrlBuilder($optionUid, $menuUid)
                                : null,
                        );
                    }

                    $placementGroups[] = new PlacementGroup(
                        uid: $placementUid,
                        itemTitle: $itemTitle,
                        itemEditUrl: $item !== null ? $this->editUrl($editUrlBuilder, self::TABLE_ITEM, $itemUid) : null,
                        editUrl: $this->editUrl($editUrlBuilder, self::TABLE_PLACEMENT, $placementUid),
                        statusKeys: $this->recordStatusKeys(
                            $placement,
                            $now,
                            $item !== null && (int)($item['hidden'] ?? 0) === 1
                        ),
                        priceOptions: $optionRows,
                    );
                }

                $categorySections[] = new CategorySection(
                    uid: $categoryUid,
                    title: $this->title((string)($category['title'] ?? ''), 'Untitled category'),
                    editUrl: $this->editUrl($editUrlBuilder, self::TABLE_CATEGORY, $categoryUid),
                    statusKeys: $this->recordStatusKeys($category, $now, false),
                    placements: $placementGroups,
                );
            }

            $selectedDetail = new MenuDetail(
                uid: $menuUid,
                title: $this->title((string)($menu['title'] ?? ''), 'Untitled menu'),
                editUrl: $this->editUrl($editUrlBuilder, self::TABLE_MENU, $menuUid),
                statusKeys: $this->recordStatusKeys($menu, $now, false),
                categories: $categorySections,
            );
        }

        return new EditorScreen(
            pid: $pid,
            pageTitle: $pageTitle,
            canRead: true,
            emptyState: '',
            menus: $tabs,
            selectedMenu: $selectedDetail,
        );
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function sortRows(array $rows, string $field, bool $asString = false): array
    {
        usort($rows, static function (array $left, array $right) use ($field, $asString): int {
            if ($asString) {
                $compare = strcasecmp((string)($left[$field] ?? ''), (string)($right[$field] ?? ''));
            } else {
                $compare = (int)($left[$field] ?? 0) <=> (int)($right[$field] ?? 0);
            }
            if ($compare !== 0) {
                return $compare;
            }

            return (int)($left['uid'] ?? 0) <=> (int)($right['uid'] ?? 0);
        });

        return array_values($rows);
    }

    /**
     * @param list<array<string, mixed>> $menus
     */
    private function resolveSelectedMenuUid(array $menus, int $selectedMenuUid): int
    {
        foreach ($menus as $menu) {
            if ((int)($menu['uid'] ?? 0) === $selectedMenuUid) {
                return $selectedMenuUid;
            }
        }

        return (int)($menus[0]['uid'] ?? 0);
    }

    /**
     * @param array<string, mixed> $record
     * @return list<string>
     */
    private function recordStatusKeys(array $record, int $now, bool $itemHidden): array
    {
        $keys = [];
        if ((int)($record['hidden'] ?? 0) === 1) {
            $keys[] = 'hidden';
        }

        $start = (int)($record['starttime'] ?? 0);
        $end = (int)($record['endtime'] ?? 0);
        $scheduled = ($start > 0 && $start > $now) || ($end > 0 && $end <= $now);
        if ($scheduled) {
            $keys[] = 'scheduled';
        }

        if ($itemHidden) {
            $keys[] = 'itemHidden';
        }

        if ($keys === []) {
            $keys[] = 'visible';
        }

        return $keys;
    }

    private function editUrl(?RecordEditUrlBuilder $editUrlBuilder, string $table, int $uid): ?string
    {
        return $editUrlBuilder?->build($table, $uid);
    }

    private function title(string $value, string $fallback): string
    {
        $trimmed = trim($value);
        return $trimmed !== '' ? $trimmed : $fallback;
    }
}
