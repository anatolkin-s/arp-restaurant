<?php

declare(strict_types=1);

namespace Anatolkin\ArpRestaurant\Backend\Editor\PriceEdit\Write;

use Anatolkin\ArpRestaurant\Backend\Editor\PriceEdit\PriceOptionEditContext;
use Anatolkin\ArpRestaurant\Backend\Editor\PriceEdit\PriceOptionUpdatePlan;

/**
 * Pure post-attempt verification for existing PriceOption update.
 * No DataHandler / QueryBuilder.
 */
final class PriceOptionUpdateVerifier
{
    /**
     * @param list<string> $errorLog
     */
    public function verify(
        PriceOptionUpdatePlan $plan,
        ?PriceOptionEditContext $afterContext,
        array $errorLog,
        bool $processThrew,
    ): PriceOptionUpdateExecutionResult {
        $diagnostics = $errorLog;
        if ($processThrew) {
            $diagnostics[] = 'priceUpdateException';
        }

        if ($afterContext === null) {
            $diagnostics[] = 'verificationReadFailed';

            return new PriceOptionUpdateExecutionResult(
                outcome: 'failed',
                dataHandlerAttempted: true,
                diagnostics: $diagnostics,
            );
        }

        $identityOk = $afterContext->uid === $plan->uid
            && $afterContext->pid === $plan->pid
            && $afterContext->publicUuid === $plan->publicUuid
            && $afterContext->placementUid === $plan->placementUid
            && $afterContext->menuUid === $plan->menuUid
            && $afterContext->categoryUid === $plan->categoryUid
            && $afterContext->itemUid === $plan->itemUid;

        if (!$identityOk) {
            $diagnostics[] = 'graphIdentityMismatch';
        }

        $labelMatches = $afterContext->label === $plan->after->label;
        $amountMatches = $afterContext->amountMinor === $plan->after->amountMinor;
        $exactSuccess = $identityOk && $labelMatches && $amountMatches;
        $labelChanged = $afterContext->label !== $plan->before->label;
        $amountChanged = $afterContext->amountMinor !== $plan->before->amountMinor;
        $anyRequestedPersisted = ($labelMatches && $labelChanged)
            || ($amountMatches && $amountChanged)
            || ($labelMatches && $amountMatches);

        // Clean updated only when exact after-state and clean DataHandler log.
        if ($exactSuccess && $diagnostics === []) {
            return new PriceOptionUpdateExecutionResult(
                outcome: 'updated',
                dataHandlerAttempted: true,
                diagnostics: [],
            );
        }

        if ($exactSuccess && $diagnostics !== []) {
            return new PriceOptionUpdateExecutionResult(
                outcome: 'partialFailure',
                dataHandlerAttempted: true,
                diagnostics: $diagnostics,
            );
        }

        if ($anyRequestedPersisted || $labelChanged || $amountChanged) {
            return new PriceOptionUpdateExecutionResult(
                outcome: 'partialFailure',
                dataHandlerAttempted: true,
                diagnostics: $diagnostics,
            );
        }

        return new PriceOptionUpdateExecutionResult(
            outcome: 'failed',
            dataHandlerAttempted: true,
            diagnostics: $diagnostics,
        );
    }
}
