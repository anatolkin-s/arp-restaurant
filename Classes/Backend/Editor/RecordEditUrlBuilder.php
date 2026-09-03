<?php

declare(strict_types=1);

namespace Anatolkin\ArpRestaurant\Backend\Editor;

interface RecordEditUrlBuilder
{
    public function build(string $table, int $uid): ?string;
}
