<?php

declare(strict_types=1);

/**
 * Static contract checks for EDITOR-2B3 Prepare apply wiring.
 * No browser automation; source inspection only.
 */

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

$root = dirname(__DIR__, 2);
$controller = file_get_contents($root . '/Classes/Backend/Controller/RestaurantEditorController.php') ?: '';
$view = file_get_contents($root . '/Classes/Backend/Editor/Bulk/BulkPreviewView.php') ?: '';
$template = file_get_contents($root . '/Resources/Private/Templates/RestaurantEditor/Index.html') ?: '';
$applyBlob = '';
foreach (glob($root . '/Classes/Backend/Editor/Apply/*.php') ?: [] as $file) {
    $applyBlob .= file_get_contents($file) ?: '';
}

assertTrue(
    str_contains($controller, "BULK_PREPARE_ACTION = 'bulkApplyPrepare'")
    && str_contains($controller, "prepareToken")
    && str_contains($controller, "\$body['prepareToken']"),
    'dedicated bulkApplyPrepare action/token'
);
assertTrue(
    str_contains($controller, 'validatePosted($body[\'rows\'] ?? null)')
    && substr_count($controller, 'validatePosted($body[\'rows\'] ?? null)') >= 2,
    'Prepare revalidates posted rows'
);
assertTrue(
    str_contains($controller, 'bulkApplyPrepare')
    && str_contains($controller, 'resolveIdentities(')
    && str_contains($controller, 'applyPlanBuilder->prepare($identity)'),
    'Prepare re-resolves identities then builds plan'
);
assertTrue(
    !str_contains($controller, 'bulkApply')
    || (str_contains($controller, 'bulkApplyPrepare') && !preg_match("/'bulkApply'/", $controller)),
    'no bulkApply write action'
);
assertTrue(
    !str_contains($controller, "body['identity")
    && !str_contains($controller, 'postedIdentity')
    && !str_contains($controller, 'submittedResolution'),
    'no posted identity snapshot authority'
);
assertTrue(
    str_contains($controller, '#arp-restaurant-bulk-workbench')
    && str_contains($template, 'name="bulkApplyPrepare"')
    && str_contains($template, 'name="prepareToken"'),
    'workbench fragment + Prepare UI retained'
);
assertTrue(
    str_contains($view, 'prepareToken')
    && str_contains($view, 'BulkApplyPreparationResult'),
    'BulkPreviewView exposes prepareToken and apply result'
);
assertTrue(
    str_contains($template, 'bulk.apply.notAvailable')
    && !str_contains($template, 'name="bulkApply"')
    && !str_contains(strtolower($template), 'confirm & save'),
    'no Apply/Save button in template'
);
assertTrue(
    !str_contains($applyBlob, 'DataHandler')
    && !str_contains($applyBlob, 'process_datamap')
    && !str_contains($applyBlob, 'QueryBuilder')
    && !str_contains($applyBlob, 'executeStatement'),
    'Apply package has no DataHandler/QueryBuilder writes'
);

echo $failures === 0 ? "\nAll RestaurantEditorPrepareContract tests passed.\n" : "\n{$failures} RestaurantEditorPrepareContract test(s) failed.\n";
exit($failures === 0 ? 0 : 1);
