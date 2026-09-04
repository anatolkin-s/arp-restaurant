<?php

declare(strict_types=1);

/**
 * Pure contract for DataHandler state capture after success and throw paths.
 */

use Anatolkin\ArpRestaurant\Backend\Editor\Apply\Write\ApplyDataHandlerStateSnapshot;

require dirname(__DIR__, 2) . '/Classes/Backend/Editor/Apply/Write/ApplyDataHandlerStateSnapshot.php';

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

$normal = new class {
    public array $errorLog = ['ok'];
    public array $substNEWwithIDs = ['NEWarpI1' => 11];
    public array $substNEWwithIDs_table = ['NEWarpI1' => 'tx_arprestaurant_domain_model_item'];
};
$throwish = new class {
    public array $errorLog = ['mid'];
    public array $substNEWwithIDs = ['NEWarpC1' => 21, 'NEWarpI1' => 22];
    public array $substNEWwithIDs_table = [
        'NEWarpC1' => 'tx_arprestaurant_domain_model_category',
        'NEWarpI1' => 'tx_arprestaurant_domain_model_item',
    ];
};

$fromNormal = ApplyDataHandlerStateSnapshot::fromDataHandler($normal);
$fromThrow = ApplyDataHandlerStateSnapshot::fromDataHandler($throwish);

assertTrue($fromNormal->substNEWwithIDs === ['NEWarpI1' => 11], 'normal path snapshot keeps NEW mappings');
assertTrue($fromThrow->substNEWwithIDs['NEWarpC1'] === 21 && $fromThrow->substNEWwithIDs['NEWarpI1'] === 22, 'thrown-path snapshot keeps partial NEW mappings');
assertTrue($fromThrow->substNEWwithIDsTable['NEWarpC1'] === 'tx_arprestaurant_domain_model_category', 'thrown-path snapshot keeps table map');

$writer = file_get_contents(dirname(__DIR__, 2) . '/Classes/Backend/Editor/Apply/Write/RestaurantApplyWriter.php') ?: '';
$writerWithoutComments = preg_replace('!/\*.*?\*/!s', '', $writer) ?? $writer;
$writerWithoutComments = preg_replace('!^\s*//.*$!m', '', $writerWithoutComments) ?? $writerWithoutComments;

assertTrue(
    preg_match(
        '/\$dataHandlerAttempted\s*=\s*true;\s*\$dataHandler->process_datamap\(\);/s',
        $writerWithoutComments
    ) === 1,
    'attempt flag set immediately before process_datamap'
);
assertTrue(
    preg_match(
        '/process_datamap\(\);[\s\S]*?\}\s*catch[\s\S]*?\}\s*finally\s*\{[\s\S]*fromDataHandler\(\$dataHandler\)/s',
        $writerWithoutComments
    ) === 1,
    'state extraction lives in finally, not only success path after process_datamap'
);
assertTrue(
    !preg_match(
        '/process_datamap\(\);\s*\$errorLog\s*=/s',
        $writerWithoutComments
    ),
    'errorLog/subst assignment is not exclusive to the success path after process_datamap'
);

echo $failures === 0 ? "\nAll ApplyDataHandlerStateSnapshot tests passed.\n" : "\n{$failures} ApplyDataHandlerStateSnapshot test(s) failed.\n";
exit($failures === 0 ? 0 : 1);
