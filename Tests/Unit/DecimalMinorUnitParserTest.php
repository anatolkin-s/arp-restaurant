<?php

declare(strict_types=1);

use Anatolkin\ArpRestaurant\Backend\Editor\Bulk\DecimalMinorUnitParser;

require dirname(__DIR__, 2) . '/Classes/Backend/Editor/Bulk/DecimalMinorUnitParser.php';

$parser = new DecimalMinorUnitParser(2);
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

$twentyThree = $parser->parse('23');
assertTrue($twentyThree === ['ok' => true, 'amountMinor' => 2300], '23 converts to 2300 minor units');

$twentyThreePointZero = $parser->parse('23.00');
assertTrue($twentyThreePointZero === ['ok' => true, 'amountMinor' => 2300], '23.00 converts to 2300 minor units');

$fourFifty = $parser->parse('4.50');
assertTrue($fourFifty === ['ok' => true, 'amountMinor' => 450], '4.50 converts to 450 minor units');

$trimmed = $parser->parse('  8.00  ');
assertTrue($trimmed === ['ok' => true, 'amountMinor' => 800], 'surrounding whitespace is trimmed before conversion');

assertTrue($parser->parse('') === ['ok' => false, 'error' => 'missingPrice'], 'empty price is missingPrice');
assertTrue($parser->parse('   ') === ['ok' => false, 'error' => 'missingPrice'], 'whitespace-only price is missingPrice');

assertTrue($parser->parse('abc') === ['ok' => false, 'error' => 'invalidPrice'], 'malformed alphabetic price is rejected');
assertTrue($parser->parse('23,00') === ['ok' => false, 'error' => 'invalidPrice'], 'comma decimal is not guessed');
assertTrue($parser->parse('$4.50') === ['ok' => false, 'error' => 'invalidPrice'], 'currency symbol is not guessed');
assertTrue($parser->parse('23.') === ['ok' => false, 'error' => 'invalidPrice'], 'trailing dot is not guessed');
assertTrue($parser->parse('.50') === ['ok' => false, 'error' => 'invalidPrice'], 'leading-dot fraction is not guessed');

assertTrue($parser->parse('-1') === ['ok' => false, 'error' => 'negativePrice'], 'negative integer price is rejected');
assertTrue($parser->parse('-4.50') === ['ok' => false, 'error' => 'negativePrice'], 'negative decimal price is rejected');

assertTrue($parser->parse('4.501') === ['ok' => false, 'error' => 'tooManyDecimals'], 'more than 2 fractional digits is rejected');
assertTrue($parser->parse('23.000') === ['ok' => false, 'error' => 'tooManyDecimals'], '3 trailing zeros still exceed 2 fractional digits');

if ($failures > 0) {
    echo "\n{$failures} failing assertion(s)\n";
    exit(1);
}

echo "\nAll DecimalMinorUnitParser tests passed.\n";
exit(0);
