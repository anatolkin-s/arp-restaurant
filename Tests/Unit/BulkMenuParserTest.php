<?php

declare(strict_types=1);

use Anatolkin\ArpRestaurant\Backend\Editor\Bulk\BulkMenuParser;
use Anatolkin\ArpRestaurant\Backend\Editor\Bulk\DecimalMinorUnitParser;
use Anatolkin\ArpRestaurant\Backend\Editor\MinorUnitMoneyFormatter;

require dirname(__DIR__, 2) . '/Classes/Backend/Editor/MinorUnitMoneyFormatter.php';
require dirname(__DIR__, 2) . '/Classes/Backend/Editor/Bulk/DecimalMinorUnitParser.php';
require dirname(__DIR__, 2) . '/Classes/Backend/Editor/Bulk/BulkMenuRow.php';
require dirname(__DIR__, 2) . '/Classes/Backend/Editor/Bulk/BulkMenuPreviewSection.php';
require dirname(__DIR__, 2) . '/Classes/Backend/Editor/Bulk/BulkMenuParseResult.php';
require dirname(__DIR__, 2) . '/Classes/Backend/Editor/Bulk/BulkMenuParser.php';

$parser = new BulkMenuParser(new DecimalMinorUnitParser(2), new MinorUnitMoneyFormatter(2));
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

$validTsv = "Category\tItem\tVariant\tPrice\n"
    . "Starters\tHummus\t\t8.00\n"
    . "Starters\tLentil Soup\t\t7.00\n"
    . "Mains\tGrilled Salmon\t\t23.00\n"
    . "Mains\tVegetable Curry\t\t18.00\n"
    . "Drinks\tTea\tSmall\t3.00\n"
    . "Drinks\tTea\tLarge\t4.50\n";

$withHeader = $parser->parse($validTsv);
assertTrue($withHeader->globalError === '', 'valid TSV has no global error');
assertTrue(count($withHeader->rows) === 6, 'valid TSV keeps all data rows');
assertTrue($withHeader->validCount === 6 && $withHeader->invalidCount === 0, 'valid TSV marks every row valid');
assertTrue($withHeader->rows[0]->category === 'Starters' && $withHeader->rows[0]->item === 'Hummus', 'first data row is Hummus');
assertTrue($withHeader->rows[0]->variant === '' && $withHeader->rows[0]->amountMinor === 800, 'blank Variant is preserved and 8.00 is 800');
assertTrue($withHeader->rows[2]->amountMinor === 2300, '23.00 converts to 2300 through the parser');
assertTrue($withHeader->rows[5]->variant === 'Large' && $withHeader->rows[5]->amountMinor === 450, '4.50 converts to 450 through the parser');
assertTrue($withHeader->rows[0]->sourceLine === 2, 'source line numbers skip the header row');
assertTrue(
    array_map(static fn ($section) => $section->category, $withHeader->sections) === ['Starters', 'Mains', 'Drinks'],
    'preview groups consecutive Category values'
);
assertTrue(count($withHeader->sections[2]->rows) === 2, 'Drinks keeps both Tea rows independently');

$crlf = "Category\tItem\tVariant\tPrice\r\nStarters\tHummus\t\t8.00\r\nDrinks\tTea\tLarge\t4.50\r\n";
$crlfResult = $parser->parse($crlf);
assertTrue($crlfResult->validCount === 2 && $crlfResult->rows[1]->amountMinor === 450, 'CRLF line endings parse as TSV');

$withoutHeader = $parser->parse("Starters\tHummus\t\t8.00\nMains\tGrilled Salmon\t\t23\n");
assertTrue($withoutHeader->validCount === 2, 'TSV without a header is accepted');
assertTrue($withoutHeader->rows[0]->sourceLine === 1, 'without a header, the first line is data line 1');
assertTrue($withoutHeader->rows[1]->amountMinor === 2300, '23 converts to 2300 through the parser');

$blankLines = $parser->parse("Starters\tHummus\t\t8.00\n\n\nMains\tSalmon\t\t23.00\n");
assertTrue(count($blankLines->rows) === 2, 'blank lines are skipped');
assertTrue($blankLines->rows[1]->sourceLine === 4, 'skipped blank lines keep original source line numbers');

$repeated = $parser->parse("Mains\tSalmon\t\t23.00\nMains\tSalmon\t\t23.00\n");
assertTrue(count($repeated->rows) === 2, 'repeated Category+Item rows stay independent');
assertTrue($repeated->rows[0]->item === 'Salmon' && $repeated->rows[1]->item === 'Salmon', 'repeated rows are not collapsed');
assertTrue(count($repeated->sections) === 1 && count($repeated->sections[0]->rows) === 2, 'repeated rows remain visible in the same category section');

$malformed = $parser->parse("Starters\tHummus\t\tabc\nStarters\tSoup\t\t8.00\n");
assertTrue($malformed->rows[0]->errors === ['invalidPrice'], 'malformed price stays visible with invalidPrice');
assertTrue($malformed->rows[0]->amountMinor === null, 'malformed price does not invent minor units');
assertTrue($malformed->rows[1]->isValid() && $malformed->validCount === 1 && $malformed->invalidCount === 1, 'valid rows still preview beside invalid rows');

$negative = $parser->parse("Starters\tHummus\t\t-8.00\n");
assertTrue($negative->rows[0]->errors === ['negativePrice'] && !$negative->rows[0]->isValid(), 'negative price stays visible');

$tooManyDecimals = $parser->parse("Starters\tHummus\t\t8.001\n");
assertTrue($tooManyDecimals->rows[0]->errors === ['tooManyDecimals'], 'more than 2 decimals stays visible');

$missingCategory = $parser->parse("\tHummus\t\t8.00\n");
assertTrue($missingCategory->rows[0]->errors === ['missingCategory'], 'missing Category stays visible');
assertTrue($missingCategory->rows[0]->sourceLine === 1, 'missing Category reports the source row number');

$missingItem = $parser->parse("Starters\t\t\t8.00\n");
assertTrue($missingItem->rows[0]->errors === ['missingItem'], 'missing Item stays visible');

$missingPrice = $parser->parse("Starters\tHummus\t\t\n");
assertTrue($missingPrice->rows[0]->errors === ['missingPrice'], 'missing Price stays visible');

$whitespaceCells = $parser->parse("  Starters  \t  Hummus  \t  \t  8.00  \n");
assertTrue($whitespaceCells->rows[0]->isValid() && $whitespaceCells->rows[0]->category === 'Starters', 'surrounding cell whitespace is trimmed');

$oversize = $parser->parse(str_repeat('a', 21), 20, 200);
assertTrue($oversize->globalError === 'inputTooLarge' && $oversize->rows === [], 'byte limit returns a global error without a truncated parse');

$tooManyRows = "Starters\tA\t\t1.00\nStarters\tB\t\t1.00\nStarters\tC\t\t1.00\n";
$limited = $parser->parse($tooManyRows, 65536, 2);
assertTrue($limited->globalError === 'tooManyRows' && $limited->rows === [], 'row limit returns a global error without a truncated parse');

$headerOnly = $parser->parse("Category\tItem\tVariant\tPrice\n");
assertTrue($headerOnly->globalError === 'emptyPaste', 'a header with no data rows is emptyPaste');

$empty = $parser->parse("\n\n");
assertTrue($empty->globalError === 'emptyPaste', 'blank input is emptyPaste');

$csvGuess = $parser->parse("Starters,Hummus,,8.00\n");
assertTrue($csvGuess->rows[0]->item === '' && in_array('missingItem', $csvGuess->rows[0]->errors, true), 'comma-separated input is not guessed as columns');

if ($failures > 0) {
    echo "\n{$failures} failing assertion(s)\n";
    exit(1);
}

echo "\nAll BulkMenuParser tests passed.\n";
exit(0);
