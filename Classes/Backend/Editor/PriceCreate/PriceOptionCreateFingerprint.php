<?php

declare(strict_types=1);

namespace Anatolkin\ArpRestaurant\Backend\Editor\PriceCreate;

use Anatolkin\ArpRestaurant\Backend\Editor\RestaurantTitleNormalizer;

/** Confirmation continuity only; not authorization, CSRF or record identity. */
final class PriceOptionCreateFingerprint
{
    public static function fromPlan(PriceOptionCreatePlan $plan): string
    {
        $payload = ['version' => 'price-option-create-v1', 'pid' => $plan->pid];
        foreach (['menu', 'category', 'placement', 'item'] as $parent) {
            $payload[$parent . 'Uid'] = $plan->{$parent . 'Uid'};
            $payload[$parent] = [
                'uid' => $plan->{$parent . 'Uid'},
                'publicUuid' => $plan->{$parent . 'PublicUuid'},
                'tstamp' => $plan->{$parent . 'Tstamp'},
            ];
        }
        $existing = $plan->existingPriceOptions;
        usort($existing, static fn (ExistingPriceOptionSnapshot $a, ExistingPriceOptionSnapshot $b): int => $a->uid <=> $b->uid);
        $normalizer = new RestaurantTitleNormalizer();
        $payload['existing'] = array_map(static fn (ExistingPriceOptionSnapshot $row): array => [
            'uid' => $row->uid,
            'publicUuid' => $row->publicUuid,
            'tstamp' => $row->tstamp,
            'label' => $normalizer->cleanDisplayTitle($row->label),
            'amount' => $row->amountMinor,
            'sorting' => $row->sorting,
            'hidden' => $row->hidden,
        ], $existing);
        $payload['requested'] = [
            'label' => $plan->label,
            'amountMinor' => $plan->amountMinor,
            'plannedSorting' => $plan->plannedSorting,
        ];

        return hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }
}
