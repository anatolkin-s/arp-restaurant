<?php

declare(strict_types=1);

namespace Anatolkin\ArpRestaurant\Backend\Editor\PriceEdit;

use Anatolkin\ArpRestaurant\Backend\Editor\MinorUnitMoneyFormatter;

/**
 * Pure graph-membership assessment for an existing PriceOption edit context.
 * No database access.
 */
final class PriceOptionEditGraphAssessor
{
    private const UUID_PATTERN = '/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/';

    public function __construct(
        private readonly MinorUnitMoneyFormatter $moneyFormatter = new MinorUnitMoneyFormatter(2),
    ) {}

    /**
     * @param array<string, mixed>|null $priceOption
     * @param array<string, mixed>|null $placement
     * @param array<string, mixed>|null $category
     * @param array<string, mixed>|null $item
     * @param array<string, mixed>|null $menu
     */
    public function assess(
        int $pid,
        int $selectedMenuUid,
        ?array $priceOption,
        ?array $placement,
        ?array $category,
        ?array $item,
        ?array $menu,
        ?string $recordEditUrl = null,
    ): PriceOptionEditLoadResult {
        if ($pid <= 0 || $selectedMenuUid <= 0) {
            return $this->blocked('missingPriceOption');
        }

        if ($priceOption === null) {
            return $this->blocked('missingPriceOption');
        }

        $optionUid = (int)($priceOption['uid'] ?? 0);
        if ($optionUid <= 0) {
            return $this->blocked('missingPriceOption');
        }

        if ((int)($priceOption['sys_language_uid'] ?? -1) !== 0) {
            return $this->blocked('translatedPriceOption');
        }

        if ((int)($priceOption['pid'] ?? -1) !== $pid) {
            return $this->blocked('wrongPid');
        }

        $publicUuid = trim((string)($priceOption['public_uuid'] ?? ''));
        if ($publicUuid === '' || preg_match(self::UUID_PATTERN, $publicUuid) !== 1) {
            return $this->blocked('missingPublicUuid');
        }

        if ($placement === null) {
            return $this->blocked('brokenPlacement');
        }

        $placementUid = (int)($placement['uid'] ?? 0);
        $optionPlacement = (int)($priceOption['placement'] ?? 0);
        if (
            $placementUid <= 0
            || $optionPlacement !== $placementUid
            || (int)($placement['pid'] ?? -1) !== $pid
            || (int)($placement['sys_language_uid'] ?? -1) !== 0
        ) {
            return $this->blocked('brokenPlacement');
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
            || (int)($category['sys_language_uid'] ?? -1) !== 0
        ) {
            return $this->blocked('brokenCategory');
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
            || (int)($menu['sys_language_uid'] ?? -1) !== 0
        ) {
            return $this->blocked('wrongMenu');
        }

        if ($item === null) {
            return $this->blocked('brokenPlacement');
        }

        $itemUid = (int)($item['uid'] ?? 0);
        $placementItem = (int)($placement['item'] ?? 0);
        if (
            $itemUid <= 0
            || $placementItem !== $itemUid
            || (int)($item['pid'] ?? -1) !== $pid
            || (int)($item['sys_language_uid'] ?? -1) !== 0
        ) {
            return $this->blocked('brokenPlacement');
        }

        $label = trim((string)($priceOption['label'] ?? ''));
        $amountMinor = (int)($priceOption['amount'] ?? 0);

        return new PriceOptionEditLoadResult(
            outcome: 'loaded',
            context: new PriceOptionEditContext(
                uid: $optionUid,
                pid: $pid,
                publicUuid: $publicUuid,
                tstamp: (int)($priceOption['tstamp'] ?? 0),
                label: $label,
                amountMinor: $amountMinor,
                formattedAmount: $this->moneyFormatter->format($amountMinor),
                placementUid: $placementUid,
                sorting: (int)($priceOption['sorting'] ?? 0),
                menuUid: $menuUid,
                menuTitle: trim((string)($menu['title'] ?? '')),
                categoryUid: $categoryUid,
                categoryTitle: trim((string)($category['title'] ?? '')),
                itemUid: $itemUid,
                itemTitle: trim((string)($item['title'] ?? '')),
                recordEditUrl: $recordEditUrl,
            ),
            blockers: [],
        );
    }

    private function blocked(string $code): PriceOptionEditLoadResult
    {
        return new PriceOptionEditLoadResult(
            outcome: 'blocked',
            context: null,
            blockers: [new PriceOptionEditBlocker($code)],
        );
    }
}
