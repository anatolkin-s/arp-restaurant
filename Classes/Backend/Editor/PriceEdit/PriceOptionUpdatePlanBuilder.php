<?php

declare(strict_types=1);

namespace Anatolkin\ArpRestaurant\Backend\Editor\PriceEdit;

use Anatolkin\ArpRestaurant\Backend\Editor\Bulk\DecimalMinorUnitParser;
use Anatolkin\ArpRestaurant\Backend\Editor\MinorUnitMoneyFormatter;
use Anatolkin\ArpRestaurant\Backend\Editor\RestaurantTitleNormalizer;

/**
 * Pure PriceOption update review preparation. No persistence.
 */
final class PriceOptionUpdatePlanBuilder
{
    public function __construct(
        private readonly DecimalMinorUnitParser $priceParser = new DecimalMinorUnitParser(2),
        private readonly MinorUnitMoneyFormatter $moneyFormatter = new MinorUnitMoneyFormatter(2),
        private readonly RestaurantTitleNormalizer $titleNormalizer = new RestaurantTitleNormalizer(),
    ) {}

    public function prepare(
        PriceOptionEditContext $context,
        string $submittedLabel,
        string $submittedPrice,
    ): PriceOptionUpdatePreparationResult {
        $label = $this->titleNormalizer->cleanDisplayTitle($submittedLabel);

        if ($this->unicodeLength($label) > 255) {
            return new PriceOptionUpdatePreparationResult(
                outcome: 'preparationBlocked',
                context: $context,
                plan: null,
                blockers: [new PriceOptionEditBlocker('labelTooLong')],
            );
        }

        $parsed = $this->priceParser->parse($submittedPrice);
        if ($parsed['ok'] !== true) {
            return new PriceOptionUpdatePreparationResult(
                outcome: 'preparationBlocked',
                context: $context,
                plan: null,
                blockers: [new PriceOptionEditBlocker($parsed['error'])],
            );
        }

        $amountMinor = $parsed['amountMinor'];
        $before = new PriceOptionUpdateValues(
            label: $context->label,
            amountMinor: $context->amountMinor,
            formattedAmount: $context->formattedAmount,
        );
        $after = new PriceOptionUpdateValues(
            label: $label,
            amountMinor: $amountMinor,
            formattedAmount: $this->moneyFormatter->format($amountMinor),
        );

        if ($before->label === $after->label && $before->amountMinor === $after->amountMinor) {
            return new PriceOptionUpdatePreparationResult(
                outcome: 'noChanges',
                context: $context,
                plan: null,
                blockers: [],
            );
        }

        return new PriceOptionUpdatePreparationResult(
            outcome: 'updateReady',
            context: $context,
            plan: new PriceOptionUpdatePlan(
                uid: $context->uid,
                pid: $context->pid,
                publicUuid: $context->publicUuid,
                tstamp: $context->tstamp,
                placementUid: $context->placementUid,
                before: $before,
                after: $after,
                menuTitle: $context->menuTitle,
                categoryTitle: $context->categoryTitle,
                itemTitle: $context->itemTitle,
            ),
            blockers: [],
        );
    }

    /**
     * Unicode code-point length (not bytes). Aligns with TCA label max=255.
     */
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
