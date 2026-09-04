<?php

declare(strict_types=1);

namespace Anatolkin\ArpRestaurant\Backend\Editor\Bulk;

use Anatolkin\ArpRestaurant\Backend\Editor\MinorUnitMoneyFormatter;
use Anatolkin\ArpRestaurant\Backend\Editor\RestaurantTitleNormalizer;

/**
 * Server-authoritative bulk draft validation. No TYPO3 record reads or writes.
 *
 * DEFAULT_MAX_BYTES is 65536, the same effective bound as BulkMenuParser.
 * On revalidate it bounds the concatenated Category+Item+Variant+Price strings,
 * not an unbounded nested form.
 */
final class BulkDraftValidator
{
    public const DEFAULT_MAX_BYTES = 65536;
    public const DEFAULT_MAX_ROWS = 200;
    private const MAX_TEXT_LENGTH = 255;
    private const MAX_PRICE_LENGTH = 32;
    private const DRAFT_KEY_PATTERN = '/^[A-Za-z0-9_-]{1,32}$/';

    public function __construct(
        private readonly DecimalMinorUnitParser $priceParser,
        private readonly MinorUnitMoneyFormatter $moneyFormatter,
        private readonly RestaurantTitleNormalizer $titleNormalizer = new RestaurantTitleNormalizer(),
    ) {}

    /**
     * @param list<BulkMenuRow> $parsedRows
     */
    public function fromParsedRows(array $parsedRows): BulkDraftValidationResult
    {
        $draftRows = [];
        foreach ($parsedRows as $index => $parsed) {
            $draftRows[] = new BulkDraftRow(
                draftKey: 'r' . $index,
                originalOrder: $index,
                sourceLine: $parsed->sourceLine,
                category: $this->titleNormalizer->cleanDisplayTitle($parsed->category),
                item: $this->titleNormalizer->cleanDisplayTitle($parsed->item),
                variant: $parsed->variant,
                priceRaw: $parsed->priceRaw,
                amountMinor: $parsed->amountMinor,
                formattedAmount: $parsed->formattedAmount,
                errors: $parsed->errors,
            );
        }

        return $this->resultFromCanonicalRows($draftRows);
    }

    public function validatePosted(
        mixed $postedRows,
        int $maxRows = self::DEFAULT_MAX_ROWS,
        int $maxBytes = self::DEFAULT_MAX_BYTES,
    ): BulkDraftValidationResult {
        if (!is_array($postedRows)) {
            return $this->globalError('malformedDraft');
        }

        if ($postedRows === []) {
            return $this->globalError('emptyDraft');
        }

        if (count($postedRows) > $maxRows) {
            return $this->globalError('tooManyRows');
        }

        $aggregate = 0;
        $seenKeys = [];
        $built = [];
        foreach ($postedRows as $draftKey => $row) {
            if (!is_string($draftKey) || preg_match(self::DRAFT_KEY_PATTERN, $draftKey) !== 1) {
                return $this->globalError('malformedDraft');
            }
            if (isset($seenKeys[$draftKey])) {
                return $this->globalError('duplicateDraftKey');
            }
            $seenKeys[$draftKey] = true;
            if (!is_array($row)) {
                return $this->globalError('malformedDraft');
            }

            $category = $this->postedString($row, 'category');
            $item = $this->postedString($row, 'item');
            $variant = $this->postedString($row, 'variant');
            $priceRaw = $this->postedString($row, 'price');
            if ($category === null || $item === null || $variant === null || $priceRaw === null) {
                return $this->globalError('malformedDraft');
            }

            if (
                strlen($category) > self::MAX_TEXT_LENGTH
                || strlen($item) > self::MAX_TEXT_LENGTH
                || strlen($variant) > self::MAX_TEXT_LENGTH
                || strlen($priceRaw) > self::MAX_PRICE_LENGTH
            ) {
                return $this->globalError('malformedDraft');
            }

            $aggregate += strlen($category) + strlen($item) + strlen($variant) + strlen($priceRaw);
            if ($aggregate > $maxBytes) {
                return $this->globalError('inputTooLarge');
            }

            $originalOrder = $this->postedNonNegativeInt($row, 'originalOrder');
            $sourceLine = $this->postedPositiveInt($row, 'sourceLine');
            if ($originalOrder === null) {
                return $this->globalError('invalidOriginalOrder');
            }
            if ($sourceLine === null) {
                return $this->globalError('malformedDraft');
            }

            $built[] = $this->normalizeRow($draftKey, $originalOrder, $sourceLine, $category, $item, $variant, $priceRaw);
        }

        $orders = array_map(static fn (BulkDraftRow $row): int => $row->originalOrder, $built);
        $expected = range(0, count($built) - 1);
        $sortedOrders = $orders;
        sort($sortedOrders, SORT_NUMERIC);
        if ($sortedOrders !== $expected) {
            return $this->globalError('invalidOriginalOrder');
        }

        usort(
            $built,
            static fn (BulkDraftRow $left, BulkDraftRow $right): int => $left->originalOrder <=> $right->originalOrder
        );

        return $this->resultFromCanonicalRows($built);
    }

    /**
     * @param list<BulkDraftRow> $rows Already in originalOrder
     */
    private function resultFromCanonicalRows(array $rows): BulkDraftValidationResult
    {
        $withRuns = $this->applyRunValidation($rows);
        $validCount = 0;
        $invalidCount = 0;
        foreach ($withRuns as $row) {
            if ($row->isValid()) {
                ++$validCount;
            } else {
                ++$invalidCount;
            }
        }

        return new BulkDraftValidationResult(
            rows: $withRuns,
            validCount: $validCount,
            invalidCount: $invalidCount,
        );
    }

    /**
     * @param list<BulkDraftRow> $rows
     * @return list<BulkDraftRow>
     */
    private function applyRunValidation(array $rows): array
    {
        $count = count($rows);
        $extraErrors = array_fill(0, $count, []);
        $extraWarnings = array_fill(0, $count, []);
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

            $this->annotateRun($rows, $start, $end, $extraErrors, $extraWarnings);
            $start = $end + 1;
        }

        $out = [];
        foreach ($rows as $index => $row) {
            $errors = $row->errors;
            foreach ($extraErrors[$index] as $code) {
                if (!in_array($code, $errors, true)) {
                    $errors[] = $code;
                }
            }
            $warnings = $row->warnings;
            foreach ($extraWarnings[$index] as $code) {
                if (!in_array($code, $warnings, true)) {
                    $warnings[] = $code;
                }
            }
            $out[] = new BulkDraftRow(
                draftKey: $row->draftKey,
                originalOrder: $row->originalOrder,
                sourceLine: $row->sourceLine,
                category: $row->category,
                item: $row->item,
                variant: $row->variant,
                priceRaw: $row->priceRaw,
                amountMinor: $row->amountMinor,
                formattedAmount: $row->formattedAmount,
                errors: $errors,
                warnings: $warnings,
            );
        }

        return $out;
    }

    /**
     * @param list<BulkDraftRow> $rows
     * @param list<list<string>> $extraErrors
     * @param list<list<string>> $extraWarnings
     */
    private function annotateRun(
        array $rows,
        int $start,
        int $end,
        array &$extraErrors,
        array &$extraWarnings,
    ): void {
        $empty = 0;
        $named = 0;
        for ($i = $start; $i <= $end; ++$i) {
            if ($rows[$i]->variant === '') {
                ++$empty;
            } else {
                ++$named;
            }
        }

        if ($empty > 0 && $named > 0) {
            for ($i = $start; $i <= $end; ++$i) {
                $extraErrors[$i][] = 'mixedVariantRun';
            }
            return;
        }

        $length = $end - $start + 1;
        if ($empty === 0 && $named >= 2) {
            $tally = [];
            for ($i = $start; $i <= $end; ++$i) {
                $label = $rows[$i]->variant;
                $tally[$label] = ($tally[$label] ?? 0) + 1;
            }
            for ($i = $start; $i <= $end; ++$i) {
                if ($tally[$rows[$i]->variant] > 1) {
                    $extraErrors[$i][] = 'duplicateVariant';
                }
            }
            return;
        }

        if ($length === 1 && $rows[$start]->variant !== '') {
            $extraWarnings[$start][] = 'singleNamedVariant';
        }
    }

    private function normalizeRow(
        string $draftKey,
        int $originalOrder,
        int $sourceLine,
        string $category,
        string $item,
        string $variant,
        string $priceRaw,
    ): BulkDraftRow {
        $category = $this->titleNormalizer->cleanDisplayTitle($category);
        $item = $this->titleNormalizer->cleanDisplayTitle($item);
        $variant = trim($variant);
        $priceRaw = trim($priceRaw);

        $errors = [];
        if ($category === '') {
            $errors[] = 'missingCategory';
        }
        if ($item === '') {
            $errors[] = 'missingItem';
        }

        $amountMinor = null;
        $formattedAmount = '';
        $price = $this->priceParser->parse($priceRaw);
        if ($price['ok'] === true) {
            $amountMinor = $price['amountMinor'];
            $formattedAmount = $this->moneyFormatter->format($amountMinor);
        } else {
            $errors[] = $price['error'];
        }

        return new BulkDraftRow(
            draftKey: $draftKey,
            originalOrder: $originalOrder,
            sourceLine: $sourceLine,
            category: $category,
            item: $item,
            variant: $variant,
            priceRaw: $priceRaw,
            amountMinor: $amountMinor,
            formattedAmount: $formattedAmount,
            errors: $errors,
        );
    }

    /**
     * @param array<mixed> $row
     */
    private function postedString(array $row, string $key): ?string
    {
        if (!array_key_exists($key, $row) || !is_string($row[$key])) {
            return null;
        }

        return $row[$key];
    }

    /**
     * @param array<mixed> $row
     */
    private function postedNonNegativeInt(array $row, string $key): ?int
    {
        if (!array_key_exists($key, $row) || (!is_string($row[$key]) && !is_int($row[$key]))) {
            return null;
        }
        $raw = is_int($row[$key]) ? (string)$row[$key] : $row[$key];
        if (preg_match('/^(0|[1-9][0-9]*)$/', $raw) !== 1) {
            return null;
        }

        return (int)$raw;
    }

    /**
     * @param array<mixed> $row
     */
    private function postedPositiveInt(array $row, string $key): ?int
    {
        $value = $this->postedNonNegativeInt($row, $key);
        if ($value === null || $value < 1) {
            return null;
        }

        return $value;
    }

    private function globalError(string $code): BulkDraftValidationResult
    {
        return new BulkDraftValidationResult(
            rows: [],
            validCount: 0,
            invalidCount: 0,
            globalError: $code,
        );
    }
}
