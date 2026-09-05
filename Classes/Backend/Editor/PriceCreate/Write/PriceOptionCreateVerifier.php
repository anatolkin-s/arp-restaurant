<?php

declare(strict_types=1);

namespace Anatolkin\ArpRestaurant\Backend\Editor\PriceCreate\Write;

use Anatolkin\ArpRestaurant\Backend\Editor\MenuGraphAssembler;
use Anatolkin\ArpRestaurant\Backend\Editor\PriceCreate\PriceOptionCreatePlan;

/** Pure verification after an attempted create. No persistence or cleanup. */
final class PriceOptionCreateVerifier
{
    public function resolveUid(string $token, PriceOptionCreateDataHandlerStateSnapshot $state): ?int
    {
        $raw = $state->substNEWwithIDs[$token] ?? null;
        if (($state->substNEWwithIDsTable[$token] ?? null) !== MenuGraphAssembler::TABLE_PRICEOPTION
            || (!is_int($raw) && !is_string($raw))) {
            return null;
        }
        $uid = filter_var($raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return $uid === false ? null : $uid;
    }

    public function verify(
        PriceOptionCreatePlan $plan,
        string $token,
        PriceOptionCreateDataHandlerStateSnapshot $state,
        ?PriceOptionCreateVerificationSnapshot $after,
        bool $processThrew,
    ): PriceOptionCreateExecutionResult {
        $diagnostics = $state->errorLog;
        if ($processThrew) {
            $diagnostics[] = 'priceCreateException';
        }
        $uid = $this->resolveUid($token, $state);
        if ($uid === null) {
            return new PriceOptionCreateExecutionResult('failed', true, [...$diagnostics, 'invalidSubstitution']);
        }
        $row = $after?->row;
        $context = $after?->context;
        if ($row === null || $context === null) {
            $diagnostics[] = 'verificationReadFailed';
        }
        if ($row !== null) {
            foreach (['uid' => $uid, 'pid' => $plan->pid, 'placement' => $plan->placementUid,
                'amount' => $plan->amountMinor, 'sorting' => $plan->plannedSorting,
                'sys_language_uid' => 0, 'hidden' => 0, 'deleted' => 0] as $field => $expected) {
                if (!isset($row[$field]) || (int)$row[$field] !== $expected) {
                    $diagnostics[] = 'mismatch:' . $field;
                }
            }
            if (($row['label'] ?? null) !== $plan->label) {
                $diagnostics[] = 'mismatch:label';
            }
            $uuid = (string)($row['public_uuid'] ?? '');
            if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/iD', $uuid) !== 1) {
                $diagnostics[] = 'invalidGeneratedUuid';
            }
            foreach ($plan->existingPriceOptions as $existing) {
                if (strcasecmp($existing->publicUuid, $uuid) === 0 || $existing->uid === $uid) {
                    $diagnostics[] = 'existingIdentityReused';
                }
            }
        }
        if ($context !== null) {
            if ($context->pid !== $plan->pid || $context->placementCategoryUid !== $plan->categoryUid
                || $context->placementItemUid !== $plan->itemUid) {
                $diagnostics[] = 'graphMismatch';
            }
            foreach (['menu', 'category', 'placement', 'item'] as $parent) {
                foreach (['Uid', 'PublicUuid', 'Tstamp'] as $suffix) {
                    $field = $parent . $suffix;
                    if ($context->$field !== $plan->$field) {
                        $diagnostics[] = 'parentMismatch:' . $field;
                    }
                }
            }
        }

        // A valid substitution is evidence of a create, even if read-back fails.
        return new PriceOptionCreateExecutionResult($diagnostics === [] ? 'created' : 'partialFailure', true, $diagnostics, $uid);
    }
}
