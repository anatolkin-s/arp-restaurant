<?php

declare(strict_types=1);

namespace Anatolkin\ArpRestaurant\Backend\Editor\Visibility;

/**
 * Deterministic SHA-256 confirmation-continuity token for a PriceOptionVisibilityPlan.
 * Not CSRF, auth, authorization, or external identity.
 */
final class PriceOptionVisibilityFingerprint
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
        int $beforeHidden,
        int $afterHidden,
    ): string {
        $payload = [
            'version' => 'price-option-visibility-v1',
            'uid' => $uid,
            'pid' => $pid,
            'publicUuid' => $publicUuid,
            'tstamp' => $tstamp,
            'placementUid' => $placementUid,
            'menuUid' => $menuUid,
            'categoryUid' => $categoryUid,
            'itemUid' => $itemUid,
            'before' => [
                'hidden' => $beforeHidden,
            ],
            'after' => [
                'hidden' => $afterHidden,
            ],
        ];

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        return hash('sha256', $json);
    }

    public static function fromPlan(PriceOptionVisibilityPlan $plan): string
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
            $plan->currentHidden,
            $plan->requestedHidden,
        );
    }
}
