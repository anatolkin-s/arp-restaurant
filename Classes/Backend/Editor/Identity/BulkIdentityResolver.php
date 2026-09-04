<?php

declare(strict_types=1);

namespace Anatolkin\ArpRestaurant\Backend\Editor\Identity;

use Anatolkin\ArpRestaurant\Backend\Editor\Bulk\BulkDraftRow;
use Anatolkin\ArpRestaurant\Backend\Editor\Bulk\BulkDraftValidationResult;
use Anatolkin\ArpRestaurant\Backend\Editor\RestaurantTitleNormalizer;

/**
 * Pure CREATE / REUSE / AMBIGUOUS decisions over a DraftValid draft and
 * already-loaded candidates. No QueryBuilder / DataHandler / DB writes.
 *
 * Identity comparison uses RestaurantTitleNormalizer::matchKey (whitespace
 * normalized + Unicode case-folded). Distinct draft identities are indexed by
 * draft-local prefixed keys (c:<matchKey> / i:<matchKey>) so numeric-looking
 * titles remain safe under PHP array-key casting.
 *
 * Missing/unusable public_uuid on a sole REUSE candidate fails closed
 * (status inaccessible + blocker missingPublicUuid). No repair / minting.
 */
final class BulkIdentityResolver
{
    private const UUID_PATTERN = '/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/';

    public function __construct(
        private readonly RestaurantTitleNormalizer $titleNormalizer = new RestaurantTitleNormalizer(),
    ) {}

    /**
     * @param list<PersistedIdentityCandidate> $itemCandidates
     * @param list<PersistedIdentityCandidate> $categoryCandidates
     */
    public function resolve(
        BulkDraftValidationResult $draft,
        ?TargetMenuSnapshot $targetMenu,
        array $itemCandidates,
        array $categoryCandidates,
        string $permissionBlocker = '',
        string $targetMenuBlocker = '',
    ): BulkIdentityResolutionResult {
        if (!$draft->isDraftValid()) {
            return $this->blocked(
                $draft,
                null,
                [],
                [],
                [],
                [new IdentityBlocker('draftNotValid')],
                $this->emptySummary(),
            );
        }

        if ($permissionBlocker !== '') {
            return $this->blocked(
                $draft,
                null,
                [],
                [],
                [],
                [new IdentityBlocker($permissionBlocker)],
                $this->emptySummary(),
            );
        }

        if ($targetMenu === null) {
            return $this->blocked(
                $draft,
                null,
                [],
                [],
                [],
                [new IdentityBlocker($targetMenuBlocker !== '' ? $targetMenuBlocker : 'missingTargetMenu')],
                $this->emptySummary(),
            );
        }

        $categoryTitles = [];
        $itemTitles = [];
        foreach ($draft->rows as $row) {
            $categoryKey = $this->categoryKey($row->category);
            $itemKey = $this->itemKey($row->item);
            // First cleanDisplayTitle in originalOrder becomes the CREATE proposal.
            if (!isset($categoryTitles[$categoryKey])) {
                $categoryTitles[$categoryKey] = $row->category;
            }
            if (!isset($itemTitles[$itemKey])) {
                $itemTitles[$itemKey] = $row->item;
            }
        }

        $categoryResolutions = [];
        $itemResolutions = [];
        $blockers = [];

        foreach ($categoryTitles as $draftIdentityKey => $displayTitle) {
            $resolution = $this->resolveTitle(
                $draftIdentityKey,
                $displayTitle,
                $this->matchesByMatchKey($categoryCandidates, $this->titleNormalizer->matchKey($displayTitle)),
            );
            $categoryResolutions[] = $resolution;
            $blocker = $this->blockerForResolution($resolution, 'category');
            if ($blocker !== null) {
                $blockers[] = $blocker;
            }
        }

        foreach ($itemTitles as $draftIdentityKey => $displayTitle) {
            $resolution = $this->resolveTitle(
                $draftIdentityKey,
                $displayTitle,
                $this->matchesByMatchKey($itemCandidates, $this->titleNormalizer->matchKey($displayTitle)),
            );
            $itemResolutions[] = $resolution;
            $blocker = $this->blockerForResolution($resolution, 'item');
            if ($blocker !== null) {
                $blockers[] = $blocker;
            }
        }

        $categoryByKey = [];
        foreach ($categoryResolutions as $resolution) {
            $categoryByKey[$resolution->draftIdentityKey] = $resolution;
        }
        $itemByKey = [];
        foreach ($itemResolutions as $resolution) {
            $itemByKey[$resolution->draftIdentityKey] = $resolution;
        }

        $boundRows = [];
        foreach ($draft->rows as $row) {
            $boundRows[] = new IdentityBoundRow(
                draftKey: $row->draftKey,
                categoryResolution: $categoryByKey[$this->categoryKey($row->category)] ?? null,
                itemResolution: $itemByKey[$this->itemKey($row->item)] ?? null,
            );
        }

        $summary = $this->buildSummary($categoryResolutions, $itemResolutions, $draft->rows);
        $outcome = $blockers === [] ? 'identityResolved' : 'resolutionBlocked';

        return new BulkIdentityResolutionResult(
            outcome: $outcome,
            draft: $draft,
            targetMenu: $targetMenu,
            categoryResolutions: $categoryResolutions,
            itemResolutions: $itemResolutions,
            boundRows: $boundRows,
            blockers: $blockers,
            summary: $summary,
        );
    }

    /**
     * @param list<PersistedIdentityCandidate> $candidates
     * @return list<PersistedIdentityCandidate>
     */
    public function matchesByMatchKey(array $candidates, string $matchKey): array
    {
        $matches = [];
        foreach ($candidates as $candidate) {
            if ($this->titleNormalizer->matchKey($candidate->title) === $matchKey) {
                $matches[] = $candidate;
            }
        }

        return $matches;
    }

    /**
     * Draft-run Placement count (append semantics). Requires DraftValid rows.
     * Runs use cleaned display Category+Item strings (case-preserving).
     *
     * @param list<BulkDraftRow> $rows
     */
    public function countFuturePlacements(array $rows): int
    {
        $count = count($rows);
        $placements = 0;
        $start = 0;
        while ($start < $count) {
            $end = $start;
            $category = $rows[$start]->category;
            $item = $rows[$start]->item;
            while (
                $end + 1 < $count
                && $rows[$end + 1]->category === $category
                && $rows[$end + 1]->item === $item
            ) {
                ++$end;
            }

            $empty = 0;
            $named = 0;
            for ($i = $start; $i <= $end; ++$i) {
                if ($rows[$i]->variant === '') {
                    ++$empty;
                } else {
                    ++$named;
                }
            }

            if ($empty > 0 && $named === 0) {
                $placements += $end - $start + 1;
            } elseif ($named > 0 && $empty === 0) {
                ++$placements;
            }

            $start = $end + 1;
        }

        return $placements;
    }

    /**
     * @param list<PersistedIdentityCandidate> $matches
     */
    private function resolveTitle(
        string $draftIdentityKey,
        string $displayTitle,
        array $matches,
    ): IdentityResolution {
        $matchCount = count($matches);
        if ($matchCount === 0) {
            return new IdentityResolution(
                status: 'create',
                draftIdentityKey: $draftIdentityKey,
                normalizedTitle: $displayTitle,
                matchCount: 0,
            );
        }

        if ($matchCount > 1) {
            return new IdentityResolution(
                status: 'ambiguous',
                draftIdentityKey: $draftIdentityKey,
                normalizedTitle: $displayTitle,
                matchCount: $matchCount,
            );
        }

        $candidate = $matches[0];
        if (!$this->isUsablePublicUuid($candidate->publicUuid)) {
            return new IdentityResolution(
                status: 'inaccessible',
                draftIdentityKey: $draftIdentityKey,
                normalizedTitle: $displayTitle,
                matchCount: 1,
                uid: $candidate->uid,
                publicUuid: null,
                tstamp: $candidate->tstamp,
                pid: $candidate->pid,
                canonicalTitle: $candidate->title,
            );
        }

        return new IdentityResolution(
            status: 'reuse',
            draftIdentityKey: $draftIdentityKey,
            normalizedTitle: $displayTitle,
            matchCount: 1,
            uid: $candidate->uid,
            publicUuid: $candidate->publicUuid,
            tstamp: $candidate->tstamp,
            pid: $candidate->pid,
            canonicalTitle: $candidate->title,
        );
    }

    private function blockerForResolution(IdentityResolution $resolution, string $entityKind): ?IdentityBlocker
    {
        if ($resolution->status === 'ambiguous') {
            return new IdentityBlocker(
                code: $entityKind === 'category' ? 'ambiguousCategory' : 'ambiguousItem',
                entityKind: $entityKind,
                normalizedTitle: $resolution->normalizedTitle,
                matchCount: $resolution->matchCount,
            );
        }
        if ($resolution->status === 'inaccessible') {
            return new IdentityBlocker(
                code: 'missingPublicUuid',
                entityKind: $entityKind,
                normalizedTitle: $resolution->normalizedTitle,
                matchCount: $resolution->matchCount,
            );
        }

        return null;
    }

    private function categoryKey(string $displayTitle): string
    {
        return 'c:' . $this->titleNormalizer->matchKey($displayTitle);
    }

    private function itemKey(string $displayTitle): string
    {
        return 'i:' . $this->titleNormalizer->matchKey($displayTitle);
    }

    /**
     * @param list<IdentityResolution> $categoryResolutions
     * @param list<IdentityResolution> $itemResolutions
     * @param list<BulkDraftRow> $rows
     */
    private function buildSummary(
        array $categoryResolutions,
        array $itemResolutions,
        array $rows,
    ): IdentityResolutionSummary {
        $createCategories = 0;
        $reuseCategories = 0;
        $ambiguousCategories = 0;
        foreach ($categoryResolutions as $resolution) {
            if ($resolution->status === 'create') {
                ++$createCategories;
            } elseif ($resolution->status === 'reuse') {
                ++$reuseCategories;
            } elseif ($resolution->status === 'ambiguous') {
                ++$ambiguousCategories;
            }
        }

        $createItems = 0;
        $reuseItems = 0;
        $ambiguousItems = 0;
        foreach ($itemResolutions as $resolution) {
            if ($resolution->status === 'create') {
                ++$createItems;
            } elseif ($resolution->status === 'reuse') {
                ++$reuseItems;
            } elseif ($resolution->status === 'ambiguous') {
                ++$ambiguousItems;
            }
        }

        return new IdentityResolutionSummary(
            createCategories: $createCategories,
            createItems: $createItems,
            createPlacements: $this->countFuturePlacements($rows),
            createPriceOptions: count($rows),
            reuseCategories: $reuseCategories,
            reuseItems: $reuseItems,
            ambiguousCategories: $ambiguousCategories,
            ambiguousItems: $ambiguousItems,
        );
    }

    /**
     * @param list<IdentityResolution> $categoryResolutions
     * @param list<IdentityResolution> $itemResolutions
     * @param list<IdentityBoundRow> $boundRows
     * @param list<IdentityBlocker> $blockers
     */
    private function blocked(
        BulkDraftValidationResult $draft,
        ?TargetMenuSnapshot $targetMenu,
        array $categoryResolutions,
        array $itemResolutions,
        array $boundRows,
        array $blockers,
        IdentityResolutionSummary $summary,
    ): BulkIdentityResolutionResult {
        return new BulkIdentityResolutionResult(
            outcome: 'resolutionBlocked',
            draft: $draft,
            targetMenu: $targetMenu,
            categoryResolutions: $categoryResolutions,
            itemResolutions: $itemResolutions,
            boundRows: $boundRows,
            blockers: $blockers,
            summary: $summary,
        );
    }

    private function emptySummary(): IdentityResolutionSummary
    {
        return new IdentityResolutionSummary(0, 0, 0, 0, 0, 0, 0, 0);
    }

    private function isUsablePublicUuid(string $uuid): bool
    {
        return $uuid !== '' && preg_match(self::UUID_PATTERN, $uuid) === 1;
    }
}
