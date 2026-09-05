<?php

declare(strict_types=1);

namespace Anatolkin\ArpRestaurant\Backend\Editor\Visibility;

/**
 * Pure PriceOption.hidden review preparation. No persistence.
 *
 * Accepted submitted enum: "visible" (hidden=0) | "hidden" (hidden=1).
 * Anything else is rejected. No silent coercion.
 */
final class PriceOptionVisibilityPlanBuilder
{
    public function prepare(
        PriceOptionVisibilityContext $context,
        string $submittedVisibility,
    ): PriceOptionVisibilityPreparationResult {
        $requestedHidden = $this->normalizeSubmittedHidden($submittedVisibility);
        if ($requestedHidden === null) {
            return new PriceOptionVisibilityPreparationResult(
                outcome: 'preparationBlocked',
                context: $context,
                plan: null,
                blockers: [new PriceOptionVisibilityBlocker('malformedVisibility')],
            );
        }

        $currentHidden = $context->hidden ? 1 : 0;
        if ($currentHidden === $requestedHidden) {
            return new PriceOptionVisibilityPreparationResult(
                outcome: 'noChanges',
                context: $context,
                plan: null,
                blockers: [],
            );
        }

        return new PriceOptionVisibilityPreparationResult(
            outcome: 'visibilityUpdateReady',
            context: $context,
            plan: new PriceOptionVisibilityPlan(
                uid: $context->uid,
                pid: $context->pid,
                publicUuid: $context->publicUuid,
                tstamp: $context->tstamp,
                placementUid: $context->placementUid,
                menuUid: $context->menuUid,
                categoryUid: $context->categoryUid,
                itemUid: $context->itemUid,
                currentHidden: $currentHidden,
                requestedHidden: $requestedHidden,
                menuTitle: $context->menuTitle,
                categoryTitle: $context->categoryTitle,
                itemTitle: $context->itemTitle,
                label: $context->label,
                formattedAmount: $context->formattedAmount,
                fingerprint: PriceOptionVisibilityFingerprint::compute(
                    $context->uid,
                    $context->pid,
                    $context->publicUuid,
                    $context->tstamp,
                    $context->placementUid,
                    $context->menuUid,
                    $context->categoryUid,
                    $context->itemUid,
                    $currentHidden,
                    $requestedHidden,
                ),
            ),
            blockers: [],
        );
    }

    /**
     * @return 0|1|null
     */
    private function normalizeSubmittedHidden(string $submittedVisibility): ?int
    {
        return match ($submittedVisibility) {
            'visible' => 0,
            'hidden' => 1,
            default => null,
        };
    }
}
