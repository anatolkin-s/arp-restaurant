<?php

declare(strict_types=1);

namespace Anatolkin\ArpRestaurant\Backend\Editor\PriceCreate;

use Anatolkin\ArpRestaurant\Backend\Editor\Bulk\DecimalMinorUnitParser;
use Anatolkin\ArpRestaurant\Backend\Editor\MinorUnitMoneyFormatter;
use Anatolkin\ArpRestaurant\Backend\Editor\RestaurantTitleNormalizer;

/**
 * Pure PriceOption-create review preparation. No persistence.
 */
final class PriceOptionCreatePlanBuilder
{
    public const SORT_STEP = 256;

    public function __construct(
        private readonly DecimalMinorUnitParser $priceParser = new DecimalMinorUnitParser(2),
        private readonly MinorUnitMoneyFormatter $moneyFormatter = new MinorUnitMoneyFormatter(2),
        private readonly RestaurantTitleNormalizer $titleNormalizer = new RestaurantTitleNormalizer(),
    ) {}

    public function prepare(
        PriceOptionCreateContext $context,
        string $submittedLabel,
        string $submittedPrice,
    ): PriceOptionCreatePreparationResult {
        $label = $this->titleNormalizer->cleanDisplayTitle($submittedLabel);
        if ($this->unicodeLength($label) > 255) {
            return $this->blocked($context, 'labelTooLong');
        }

        $shapeBlocker = $this->variantShapeBlocker($context, $label);
        if ($shapeBlocker !== '') {
            return $this->blocked($context, $shapeBlocker);
        }

        $parsed = $this->priceParser->parse($submittedPrice);
        if ($parsed['ok'] !== true) {
            return $this->blocked($context, $parsed['error']);
        }

        $plannedSorting = $this->plannedSorting($context->existingPriceOptions);
        if ($plannedSorting === null) {
            return $this->blocked($context, 'sortingOverflow');
        }

        $amountMinor = $parsed['amountMinor'];

        return new PriceOptionCreatePreparationResult(
            outcome: 'createReady',
            context: $context,
            plan: new PriceOptionCreatePlan(
                pid: $context->pid,
                menuUid: $context->menuUid,
                menuPublicUuid: $context->menuPublicUuid,
                menuTstamp: $context->menuTstamp,
                menuTitle: $context->menuTitle,
                categoryUid: $context->categoryUid,
                categoryPublicUuid: $context->categoryPublicUuid,
                categoryTstamp: $context->categoryTstamp,
                categoryTitle: $context->categoryTitle,
                placementUid: $context->placementUid,
                placementPublicUuid: $context->placementPublicUuid,
                placementTstamp: $context->placementTstamp,
                itemUid: $context->itemUid,
                itemPublicUuid: $context->itemPublicUuid,
                itemTstamp: $context->itemTstamp,
                itemTitle: $context->itemTitle,
                label: $label,
                amountMinor: $amountMinor,
                formattedAmount: $this->moneyFormatter->format($amountMinor),
                plannedSorting: $plannedSorting,
                existingPriceOptions: $context->existingPriceOptions,
            ),
            blockers: [],
        );
    }

    /**
     * @param list<ExistingPriceOptionSnapshot> $existing
     */
    private function variantShapeBlocker(PriceOptionCreateContext $context, string $label): string
    {
        $blankCount = 0;
        $namedKeys = [];
        foreach ($context->existingPriceOptions as $existing) {
            $existingLabel = $this->titleNormalizer->cleanDisplayTitle($existing->label);
            if ($existingLabel === '') {
                ++$blankCount;
                continue;
            }
            $namedKeys[] = $this->titleNormalizer->matchKey($existingLabel);
        }
        $namedCount = count($namedKeys);
        if ($blankCount === 0 && $namedCount === 0) {
            return '';
        }

        if ($blankCount === 1 && $namedCount === 0) {
            return 'simplePriceMustBecomeVariantFirst';
        }

        if ($blankCount > 0) {
            return 'existingVariantSetInvalid';
        }

        if ($label === '') {
            return 'variantRequired';
        }

        $submittedKey = $this->titleNormalizer->matchKey($label);
        foreach ($namedKeys as $existingKey) {
            if ($existingKey === $submittedKey) {
                return 'duplicateVariant';
            }
        }

        return '';
    }

    /**
     * @param list<ExistingPriceOptionSnapshot> $existing
     */
    private function plannedSorting(array $existing): ?int
    {
        if ($existing === []) {
            return self::SORT_STEP;
        }

        $maxSorting = null;
        foreach ($existing as $option) {
            if ($maxSorting === null || $option->sorting > $maxSorting) {
                $maxSorting = $option->sorting;
            }
        }
        if ($maxSorting === null) {
            return self::SORT_STEP;
        }
        if ($maxSorting > PHP_INT_MAX - self::SORT_STEP) {
            return null;
        }

        return $maxSorting + self::SORT_STEP;
    }

    private function blocked(
        PriceOptionCreateContext $context,
        string $code,
    ): PriceOptionCreatePreparationResult {
        return new PriceOptionCreatePreparationResult(
            outcome: 'preparationBlocked',
            context: $context,
            plan: null,
            blockers: [new PriceOptionCreateBlocker($code)],
        );
    }

    private function unicodeLength(string $value): int
    {
        if (\function_exists('mb_strlen')) {
            return \mb_strlen($value, 'UTF-8');
        }

        if (preg_match_all('/./us', $value, $matches) === false) {
            return 0;
        }

        return count($matches[0]);
    }
}
