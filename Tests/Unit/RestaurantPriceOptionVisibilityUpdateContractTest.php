<?php

declare(strict_types=1);

/**
 * Static contract checks for EDITOR-2C4 confirmed PriceOption.hidden save.
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
$writer = file_get_contents($root . '/Classes/Backend/Editor/Visibility/Write/RestaurantPriceOptionVisibilityWriter.php') ?: '';
$verifier = file_get_contents($root . '/Classes/Backend/Editor/Visibility/Write/PriceOptionVisibilityVerifier.php') ?: '';
$template = file_get_contents($root . '/Resources/Private/Templates/RestaurantEditor/Index.html') ?: '';
$xlf = file_get_contents($root . '/Resources/Private/Language/locallang_mod_editor.xlf') ?: '';
$contract = file_get_contents($root . '/Documentation/EDITOR_WRITE_CONTRACT.md') ?: '';
$statusPartial = file_get_contents($root . '/Resources/Private/Partials/RestaurantEditor/Status.html') ?: '';

assertTrue(
    str_contains($controller, "PRICE_VISIBILITY_APPLY_ACTION = 'priceOptionVisibilityApply'")
    && str_contains($controller, 'priceVisibilityApplyToken')
    && str_contains($template, 'name="priceOptionVisibilityApply"')
    && str_contains($template, 'name="priceVisibilityApplyToken"'),
    'dedicated priceOptionVisibilityApply action/token'
);

$applyMethod = '';
if (preg_match('/function processPriceOptionVisibilityApply\(.*?function processPriceOptionVisibilityReview/s', $controller, $m)) {
    $applyMethod = $m[0];
}
assertTrue($applyMethod !== '', 'processPriceOptionVisibilityApply extractable');

$csrfPos = strpos($applyMethod, 'validateToken');
$permPos = strpos($applyMethod, 'priceOptionVisibilityPermissionBlocker');
$loadPos = strpos($applyMethod, 'priceOptionVisibilityReader->load');
$preparePos = strpos($applyMethod, 'priceOptionVisibilityPlanBuilder->prepare');
$hashPos = strpos($applyMethod, 'hash_equals');
$writerPos = strpos($applyMethod, 'priceOptionVisibilityWriter->execute');
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
    substr_count($applyMethod, 'priceOptionVisibilityWriter->execute') === 1,
    'writer invoked at most once'
);

assertTrue(
    preg_match(
        '/if\s*\(\s*!\$execution->dataHandlerAttempted\s*\)\s*\{[\s\S]*?writePreparationBlocked[\s\S]*?\}\s*\$this->enqueueVisibilityFlash/s',
        $applyMethod
    ) === 1
    && str_contains($applyMethod, 'RedirectResponse($redirectUri, 303)')
    && str_contains($applyMethod, "'priceOptionVisibility' => \$priceOptionUid")
    && !str_contains($applyMethod, '#arp-restaurant')
    && !str_contains($applyMethod, 'rollback')
    && !str_contains($applyMethod, 'retry'),
    'PRG only after dataHandlerAttempted; 303; keeps id/menu/priceOptionVisibility; no fragment/rollback/retry'
);

$visibilityPanel = '';
if (preg_match('/data-arp-price-visibility="1"[\s\S]*?<\/section>/', $template, $panelMatch) === 1) {
    $visibilityPanel = $panelMatch[0];
}
assertTrue($visibilityPanel !== '', 'visibility panel extractable');

$authForm = '';
if (preg_match(
    '/<form[^>]*id="arp-price-visibility-form"[^>]*>[\s\S]*?<\/form>/',
    $visibilityPanel,
    $formMatch
) === 1) {
    $authForm = $formMatch[0];
}
assertTrue($authForm !== '', 'one authoritative visibility form');
assertTrue(
    preg_match('/id="arp-price-visibility"[^>]*name="visibility"/', $authForm) === 1
    && substr_count($authForm, 'name="visibility"') === 1
    && substr_count($visibilityPanel, 'name="visibility"') === 1,
    'visible/hidden control belongs once to authoritative form'
);

$reviewCard = '';
if (preg_match(
    '/data-arp-visibility-update-plan="1"[\s\S]*?<\/div>\s*<\/f:if>/',
    $visibilityPanel,
    $cardMatch
) === 1) {
    $reviewCard = $cardMatch[0];
}
assertTrue($reviewCard !== '', 'READY card extractable');
assertTrue(
    preg_match(
        '/data-arp-visibility-save="1"[^>]*form="arp-price-visibility-form"|form="arp-price-visibility-form"[^>]*data-arp-visibility-save="1"/',
        $reviewCard
    ) === 1
    && str_contains($reviewCard, 'name="priceOptionVisibilityApply"'),
    'Save visibility submits authoritative form'
);
assertTrue(
    preg_match(
        '/data-arp-visibility-save-refreshed="1"[^>]*form="arp-price-visibility-form"|form="arp-price-visibility-form"[^>]*data-arp-visibility-save-refreshed="1"/',
        $reviewCard
    ) === 1,
    'Save refreshed visibility submits authoritative form'
);
assertTrue(
    str_contains($authForm, 'name="confirmedFingerprint"')
    && str_contains($authForm, 'priceVisibility.review.plan.fingerprint')
    && str_contains($authForm, 'name="priceVisibilityApplyToken"')
    && !str_contains($reviewCard, 'name="confirmedFingerprint"')
    && !str_contains($reviewCard, 'name="priceVisibilityApplyToken"')
    && !str_contains($reviewCard, 'name="visibility"')
    && !str_contains($reviewCard, '<form'),
    'fingerprint + apply token on live form; no shadow hidden visibility field in card'
);

assertTrue(
    str_contains($applyMethod, "\$submittedVisibility = (string)(\$body['visibility'] ?? '')")
    && strpos($applyMethod, "\$body['visibility']") < strpos($applyMethod, 'priceOptionVisibilityPlanBuilder->prepare')
    && preg_match(
        '/priceOptionVisibilityPlanBuilder->prepare\(\s*\$load->context,\s*\$submittedVisibility/s',
        $applyMethod
    ) === 1,
    'live Visible/Hidden reaches apply as submittedVisibility for fresh fingerprint rebuild'
);

$noChangesBranch = '';
if (preg_match(
    '/priceVisibility\.review\.outcome\} == \'noChanges\'[\s\S]*?<\/f:if>/',
    $visibilityPanel,
    $nc
)) {
    $noChangesBranch = $nc[0];
}
assertTrue(
    $noChangesBranch !== ''
    && !str_contains($noChangesBranch, 'priceOptionVisibilityApply'),
    'no Save button for noChanges'
);

assertTrue(
    str_contains($visibilityPanel, 'data-arp-visibility-confirmation-stale="1"')
    && str_contains($visibilityPanel, 'arp-editor-stale-warning')
    && str_contains($xlf, 'PRICE OPTION CHANGED — NOTHING WAS WRITTEN')
    && str_contains($xlf, 'id="priceVisibility.saveRefreshed"'),
    'stale warning prominent + Save refreshed visibility'
);

assertTrue(
    str_contains($xlf, 'id="priceVisibility.flash.updatedTitle"')
    && str_contains($xlf, 'Price option visibility updated')
    && str_contains($xlf, 'id="priceVisibility.flash.updated"')
    && str_contains($xlf, 'The visibility was saved.')
    && str_contains($xlf, 'id="priceVisibility.alreadyMatches"')
    && str_contains($xlf, 'Price option already has this visibility. Nothing was written.')
    && str_contains($xlf, 'id="priceVisibility.flash.partialTitle"')
    && str_contains($xlf, 'Visibility update may be incomplete')
    && str_contains($xlf, 'id="priceVisibility.flash.failedTitle"')
    && str_contains($xlf, 'Visibility update failed'),
    'success / alreadyMatches / partial / failure copy exists'
);

assertTrue(
    str_contains($writer, 'process_datamap(')
    && !str_contains($writer, 'process_cmdmap(')
    && !str_contains($verifier, 'process_datamap(')
    && !str_contains($verifier, 'makeInstance(DataHandler'),
    'writer sole process_datamap; verifier has no DataHandler call'
);

assertTrue(
    str_contains($contract, 'EDITOR-2C4')
    && str_contains($contract, 'RestaurantPriceOptionVisibilityWriter')
    && str_contains($contract, 'price-option-visibility-v1'),
    'EDITOR_WRITE_CONTRACT documents EDITOR-2C4'
);

assertTrue(
    str_contains($statusPartial, 'identifier="actions-eye"')
    && str_contains($statusPartial, 'identifier="actions-edit-hide"')
    && str_contains($statusPartial, '<span class="arp-editor-sr">{statusLabel}</span>'),
    'table icon semantics preserved: Core eye/hide + visually-hidden Visible/Hidden'
);

echo $failures === 0
    ? "\nAll RestaurantPriceOptionVisibilityUpdateContract tests passed.\n"
    : "\n{$failures} RestaurantPriceOptionVisibilityUpdateContract test(s) failed.\n";
exit($failures === 0 ? 0 : 1);
