<?php

declare(strict_types=1);

namespace Anatolkin\ArpRestaurant\Backend\Editor\Apply;

use Anatolkin\ArpRestaurant\Backend\Editor\Bulk\BulkDraftRow;
use Anatolkin\ArpRestaurant\Backend\Editor\Bulk\BulkDraftRunGrouping;
use Anatolkin\ArpRestaurant\Backend\Editor\Identity\BulkIdentityResolutionResult;
use Anatolkin\ArpRestaurant\Backend\Editor\Identity\IdentityResolution;
use Anatolkin\ArpRestaurant\Backend\Editor\MinorUnitMoneyFormatter;

/**
 * Pure ApplyPlan construction from a server-authoritative identityResolved result.
 * No database reads/writes and no TYPO3 persistence APIs.
 */
final class BulkApplyPlanBuilder
{
    public function __construct(
        private readonly BulkDraftRunGrouping $runGrouping = new BulkDraftRunGrouping(),
        private readonly MinorUnitMoneyFormatter $moneyFormatter = new MinorUnitMoneyFormatter(2),
    ) {}

    public function prepare(BulkIdentityResolutionResult $identity): BulkApplyPreparationResult
    {
        if (!$identity->draft->isDraftValid()) {
            return $this->blocked($identity, [new ApplyPlanBlocker('draftNotValid')]);
        }

        if ($identity->outcome !== 'identityResolved') {
            return $this->blocked($identity, [new ApplyPlanBlocker('identityNotResolved')]);
        }

        if ($identity->targetMenu === null) {
            return $this->blocked($identity, [new ApplyPlanBlocker('applyPlanInvariant', 'missingTargetMenu')]);
        }

        $blockers = [];
        $categories = [];
        foreach ($identity->categoryResolutions as $resolution) {
            $entity = $this->entityFromResolution($resolution);
            if ($entity === null) {
                $blockers[] = new ApplyPlanBlocker(
                    'applyPlanInvariant',
                    'categoryStatus:' . $resolution->status
                );
                continue;
            }
            $categories[] = $entity;
        }

        $items = [];
        foreach ($identity->itemResolutions as $resolution) {
            $entity = $this->entityFromResolution($resolution);
            if ($entity === null) {
                $blockers[] = new ApplyPlanBlocker(
                    'applyPlanInvariant',
                    'itemStatus:' . $resolution->status
                );
                continue;
            }
            $items[] = $entity;
        }

        $categoryByRef = [];
        foreach ($categories as $category) {
            $categoryByRef[$category->localRef] = $category;
        }
        $itemByRef = [];
        foreach ($items as $item) {
            $itemByRef[$item->localRef] = $item;
        }

        $rowsByKey = [];
        foreach ($identity->draft->rows as $row) {
            $rowsByKey[$row->draftKey] = $row;
        }

        foreach ($identity->boundRows as $bound) {
            if ($bound->categoryResolution === null || $bound->itemResolution === null) {
                $blockers[] = new ApplyPlanBlocker('missingBoundResolution', $bound->draftKey);
                continue;
            }
            if (
                ($bound->categoryResolution->status !== 'create' && $bound->categoryResolution->status !== 'reuse')
                || ($bound->itemResolution->status !== 'create' && $bound->itemResolution->status !== 'reuse')
            ) {
                $blockers[] = new ApplyPlanBlocker(
                    'applyPlanInvariant',
                    'boundStatus:' . $bound->draftKey
                );
            }
            $row = $rowsByKey[$bound->draftKey] ?? null;
            if ($row === null || $row->amountMinor === null) {
                $blockers[] = new ApplyPlanBlocker('missingAmountMinor', $bound->draftKey);
            }
        }

        if ($blockers !== []) {
            return $this->blocked($identity, $blockers);
        }

        $placements = $this->buildPlacements(
            $identity->draft->rows,
            $identity->boundRows,
            $categoryByRef,
            $itemByRef,
            $blockers,
        );
        if ($blockers !== []) {
            return $this->blocked($identity, $blockers);
        }

        $priceOptionCount = 0;
        foreach ($placements as $placement) {
            $priceOptionCount += count($placement->priceOptions);
        }

        $summary = new ApplyPlanSummary(
            createCategories: $this->countStatus($categories, 'create'),
            createItems: $this->countStatus($items, 'create'),
            createPlacements: count($placements),
            createPriceOptions: $priceOptionCount,
            reuseCategories: $this->countStatus($categories, 'reuse'),
            reuseItems: $this->countStatus($items, 'reuse'),
        );

        $identitySummary = $identity->summary;
        if (
            $summary->createCategories !== $identitySummary->createCategories
            || $summary->createItems !== $identitySummary->createItems
            || $summary->createPlacements !== $identitySummary->createPlacements
            || $summary->createPriceOptions !== $identitySummary->createPriceOptions
            || $summary->reuseCategories !== $identitySummary->reuseCategories
            || $summary->reuseItems !== $identitySummary->reuseItems
        ) {
            return $this->blocked(
                $identity,
                [new ApplyPlanBlocker('applyPlanInvariant', 'summaryMismatch')]
            );
        }

        if ($priceOptionCount !== count($identity->draft->rows)) {
            return $this->blocked(
                $identity,
                [new ApplyPlanBlocker('applyPlanInvariant', 'priceOptionCount')]
            );
        }

        $fingerprint = ApplyPlanFingerprint::compute(
            $identity->targetMenu,
            $categories,
            $items,
            $placements,
        );

        $plan = new ApplyPlan(
            targetMenu: $identity->targetMenu,
            categories: $categories,
            items: $items,
            placements: $placements,
            summary: $summary,
            fingerprint: $fingerprint,
        );

        return new BulkApplyPreparationResult(
            outcome: 'applyReady',
            identity: $identity,
            plan: $plan,
            blockers: [],
        );
    }

    private function entityFromResolution(IdentityResolution $resolution): ?ApplyEntityReference
    {
        if ($resolution->status === 'create') {
            return new ApplyEntityReference(
                localRef: $resolution->draftIdentityKey,
                status: 'create',
                displayTitle: $resolution->normalizedTitle,
            );
        }

        if ($resolution->status === 'reuse') {
            if (
                $resolution->uid === null
                || $resolution->publicUuid === null
                || $resolution->tstamp === null
                || $resolution->pid === null
                || $resolution->canonicalTitle === ''
            ) {
                return null;
            }

            return new ApplyEntityReference(
                localRef: $resolution->draftIdentityKey,
                status: 'reuse',
                displayTitle: $resolution->canonicalTitle,
                canonicalTitle: $resolution->canonicalTitle,
                uid: $resolution->uid,
                publicUuid: $resolution->publicUuid,
                tstamp: $resolution->tstamp,
                pid: $resolution->pid,
            );
        }

        return null;
    }

    /**
     * @param list<BulkDraftRow> $rows
     * @param list<\Anatolkin\ArpRestaurant\Backend\Editor\Identity\IdentityBoundRow> $boundRows
     * @param array<string, ApplyEntityReference> $categoryByRef
     * @param array<string, ApplyEntityReference> $itemByRef
     * @param list<ApplyPlanBlocker> $blockers
     * @return list<ApplyPlacementPlan>
     */
    private function buildPlacements(
        array $rows,
        array $boundRows,
        array $categoryByRef,
        array $itemByRef,
        array &$blockers,
    ): array {
        $boundByKey = [];
        foreach ($boundRows as $bound) {
            $boundByKey[$bound->draftKey] = $bound;
        }

        $placements = [];
        $count = count($rows);
        $start = 0;
        while ($start < $count) {
            $end = $start;
            while (
                $end + 1 < $count
                && $this->runGrouping->sameCategoryItem(
                    $rows[$start]->category,
                    $rows[$start]->item,
                    $rows[$end + 1]->category,
                    $rows[$end + 1]->item,
                )
            ) {
                ++$end;
            }

            $empty = 0;
            $named = 0;
            for ($i = $start; $i <= $end; ++$i) {
                if ($rows[$i]->variant === '') {
                    ++$empty;
                } else {
                    ++$named;
                }
            }

            if ($empty > 0 && $named > 0) {
                $blockers[] = new ApplyPlanBlocker('applyPlanInvariant', 'mixedVariantRun');
                return [];
            }

            if ($empty > 0 && $named === 0) {
                for ($i = $start; $i <= $end; ++$i) {
                    $placement = $this->placementFromRows(
                        [$rows[$i]],
                        $boundByKey,
                        $categoryByRef,
                        $itemByRef,
                        $blockers,
                    );
                    if ($placement === null) {
                        return [];
                    }
                    $placements[] = $placement;
                }
            } elseif ($named > 0 && $empty === 0) {
                $runRows = [];
                for ($i = $start; $i <= $end; ++$i) {
                    $runRows[] = $rows[$i];
                }
                $placement = $this->placementFromRows(
                    $runRows,
                    $boundByKey,
                    $categoryByRef,
                    $itemByRef,
                    $blockers,
                );
                if ($placement === null) {
                    return [];
                }
                $placements[] = $placement;
            } else {
                $blockers[] = new ApplyPlanBlocker('applyPlanInvariant', 'emptyRun');
                return [];
            }

            $start = $end + 1;
        }

        return $placements;
    }

    /**
     * @param list<BulkDraftRow> $rows
     * @param array<string, \Anatolkin\ArpRestaurant\Backend\Editor\Identity\IdentityBoundRow> $boundByKey
     * @param array<string, ApplyEntityReference> $categoryByRef
     * @param array<string, ApplyEntityReference> $itemByRef
     * @param list<ApplyPlanBlocker> $blockers
     */
    private function placementFromRows(
        array $rows,
        array $boundByKey,
        array $categoryByRef,
        array $itemByRef,
        array &$blockers,
    ): ?ApplyPlacementPlan {
        $first = $rows[0];
        $bound = $boundByKey[$first->draftKey] ?? null;
        if (
            $bound === null
            || $bound->categoryResolution === null
            || $bound->itemResolution === null
        ) {
            $blockers[] = new ApplyPlanBlocker('missingBoundResolution', $first->draftKey);
            return null;
        }

        $categoryRef = $bound->categoryResolution->draftIdentityKey;
        $itemRef = $bound->itemResolution->draftIdentityKey;
        if (!isset($categoryByRef[$categoryRef]) || !isset($itemByRef[$itemRef])) {
            $blockers[] = new ApplyPlanBlocker('applyPlanInvariant', 'missingEntityRef');
            return null;
        }

        $priceOptions = [];
        foreach ($rows as $row) {
            if ($row->amountMinor === null) {
                $blockers[] = new ApplyPlanBlocker('missingAmountMinor', $row->draftKey);
                return null;
            }
            $priceOptions[] = new ApplyPriceOptionPlan(
                localRef: 'po:' . $row->draftKey,
                draftKey: $row->draftKey,
                sourceLine: $row->sourceLine,
                originalOrder: $row->originalOrder,
                label: $row->variant,
                amountMinor: $row->amountMinor,
                formattedAmount: $this->moneyFormatter->format($row->amountMinor),
            );
        }

        return new ApplyPlacementPlan(
            localRef: 'p:' . $first->originalOrder,
            categoryLocalRef: $categoryRef,
            itemLocalRef: $itemRef,
            startOriginalOrder: $first->originalOrder,
            priceOptions: $priceOptions,
            categoryDisplayTitle: $categoryByRef[$categoryRef]->displayTitle,
            itemDisplayTitle: $itemByRef[$itemRef]->displayTitle,
            categoryStatus: $categoryByRef[$categoryRef]->status,
            itemStatus: $itemByRef[$itemRef]->status,
        );
    }

    /**
     * @param list<ApplyEntityReference> $entities
     */
    private function countStatus(array $entities, string $status): int
    {
        $count = 0;
        foreach ($entities as $entity) {
            if ($entity->status === $status) {
                ++$count;
            }
        }

        return $count;
    }

    /**
     * @param list<ApplyPlanBlocker> $blockers
     */
    private function blocked(
        BulkIdentityResolutionResult $identity,
        array $blockers,
    ): BulkApplyPreparationResult {
        return new BulkApplyPreparationResult(
            outcome: 'preparationBlocked',
            identity: $identity,
            plan: null,
            blockers: $blockers,
        );
    }
}
