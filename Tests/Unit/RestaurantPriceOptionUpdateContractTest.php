<?php

declare(strict_types=1);

/**
 * Static contract checks for EDITOR-2C2 confirmed existing PriceOption update.
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
$writer = file_get_contents($root . '/Classes/Backend/Editor/PriceEdit/Write/RestaurantPriceOptionUpdateWriter.php') ?: '';
$verifier = file_get_contents($root . '/Classes/Backend/Editor/PriceEdit/Write/PriceOptionUpdateVerifier.php') ?: '';
$template = file_get_contents($root . '/Resources/Private/Templates/RestaurantEditor/Index.html') ?: '';
$xlf = file_get_contents($root . '/Resources/Private/Language/locallang_mod_editor.xlf') ?: '';
$contract = file_get_contents($root . '/Documentation/EDITOR_WRITE_CONTRACT.md') ?: '';

assertTrue(
    str_contains($controller, "PRICE_EDIT_APPLY_ACTION = 'priceOptionEditApply'")
    && str_contains($controller, 'priceEditApplyToken')
    && str_contains($template, 'name="priceOptionEditApply"')
    && str_contains($template, 'name="priceEditApplyToken"'),
    'dedicated priceOptionEditApply action/token'
);

$applyMethod = '';
if (preg_match('/function processPriceOptionEditApply\(.*?function processPriceOptionEditReview/s', $controller, $m)) {
    $applyMethod = $m[0];
}
assertTrue($applyMethod !== '', 'processPriceOptionEditApply extractable');

$csrfPos = strpos($applyMethod, 'validateToken');
$permPos = strpos($applyMethod, 'priceOptionEditPermissionBlocker');
$loadPos = strpos($applyMethod, 'priceOptionEditReader->load');
$preparePos = strpos($applyMethod, 'priceOptionUpdatePlanBuilder->prepare');
$hashPos = strpos($applyMethod, 'hash_equals');
$writerPos = strpos($applyMethod, 'priceOptionUpdateWriter->execute');
assertTrue(
    $csrfPos !== false
    && $permPos !== false
    && $loadPos !== false
    && $preparePos !== false
    && $hashPos !== false
    && $writerPos !== false
    && $csrfPos < $permPos
    && $permPos < $loadPos
    && $loadPos < $preparePos
    && $preparePos < $hashPos
    && $hashPos < $writerPos,
    'CSRF → permissions → fresh load → rebuild plan → fingerprint → writer'
);

assertTrue(
    !str_contains($applyMethod, "body['public_uuid']")
    && !str_contains($applyMethod, "body['tstamp']")
    && !str_contains($applyMethod, "body['placement']")
    && !str_contains($applyMethod, "body['menuUid']")
    && !str_contains($applyMethod, "body['categoryUid']")
    && !str_contains($applyMethod, "body['itemUid']"),
    'no posted tstamp/public_uuid/graph authority'
);

assertTrue(
    str_contains($applyMethod, 'FINGERPRINT_PATTERN')
    && str_contains($applyMethod, 'hash_equals($review->plan->fingerprint, $confirmedFingerprint)'),
    'confirmedFingerprint lowercase 64-hex validated + hash_equals'
);

assertTrue(
    preg_match(
        '/!hash_equals\(\$review->plan->fingerprint,\s*\$confirmedFingerprint\)\)\s*\{\s*return \$empty\([\s\S]*?\'confirmationStale\'/s',
        $applyMethod
    ) === 1
    && strpos($applyMethod, "'confirmationStale'") < $writerPos,
    'stale fingerprint => no writer'
);

assertTrue(
    preg_match(
        '/outcome === \'noChanges\'\)\s*\{\s*return \$empty\([\s\S]*?\'alreadyMatches\'/s',
        $applyMethod
    ) === 1
    && strpos($applyMethod, "'alreadyMatches'") < $writerPos,
    'noChanges => no writer'
);

assertTrue(
    strpos($applyMethod, "outcome === 'preparationBlocked'") < $writerPos,
    'preparationBlocked => no writer'
);

assertTrue(
    substr_count($applyMethod, 'priceOptionUpdateWriter->execute') === 1,
    'writer invoked at most once'
);

assertTrue(
    preg_match(
        '/if\s*\(\s*!\$execution->dataHandlerAttempted\s*\)\s*\{[\s\S]*?writePreparationBlocked[\s\S]*?\}\s*\$this->enqueuePriceUpdateFlash/s',
        $applyMethod
    ) === 1
    && str_contains($applyMethod, 'RedirectResponse($redirectUri, 303)')
    && str_contains($applyMethod, "'priceOption' => \$priceOptionUid")
    && !str_contains($applyMethod, '#arp-restaurant')
    && !str_contains($applyMethod, 'rollback')
    && !str_contains($applyMethod, 'retry'),
    'PRG only after dataHandlerAttempted; 303; preserves id/menu/priceOption; no fragment/rollback/retry'
);

assertTrue(
    str_contains($template, 'priceEdit.save')
    && str_contains($template, 'updateReady')
    && str_contains($template, 'priceEdit.review.plan.fingerprint')
    && str_contains($template, 'name="confirmedFingerprint"'),
    'Save price only for updateReady; fingerprint from server plan'
);

assertTrue(
    str_contains($template, 'data-arp-price-confirmation-stale="1"')
    && str_contains($template, 'arp-editor-stale-warning')
    && str_contains($template, 'priceEdit.saveRefreshed')
    && str_contains($template, 'data-arp-price-save-refreshed="1"'),
    'stale warning prominent + Save refreshed price'
);

assertTrue(
    str_contains($template, 'priceEdit.nothingWritten')
    && str_contains($template, 'priceEdit.savingWillUpdate'),
    'review still states nothing written before Save; write-capable footer'
);

$priceEditSection = '';
if (preg_match(
    '/data-arp-price-edit="1"[\s\S]*?<\/section>/',
    $template,
    $sectionMatch
) === 1) {
    $priceEditSection = $sectionMatch[0];
}
assertTrue($priceEditSection !== '', 'PriceEdit panel section extractable');

$authForm = '';
if (preg_match(
    '/<form[^>]*id="arp-price-edit-form"[^>]*>[\s\S]*?<\/form>/',
    $priceEditSection,
    $formMatch
) === 1) {
    $authForm = $formMatch[0];
}
assertTrue($authForm !== '', '1. editable PriceOption form has stable id arp-price-edit-form');

assertTrue(
    preg_match('/id="arp-price-edit-label"[^>]*name="label"/', $authForm) === 1
    && preg_match('/id="arp-price-edit-price"[^>]*name="price"/', $authForm) === 1
    && substr_count($authForm, 'name="label"') === 1
    && substr_count($authForm, 'name="price"') === 1,
    '2/9. visible label/price belong once to authoritative form'
);

assertTrue(
    substr_count($priceEditSection, 'name="label"') === 1
    && substr_count($priceEditSection, 'name="price"') === 1,
    '9. only one authoritative PriceEdit label and price field in panel'
);

$reviewCard = '';
if (preg_match(
    '/data-arp-price-update-plan="1"[\s\S]*?<\/div>\s*<\/f:if>/',
    $priceEditSection,
    $cardMatch
) === 1) {
    $reviewCard = $cardMatch[0];
}
assertTrue($reviewCard !== '', 'review card extractable for form ownership');

assertTrue(
    preg_match(
        '/data-arp-price-save="1"[^>]*form="arp-price-edit-form"|form="arp-price-edit-form"[^>]*data-arp-price-save="1"/',
        $reviewCard
    ) === 1
    && str_contains($reviewCard, 'name="priceOptionEditApply"'),
    '3/5. Save price submits authoritative form via form= attribute'
);

assertTrue(
    preg_match(
        '/data-arp-price-save-refreshed="1"[^>]*form="arp-price-edit-form"|form="arp-price-edit-form"[^>]*data-arp-price-save-refreshed="1"/',
        $reviewCard
    ) === 1,
    '4. Save refreshed price submits authoritative form'
);

assertTrue(
    str_contains($authForm, 'name="confirmedFingerprint"')
    && str_contains($authForm, 'priceEdit.review.plan.fingerprint')
    && str_contains($authForm, 'name="priceEditApplyToken"')
    && !str_contains($reviewCard, 'name="confirmedFingerprint"')
    && !str_contains($reviewCard, 'name="priceEditApplyToken"')
    && !str_contains($reviewCard, 'name="label"')
    && !str_contains($reviewCard, 'name="price"')
    && !str_contains($reviewCard, '<form'),
    '6/7/8. fingerprint + apply token on authoritative form; no shadow Save form with hidden label/price'
);

assertTrue(
    str_contains($applyMethod, "\$submittedLabel = (string)(\$body['label'] ?? '')")
    && str_contains($applyMethod, "\$submittedPrice = (string)(\$body['price'] ?? '')")
    && strpos($applyMethod, "\$body['label']") < strpos($applyMethod, 'priceOptionUpdatePlanBuilder->prepare')
    && strpos($applyMethod, "\$body['price']") < strpos($applyMethod, 'priceOptionUpdatePlanBuilder->prepare')
    && preg_match(
        '/priceOptionUpdatePlanBuilder->prepare\(\s*\$load->context,\s*\$submittedLabel,\s*\$submittedPrice/s',
        $applyMethod
    ) === 1,
    '10. post-review live label/price reach apply as submittedLabel/submittedPrice for fresh fingerprint rebuild'
);

$templateWithoutSaveButtons = preg_replace(
    '/data-arp-price-update-plan="1"[\s\S]*?<\/div>\s*<\/f:if>/',
    '<!-- price-update-card-removed -->',
    $template
) ?? $template;
assertTrue(
    !str_contains($templateWithoutSaveButtons, 'name="priceOptionEditApply"'),
    'Save control only inside updateReady card'
);

$noChangesBranch = '';
if (preg_match(
    '/priceEdit\.review\.outcome\} == \'noChanges\'[\s\S]*?<\/f:if>/',
    $template,
    $nc
)) {
    $noChangesBranch = $nc[0];
}
assertTrue(
    $noChangesBranch !== ''
    && !str_contains($noChangesBranch, 'priceOptionEditApply'),
    'no Save button for noChanges'
);

assertTrue(
    str_contains($xlf, 'id="priceEdit.flash.updatedTitle"')
    && str_contains($xlf, 'Price updated')
    && str_contains($xlf, 'id="priceEdit.flash.partialTitle"')
    && str_contains($xlf, 'Price update may be incomplete')
    && str_contains($xlf, 'id="priceEdit.flash.failedTitle"')
    && str_contains($xlf, 'Price update failed')
    && str_contains($xlf, 'id="priceEdit.alreadyMatches"')
    && str_contains($xlf, 'Price already matches these values. Nothing was written.'),
    'success / partial / failure / alreadyMatches copy exists'
);

assertTrue(
    str_contains($writer, 'process_datamap(')
    && !str_contains($writer, 'process_cmdmap(')
    && !str_contains($verifier, 'process_datamap(')
    && !str_contains($verifier, 'makeInstance(DataHandler'),
    'writer sole process_datamap; verifier has no DataHandler call'
);

assertTrue(
    str_contains($contract, 'EDITOR-2C2')
    && str_contains($contract, 'RestaurantPriceOptionUpdateWriter')
    && str_contains($contract, 'price-option-update-v1'),
    'EDITOR_WRITE_CONTRACT documents EDITOR-2C2'
);

assertTrue(
    str_contains($controller, 'priceOptionEditPermissionBlocker')
    && substr_count($applyMethod, 'priceOptionEditPermissionBlocker') >= 1,
    'permission checks before writer'
);

echo $failures === 0 ? "\nAll RestaurantPriceOptionUpdateContract tests passed.\n" : "\n{$failures} RestaurantPriceOptionUpdateContract test(s) failed.\n";
exit($failures === 0 ? 0 : 1);
