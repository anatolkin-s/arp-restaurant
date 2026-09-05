<?php

declare(strict_types=1);

namespace Anatolkin\ArpRestaurant\Backend\Editor\Visibility\Write;

use Anatolkin\ArpRestaurant\Backend\Editor\Visibility\PriceOptionVisibilityContext;
use Anatolkin\ArpRestaurant\Backend\Editor\Visibility\PriceOptionVisibilityPlan;

/**
 * Pure post-attempt verification for PriceOption.hidden. No DataHandler / QueryBuilder.
 */
final class PriceOptionVisibilityVerifier
{
    /**
     * @param list<string> $errorLog
     */
    public function verify(
        PriceOptionVisibilityPlan $plan,
        ?PriceOptionVisibilityContext $afterContext,
        array $errorLog,
        bool $processThrew,
    ): PriceOptionVisibilityExecutionResult {
        $diagnostics = $errorLog;
        if ($processThrew) {
            $diagnostics[] = 'visibilityUpdateException';
        }

        if ($afterContext === null) {
            $diagnostics[] = 'verificationReadFailed';

            return new PriceOptionVisibilityExecutionResult(
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

            return new PriceOptionVisibilityExecutionResult(
                outcome: 'failed',
                dataHandlerAttempted: true,
                diagnostics: $diagnostics,
            );
        }

        $afterHidden = $afterContext->hidden ? 1 : 0;
        $hiddenMatches = $afterHidden === $plan->requestedHidden;

        if ($hiddenMatches && $diagnostics === []) {
            return new PriceOptionVisibilityExecutionResult(
                outcome: 'updated',
                dataHandlerAttempted: true,
                diagnostics: [],
            );
        }

        if ($hiddenMatches) {
            return new PriceOptionVisibilityExecutionResult(
                outcome: 'partialFailure',
                dataHandlerAttempted: true,
                diagnostics: $diagnostics,
            );
        }

        return new PriceOptionVisibilityExecutionResult(
            outcome: 'failed',
            dataHandlerAttempted: true,
            diagnostics: $diagnostics,
        );
    }
}
