<?php

declare(strict_types=1);

use Anatolkin\ArpRestaurant\Backend\Editor\RestaurantTitleNormalizer;

require dirname(__DIR__, 2) . '/Classes/Backend/Editor/RestaurantTitleNormalizer.php';

$normalizer = new RestaurantTitleNormalizer();
$failures = 0;

function assertTrue(bool $condition, string $message): void
{
    global $failures;
    if ($condition) {
        echo "PASS  {$message}\n";
        return;
    }
    ++$failures;
    echo "FAIL  {$message}\n";
}

assertTrue($normalizer->cleanDisplayTitle('  Atlantic salmon  ') === 'Atlantic salmon', '1. trim whitespace');
assertTrue($normalizer->cleanDisplayTitle('Atlantic   salmon') === 'Atlantic salmon', '2. collapse repeated ASCII spaces');
assertTrue(
    $normalizer->cleanDisplayTitle("Atlantic\xC2\xA0salmon") === 'Atlantic salmon',
    '3. collapse NBSP / Unicode separator'
);
assertTrue(
    $normalizer->cleanDisplayTitle('Atlantic Salmon') === 'Atlantic Salmon',
    '4. preserve display capitalization'
);
assertTrue(
    $normalizer->matchKey('Atlantic Salmon') === $normalizer->matchKey('atlantic salmon'),
    '5. case-folded match key'
);
assertTrue(
    $normalizer->matchKey('Atlantic salmon') === $normalizer->matchKey('Atlantic Salmon'),
    '6. Atlantic salmon == Atlantic Salmon for matching'
);
assertTrue(
    $normalizer->matchKey('Tea') === $normalizer->matchKey('tea')
    && $normalizer->matchKey('Tea') === $normalizer->matchKey('TEA'),
    '7. Tea == tea == TEA for matching'
);
assertTrue(
    $normalizer->matchKey('Salmon') !== $normalizer->matchKey('Salmon!'),
    '8. Salmon != Salmon!'
);
assertTrue(
    $normalizer->matchKey('Salmon') !== $normalizer->matchKey('Salmon Roll')
    && $normalizer->matchKey('Salmon') !== $normalizer->matchKey('Salmon-roll'),
    '8b. Salmon remains distinct from Salmon Roll / Salmon-roll'
);
assertTrue(
    $normalizer->matchKey('  Atlantic   salmon  ') === $normalizer->matchKey('ATLANTIC SALMON'),
    'spacing + case share one match key'
);

echo $failures === 0 ? "\nAll RestaurantTitleNormalizer tests passed.\n" : "\n{$failures} RestaurantTitleNormalizer test(s) failed.\n";
exit($failures === 0 ? 0 : 1);
