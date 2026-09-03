<?php

declare(strict_types=1);

namespace Anatolkin\ArpRestaurant\Backend\Editor\Identity;

use Anatolkin\ArpRestaurant\Backend\Editor\Bulk\BulkDraftValidationResult;

/**
 * Read-only identity outcome for a DraftValid bulk draft.
 * outcome: identityResolved | resolutionBlocked
 * Not ApplyReady — future Apply must re-check concurrency and access.
 */
final readonly class BulkIdentityResolutionResult
{
    /**
     * @param list<IdentityResolution> $categoryResolutions
     * @param list<IdentityResolution> $itemResolutions
     * @param list<IdentityBoundRow> $boundRows
     * @param list<IdentityBlocker> $blockers
     */
    public function __construct(
        public string $outcome,
        public BulkDraftValidationResult $draft,
        public ?TargetMenuSnapshot $targetMenu,
        public array $categoryResolutions,
        public array $itemResolutions,
        public array $boundRows,
        public array $blockers,
        public IdentityResolutionSummary $summary,
    ) {}
}
