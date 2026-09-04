<?php

declare(strict_types=1);

namespace Anatolkin\ArpRestaurant\Backend\Editor\PriceEdit;

/**
 * Deterministic SHA-256 confirmation-continuity token for a PriceOptionUpdatePlan.
 * Not CSRF, auth, authorization, or external identity.
 */
final class PriceOptionUpdateFingerprint
{
    public static function compute(
        int $uid,
        int $pid,
        string $publicUuid,
        int $tstamp,
        int $placementUid,
        int $menuUid,
        int $categoryUid,
        int $itemUid,
        string $beforeLabel,
        int $beforeAmountMinor,
        string $afterLabel,
        int $afterAmountMinor,
    ): string {
        $payload = [
            'version' => 'price-option-update-v1',
            'uid' => $uid,
            'pid' => $pid,
            'publicUuid' => $publicUuid,
            'tstamp' => $tstamp,
            'placementUid' => $placementUid,
            'menuUid' => $menuUid,
            'categoryUid' => $categoryUid,
            'itemUid' => $itemUid,
            'before' => [
                'label' => $beforeLabel,
                'amountMinor' => $beforeAmountMinor,
            ],
            'after' => [
                'label' => $afterLabel,
                'amountMinor' => $afterAmountMinor,
            ],
        ];

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        return hash('sha256', $json);
    }

    public static function fromPlan(PriceOptionUpdatePlan $plan): string
    {
        return self::compute(
            $plan->uid,
            $plan->pid,
            $plan->publicUuid,
            $plan->tstamp,
            $plan->placementUid,
            $plan->menuUid,
            $plan->categoryUid,
            $plan->itemUid,
            $plan->before->label,
            $plan->before->amountMinor,
            $plan->after->label,
            $plan->after->amountMinor,
        );
    }
}
