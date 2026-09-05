<?php

declare(strict_types=1);

namespace Anatolkin\ArpRestaurant\Backend\Editor\PriceCreate\Write;

use Anatolkin\ArpRestaurant\Backend\Editor\PriceCreate\PriceOptionCreateContext;

final readonly class PriceOptionCreateVerificationSnapshot
{
    public function __construct(public ?array $row, public ?PriceOptionCreateContext $context) {}
}
