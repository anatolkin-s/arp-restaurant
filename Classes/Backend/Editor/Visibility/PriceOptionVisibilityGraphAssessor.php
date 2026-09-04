<?php

declare(strict_types=1);

namespace Anatolkin\ArpRestaurant\Backend\Editor\Visibility;

use Anatolkin\ArpRestaurant\Backend\Editor\PriceEdit\PriceOptionEditGraphAssessor;

/**
 * Graph membership for PriceOption visibility review.
 *
 * Proves: selected pid → Menu → Category → Placement → PriceOption.
 * A uid alone is not authority. No database access.
 */
final class PriceOptionVisibilityGraphAssessor
{
    public function __construct(
        private readonly PriceOptionEditGraphAssessor $editGraphAssessor = new PriceOptionEditGraphAssessor(),
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
    ): PriceOptionVisibilityLoadResult {
        $edit = $this->editGraphAssessor->assess(
            $pid,
            $selectedMenuUid,
            $priceOption,
            $placement,
            $category,
            $item,
            $menu,
            $recordEditUrl,
        );

        if ($edit->outcome !== 'loaded' || $edit->context === null) {
            $blockers = [];
            foreach ($edit->blockers as $blocker) {
                $blockers[] = new PriceOptionVisibilityBlocker($blocker->code, $blocker->detail);
            }

            return new PriceOptionVisibilityLoadResult(
                outcome: 'blocked',
                context: null,
                blockers: $blockers !== [] ? $blockers : [new PriceOptionVisibilityBlocker('inaccessiblePriceOption')],
            );
        }

        if ($priceOption === null || !$this->isBinaryHiddenFlag($priceOption['hidden'] ?? null)) {
            return $this->blocked('ambiguousHidden');
        }

        $context = $edit->context;

        return new PriceOptionVisibilityLoadResult(
            outcome: 'loaded',
            context: new PriceOptionVisibilityContext(
                uid: $context->uid,
                pid: $context->pid,
                publicUuid: $context->publicUuid,
                tstamp: $context->tstamp,
                label: $context->label,
                amountMinor: $context->amountMinor,
                formattedAmount: $context->formattedAmount,
                hidden: (int)$priceOption['hidden'] === 1,
                placementUid: $context->placementUid,
                sorting: $context->sorting,
                menuUid: $context->menuUid,
                menuTitle: $context->menuTitle,
                categoryUid: $context->categoryUid,
                categoryTitle: $context->categoryTitle,
                itemUid: $context->itemUid,
                itemTitle: $context->itemTitle,
                recordEditUrl: $context->recordEditUrl,
            ),
            blockers: [],
        );
    }

    private function isBinaryHiddenFlag(mixed $raw): bool
    {
        return $raw === 0 || $raw === 1 || $raw === '0' || $raw === '1';
    }

    private function blocked(string $code): PriceOptionVisibilityLoadResult
    {
        return new PriceOptionVisibilityLoadResult(
            outcome: 'blocked',
            context: null,
            blockers: [new PriceOptionVisibilityBlocker($code)],
        );
    }
}
