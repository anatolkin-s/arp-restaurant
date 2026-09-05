<?php

declare(strict_types=1);

namespace Anatolkin\ArpRestaurant\Backend\Editor\PriceCreate;

/**
 * Pure graph-membership assessment for adding a PriceOption under an existing Placement.
 * Proves: selected pid → Menu → Category → Placement → Item.
 * A Placement uid alone is not authority. No database access.
 */
final class PriceOptionCreateGraphAssessor
{
    private const UUID_PATTERN = '/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/';

    /**
     * @param array<string, mixed>|null $placement
     * @param array<string, mixed>|null $category
     * @param array<string, mixed>|null $item
     * @param array<string, mixed>|null $menu
     * @param list<array<string, mixed>> $priceOptions
     */
    public function assess(
        int $pid,
        int $selectedMenuUid,
        ?array $placement,
        ?array $category,
        ?array $item,
        ?array $menu,
        array $priceOptions,
        ?string $recordEditUrl = null,
    ): PriceOptionCreateLoadResult {
        if ($pid <= 0 || $selectedMenuUid <= 0) {
            return $this->blocked('inaccessiblePlacement');
        }

        if ($placement === null) {
            return $this->blocked('inaccessiblePlacement');
        }

        $placementUid = (int)($placement['uid'] ?? 0);
        if ($placementUid <= 0) {
            return $this->blocked('inaccessiblePlacement');
        }

        if ((int)($placement['sys_language_uid'] ?? -1) !== 0) {
            return $this->blocked('translatedPlacement');
        }

        if ((int)($placement['pid'] ?? -1) !== $pid) {
            return $this->blocked('wrongPid');
        }

        if (!$this->validUuid($placement['public_uuid'] ?? '')) {
            return $this->blocked('missingPublicUuid');
        }

        if ($category === null) {
            return $this->blocked('brokenCategory');
        }

        $categoryUid = (int)($category['uid'] ?? 0);
        $placementCategory = (int)($placement['category'] ?? 0);
        if (
            $categoryUid <= 0
            || $placementCategory !== $categoryUid
            || (int)($category['pid'] ?? -1) !== $pid
        ) {
            return $this->blocked('brokenCategory');
        }

        if ((int)($category['sys_language_uid'] ?? -1) !== 0) {
            return $this->blocked('translatedCategory');
        }

        if (!$this->validUuid($category['public_uuid'] ?? '')) {
            return $this->blocked('missingPublicUuid');
        }

        if ($menu === null) {
            return $this->blocked('wrongMenu');
        }

        $menuUid = (int)($menu['uid'] ?? 0);
        $categoryMenu = (int)($category['menu'] ?? 0);
        if (
            $menuUid <= 0
            || $categoryMenu !== $menuUid
            || $menuUid !== $selectedMenuUid
            || (int)($menu['pid'] ?? -1) !== $pid
        ) {
            return $this->blocked('wrongMenu');
        }

        if ((int)($menu['sys_language_uid'] ?? -1) !== 0) {
            return $this->blocked('translatedMenu');
        }

        if (!$this->validUuid($menu['public_uuid'] ?? '')) {
            return $this->blocked('missingPublicUuid');
        }

        if ($item === null) {
            return $this->blocked('missingItem');
        }

        $itemUid = (int)($item['uid'] ?? 0);
        $placementItem = (int)($placement['item'] ?? 0);
        if (
            $itemUid <= 0
            || $placementItem !== $itemUid
            || (int)($item['pid'] ?? -1) !== $pid
        ) {
            return $this->blocked('missingItem');
        }

        if ((int)($item['sys_language_uid'] ?? -1) !== 0) {
            return $this->blocked('translatedItem');
        }

        if (!$this->validUuid($item['public_uuid'] ?? '')) {
            return $this->blocked('missingPublicUuid');
        }

        $existing = [];
        foreach ($priceOptions as $priceOption) {
            if (!is_array($priceOption)) {
                continue;
            }
            if ((int)($priceOption['sys_language_uid'] ?? 0) !== 0) {
                continue;
            }
            if ((int)($priceOption['placement'] ?? 0) !== $placementUid) {
                continue;
            }
            if ((int)($priceOption['pid'] ?? -1) !== $pid) {
                continue;
            }
            $optionUid = (int)($priceOption['uid'] ?? 0);
            if ($optionUid <= 0) {
                continue;
            }
            if (!$this->validUuid($priceOption['public_uuid'] ?? '')) {
                return $this->blocked('missingPublicUuid');
            }
            $existing[] = new ExistingPriceOptionSnapshot(
                uid: $optionUid,
                publicUuid: trim((string)$priceOption['public_uuid']),
                tstamp: (int)($priceOption['tstamp'] ?? 0),
                label: (string)($priceOption['label'] ?? ''),
                amountMinor: (int)($priceOption['amount'] ?? 0),
                sorting: (int)($priceOption['sorting'] ?? 0),
                hidden: (int)($priceOption['hidden'] ?? 0) === 1 ? 1 : 0,
            );
        }

        return new PriceOptionCreateLoadResult(
            outcome: 'loaded',
            context: new PriceOptionCreateContext(
                pid: $pid,
                menuUid: $menuUid,
                menuPublicUuid: trim((string)$menu['public_uuid']),
                menuTstamp: (int)($menu['tstamp'] ?? 0),
                menuTitle: trim((string)($menu['title'] ?? '')),
                categoryUid: $categoryUid,
                categoryPublicUuid: trim((string)$category['public_uuid']),
                categoryTstamp: (int)($category['tstamp'] ?? 0),
                categoryTitle: trim((string)($category['title'] ?? '')),
                placementUid: $placementUid,
                placementPublicUuid: trim((string)$placement['public_uuid']),
                placementTstamp: (int)($placement['tstamp'] ?? 0),
                placementCategoryUid: $categoryUid,
                placementItemUid: $itemUid,
                placementSorting: (int)($placement['sorting'] ?? 0),
                placementHidden: (int)($placement['hidden'] ?? 0) === 1 ? 1 : 0,
                placementStarttime: (int)($placement['starttime'] ?? 0),
                placementEndtime: (int)($placement['endtime'] ?? 0),
                itemUid: $itemUid,
                itemPublicUuid: trim((string)$item['public_uuid']),
                itemTstamp: (int)($item['tstamp'] ?? 0),
                itemTitle: trim((string)($item['title'] ?? '')),
                itemHidden: (int)($item['hidden'] ?? 0) === 1 ? 1 : 0,
                itemStarttime: (int)($item['starttime'] ?? 0),
                itemEndtime: (int)($item['endtime'] ?? 0),
                existingPriceOptions: $existing,
                recordEditUrl: $recordEditUrl,
            ),
            blockers: [],
        );
    }

    private function validUuid(mixed $value): bool
    {
        $uuid = trim((string)$value);

        return $uuid !== '' && preg_match(self::UUID_PATTERN, $uuid) === 1;
    }

    private function blocked(string $code): PriceOptionCreateLoadResult
    {
        return new PriceOptionCreateLoadResult(
            outcome: 'blocked',
            context: null,
            blockers: [new PriceOptionCreateBlocker($code)],
        );
    }
}
