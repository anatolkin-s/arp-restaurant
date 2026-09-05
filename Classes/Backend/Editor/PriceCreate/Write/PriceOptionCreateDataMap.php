<?php

declare(strict_types=1);

namespace Anatolkin\ArpRestaurant\Backend\Editor\PriceCreate\Write;

final readonly class PriceOptionCreateDataMap
{
    public function __construct(public string $newToken, public array $dataMap) {}
}
