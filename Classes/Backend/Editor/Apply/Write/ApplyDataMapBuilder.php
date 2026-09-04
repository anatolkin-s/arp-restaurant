<?php

declare(strict_types=1);

namespace Anatolkin\ArpRestaurant\Backend\Editor\Apply\Write;

use Anatolkin\ArpRestaurant\Backend\Editor\Apply\ApplyEntityReference;
use Anatolkin\ArpRestaurant\Backend\Editor\Apply\ApplyPlan;
use Anatolkin\ArpRestaurant\Backend\Editor\MenuGraphAssembler;

/**
 * Pure DataHandler datamap construction from a fresh ApplyPlan.
 * No QueryBuilder, DataHandler, or persistence.
 */
final class ApplyDataMapBuilder
{
    public const SORT_OVERFLOW = 'sortOverflow';

    /**
     * @throws \InvalidArgumentException on impossible sorting context
     */
    public function build(ApplyPlan $plan, int $pid, ApplySortContext $sortContext): ApplyDataMap
    {
        if ($pid <= 0) {
            throw new \InvalidArgumentException('Apply pid must be positive', 1757000100);
        }
        if ($sortContext->step <= 0) {
            throw new \InvalidArgumentException('Sorting step must be positive', 1757000101);
        }

        $dataMap = [
            MenuGraphAssembler::TABLE_ITEM => [],
            MenuGraphAssembler::TABLE_CATEGORY => [],
            MenuGraphAssembler::TABLE_PLACEMENT => [],
            MenuGraphAssembler::TABLE_PRICEOPTION => [],
        ];
        $localRefToNewToken = [];
        $expectedCreates = [];

        $itemTokens = [];
        foreach ($plan->items as $item) {
            if ($item->status === 'create') {
                $token = $this->newToken('I', $item->localRef);
                $this->assertUniqueToken($localRefToNewToken, $item->localRef, $token);
                $localRefToNewToken[$item->localRef] = $token;
                $itemTokens[$item->localRef] = $token;
                $fields = [
                    'pid' => $pid,
                    'title' => $item->displayTitle,
                    'sys_language_uid' => 0,
                ];
                $dataMap[MenuGraphAssembler::TABLE_ITEM][$token] = $fields;
                $expectedCreates[] = new ApplyExpectedCreate(
                    table: MenuGraphAssembler::TABLE_ITEM,
                    newToken: $token,
                    localRef: $item->localRef,
                    entityKind: 'item',
                    expectedFields: $fields,
                );
            } elseif ($item->status === 'reuse') {
                if ($item->uid === null || $item->uid <= 0) {
                    throw new \InvalidArgumentException('REUSE Item missing uid', 1757000102);
                }
            } else {
                throw new \InvalidArgumentException('Unsupported Item status', 1757000103);
            }
        }

        $categoryTokens = [];
        $categoryNext = $sortContext->categoryNextSorting;
        foreach ($plan->categories as $category) {
            if ($category->status === 'create') {
                $token = $this->newToken('C', $category->localRef);
                $this->assertUniqueToken($localRefToNewToken, $category->localRef, $token);
                $localRefToNewToken[$category->localRef] = $token;
                $categoryTokens[$category->localRef] = $token;
                $sorting = $this->allocateSorting($categoryNext, $sortContext->step);
                $categoryNext = $sorting + $sortContext->step;
                $fields = [
                    'pid' => $pid,
                    'title' => $category->displayTitle,
                    'menu' => $plan->targetMenu->uid,
                    'sorting' => $sorting,
                    'sys_language_uid' => 0,
                ];
                $dataMap[MenuGraphAssembler::TABLE_CATEGORY][$token] = $fields;
                $expectedCreates[] = new ApplyExpectedCreate(
                    table: MenuGraphAssembler::TABLE_CATEGORY,
                    newToken: $token,
                    localRef: $category->localRef,
                    entityKind: 'category',
                    expectedFields: $fields,
                );
            } elseif ($category->status === 'reuse') {
                if ($category->uid === null || $category->uid <= 0) {
                    throw new \InvalidArgumentException('REUSE Category missing uid', 1757000104);
                }
            } else {
                throw new \InvalidArgumentException('Unsupported Category status', 1757000105);
            }
        }

        $entitiesByRef = [];
        foreach ($plan->categories as $category) {
            $entitiesByRef[$category->localRef] = $category;
        }
        foreach ($plan->items as $item) {
            $entitiesByRef[$item->localRef] = $item;
        }

        $placementNextByReuse = $sortContext->placementNextByReusedCategoryUid;
        $placementNextByCreateToken = [];

        foreach ($plan->placements as $placement) {
            $category = $entitiesByRef[$placement->categoryLocalRef] ?? null;
            $item = $entitiesByRef[$placement->itemLocalRef] ?? null;
            if (!$category instanceof ApplyEntityReference || !$item instanceof ApplyEntityReference) {
                throw new \InvalidArgumentException('Placement missing entity refs', 1757000106);
            }

            $categoryValue = $this->relationValue($category, $categoryTokens);
            $itemValue = $this->relationValue($item, $itemTokens);

            if ($category->status === 'reuse') {
                $reuseUid = (int)$category->uid;
                $next = $placementNextByReuse[$reuseUid] ?? $sortContext->newCategoryPlacementBase;
                $sorting = $this->allocateSorting($next, $sortContext->step);
                $placementNextByReuse[$reuseUid] = $sorting + $sortContext->step;
            } else {
                $catToken = $categoryTokens[$category->localRef];
                $next = $placementNextByCreateToken[$catToken] ?? $sortContext->newCategoryPlacementBase;
                $sorting = $this->allocateSorting($next, $sortContext->step);
                $placementNextByCreateToken[$catToken] = $sorting + $sortContext->step;
            }

            $placementToken = $this->newToken('P', $placement->localRef);
            $this->assertUniqueToken($localRefToNewToken, $placement->localRef, $placementToken);
            $localRefToNewToken[$placement->localRef] = $placementToken;

            $placementFields = [
                'pid' => $pid,
                'category' => $categoryValue,
                'item' => $itemValue,
                'sorting' => $sorting,
                'sys_language_uid' => 0,
            ];
            $dataMap[MenuGraphAssembler::TABLE_PLACEMENT][$placementToken] = $placementFields;
            $expectedCreates[] = new ApplyExpectedCreate(
                table: MenuGraphAssembler::TABLE_PLACEMENT,
                newToken: $placementToken,
                localRef: $placement->localRef,
                entityKind: 'placement',
                expectedFields: $placementFields,
            );

            $priceSorting = $sortContext->newCategoryPlacementBase;
            foreach ($placement->priceOptions as $priceOption) {
                $priceToken = $this->newToken('O', $priceOption->localRef);
                $this->assertUniqueToken($localRefToNewToken, $priceOption->localRef, $priceToken);
                $localRefToNewToken[$priceOption->localRef] = $priceToken;
                $priceSortValue = $this->allocateSorting($priceSorting, $sortContext->step);
                $priceSorting = $priceSortValue + $sortContext->step;

                $priceFields = [
                    'pid' => $pid,
                    'placement' => $placementToken,
                    'label' => $priceOption->label,
                    'amount' => $priceOption->amountMinor,
                    'sorting' => $priceSortValue,
                    'sys_language_uid' => 0,
                ];
                $dataMap[MenuGraphAssembler::TABLE_PRICEOPTION][$priceToken] = $priceFields;
                $expectedCreates[] = new ApplyExpectedCreate(
                    table: MenuGraphAssembler::TABLE_PRICEOPTION,
                    newToken: $priceToken,
                    localRef: $priceOption->localRef,
                    entityKind: 'priceoption',
                    expectedFields: $priceFields,
                );
            }
        }

        // Drop empty table keys for a clean datamap (optional); keep order-stable empty removal.
        foreach ($dataMap as $table => $rows) {
            if ($rows === []) {
                unset($dataMap[$table]);
            }
        }

        $this->assertNoNumericRecordKeys($dataMap);
        $this->assertNoMenuTable($dataMap);
        $this->assertNoPublicUuid($dataMap);

        return new ApplyDataMap(
            dataMap: $dataMap,
            localRefToNewToken: $localRefToNewToken,
            expectedCreates: $expectedCreates,
        );
    }

    /**
     * @param array<string, string> $tokensByLocalRef
     */
    private function relationValue(ApplyEntityReference $entity, array $tokensByLocalRef): int|string
    {
        if ($entity->status === 'reuse') {
            return (int)$entity->uid;
        }

        return $tokensByLocalRef[$entity->localRef]
            ?? throw new \InvalidArgumentException('Missing CREATE token for ' . $entity->localRef, 1757000107);
    }

    private function allocateSorting(int $next, int $step): int
    {
        if ($next < 0 || $next > PHP_INT_MAX) {
            throw new \InvalidArgumentException(self::SORT_OVERFLOW, 1757000108);
        }
        if ($next > PHP_INT_MAX - $step) {
            // next itself may be near overflow when used as value; still allow if next fits
        }
        if ($next > PHP_INT_MAX) {
            throw new \InvalidArgumentException(self::SORT_OVERFLOW, 1757000109);
        }

        return $next;
    }

    /**
     * @param array<string, string> $existing
     */
    private function assertUniqueToken(array $existing, string $localRef, string $token): void
    {
        if (isset($existing[$localRef])) {
            throw new \InvalidArgumentException('Duplicate localRef token', 1757000110);
        }
        if (in_array($token, $existing, true)) {
            throw new \InvalidArgumentException('Duplicate NEW token', 1757000111);
        }
        if (!str_starts_with($token, 'NEW')) {
            throw new \InvalidArgumentException('Token must start with NEW', 1757000112);
        }
    }

    private function newToken(string $kind, string $localRef): string
    {
        return 'NEWarp' . $kind . substr(hash('sha256', $kind . "\0" . $localRef), 0, 40);
    }

    /**
     * @param array<string, array<string, array<string, int|string>>> $dataMap
     */
    private function assertNoNumericRecordKeys(array $dataMap): void
    {
        foreach ($dataMap as $rows) {
            foreach (array_keys($rows) as $key) {
                if (is_int($key) || ctype_digit((string)$key)) {
                    throw new \InvalidArgumentException('Numeric datamap record keys are forbidden', 1757000113);
                }
            }
        }
    }

    /**
     * @param array<string, array<string, array<string, int|string>>> $dataMap
     */
    private function assertNoMenuTable(array $dataMap): void
    {
        if (isset($dataMap[MenuGraphAssembler::TABLE_MENU])) {
            throw new \InvalidArgumentException('Menu datamap rows are forbidden', 1757000114);
        }
    }

    /**
     * @param array<string, array<string, array<string, int|string>>> $dataMap
     */
    private function assertNoPublicUuid(array $dataMap): void
    {
        foreach ($dataMap as $rows) {
            foreach ($rows as $fields) {
                if (array_key_exists('public_uuid', $fields)) {
                    throw new \InvalidArgumentException('public_uuid must not be submitted', 1757000115);
                }
            }
        }
    }
}
