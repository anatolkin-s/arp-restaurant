<?php

declare(strict_types=1);

namespace Anatolkin\ArpRestaurant\Backend\Editor\Apply;

use Anatolkin\ArpRestaurant\Backend\Editor\Identity\TargetMenuSnapshot;

/**
 * Deterministic SHA-256 confirmation-continuity token for an ApplyPlan.
 * Not CSRF, auth, authorization, or external identity.
 */
final class ApplyPlanFingerprint
{
    /**
     * @param list<ApplyEntityReference> $categories
     * @param list<ApplyEntityReference> $items
     * @param list<ApplyPlacementPlan> $placements
     */
    public static function compute(
        TargetMenuSnapshot $targetMenu,
        array $categories,
        array $items,
        array $placements,
    ): string {
        $payload = [
            'targetMenu' => [
                'uid' => $targetMenu->uid,
                'pid' => $targetMenu->pid,
                'publicUuid' => $targetMenu->publicUuid,
                'tstamp' => $targetMenu->tstamp,
                'title' => $targetMenu->title,
            ],
            'categories' => self::entitiesPayload($categories),
            'items' => self::entitiesPayload($items),
            'placements' => self::placementsPayload($placements),
        ];

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        return hash('sha256', $json);
    }

    /**
     * @param list<ApplyEntityReference> $entities
     * @return list<array<string, mixed>>
     */
    private static function entitiesPayload(array $entities): array
    {
        $out = [];
        foreach ($entities as $entity) {
            $out[] = [
                'localRef' => $entity->localRef,
                'status' => $entity->status,
                'displayTitle' => $entity->displayTitle,
                'canonicalTitle' => $entity->canonicalTitle,
                'uid' => $entity->uid,
                'publicUuid' => $entity->publicUuid,
                'tstamp' => $entity->tstamp,
                'pid' => $entity->pid,
            ];
        }

        return $out;
    }

    /**
     * @param list<ApplyPlacementPlan> $placements
     * @return list<array<string, mixed>>
     */
    private static function placementsPayload(array $placements): array
    {
        $out = [];
        foreach ($placements as $placement) {
            $prices = [];
            foreach ($placement->priceOptions as $price) {
                $prices[] = [
                    'localRef' => $price->localRef,
                    'draftKey' => $price->draftKey,
                    'sourceLine' => $price->sourceLine,
                    'originalOrder' => $price->originalOrder,
                    'label' => $price->label,
                    'amountMinor' => $price->amountMinor,
                ];
            }
            $out[] = [
                'localRef' => $placement->localRef,
                'categoryLocalRef' => $placement->categoryLocalRef,
                'itemLocalRef' => $placement->itemLocalRef,
                'startOriginalOrder' => $placement->startOriginalOrder,
                'priceOptions' => $prices,
            ];
        }

        return $out;
    }
}
