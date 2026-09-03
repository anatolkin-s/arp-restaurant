<?php

declare(strict_types=1);

namespace Anatolkin\ArpRestaurant\Backend\Editor\Bulk;

use Anatolkin\ArpRestaurant\Backend\Editor\MinorUnitMoneyFormatter;

/**
 * Parses rectangular TSV clipboard input into preview rows.
 * Does not read or write TYPO3 records.
 */
final class BulkMenuParser
{
    public const DEFAULT_MAX_BYTES = 65536;
    public const DEFAULT_MAX_ROWS = 200;

    /**
     * @var list<string>
     */
    private const HEADER = ['category', 'item', 'variant', 'price'];

    public function __construct(
        private readonly DecimalMinorUnitParser $priceParser,
        private readonly MinorUnitMoneyFormatter $moneyFormatter,
    ) {}

    public function parse(
        string $raw,
        int $maxBytes = self::DEFAULT_MAX_BYTES,
        int $maxRows = self::DEFAULT_MAX_ROWS,
    ): BulkMenuParseResult {
        if (strlen($raw) > $maxBytes) {
            return $this->globalError('inputTooLarge');
        }

        $normalized = str_replace(["\r\n", "\r"], "\n", $raw);
        if (str_starts_with($normalized, "\u{FEFF}")) {
            $normalized = substr($normalized, strlen("\u{FEFF}"));
        }

        $lines = explode("\n", $normalized);
        $dataLineIndexes = [];
        foreach ($lines as $index => $line) {
            if (trim($line) !== '') {
                $dataLineIndexes[] = $index;
            }
        }

        if ($dataLineIndexes === []) {
            return $this->globalError('emptyPaste');
        }

        $firstIndex = $dataLineIndexes[0];
        $hasHeader = $this->isHeaderRow($lines[$firstIndex]);
        $rowIndexes = $hasHeader ? array_slice($dataLineIndexes, 1) : $dataLineIndexes;

        if (count($rowIndexes) > $maxRows) {
            return $this->globalError('tooManyRows');
        }

        if ($rowIndexes === []) {
            return $this->globalError('emptyPaste');
        }

        $rows = [];
        foreach ($rowIndexes as $index) {
            $rows[] = $this->parseRow($index + 1, $lines[$index]);
        }

        return $this->resultFromRows($rows);
    }

    /**
     * @param list<BulkMenuRow> $rows
     */
    private function resultFromRows(array $rows): BulkMenuParseResult
    {
        $validCount = 0;
        $invalidCount = 0;
        foreach ($rows as $row) {
            if ($row->isValid()) {
                ++$validCount;
            } else {
                ++$invalidCount;
            }
        }

        return new BulkMenuParseResult(
            rows: $rows,
            sections: $this->groupConsecutive($rows),
            validCount: $validCount,
            invalidCount: $invalidCount,
        );
    }

    /**
     * @param list<BulkMenuRow> $rows
     * @return list<BulkMenuPreviewSection>
     */
    private function groupConsecutive(array $rows): array
    {
        $sections = [];
        $currentCategory = null;
        $currentRows = [];

        foreach ($rows as $row) {
            $categoryKey = $row->category;
            if ($currentCategory !== null && $categoryKey !== $currentCategory) {
                $sections[] = new BulkMenuPreviewSection($currentCategory, $currentRows);
                $currentRows = [];
            }
            $currentCategory = $categoryKey;
            $currentRows[] = $row;
        }

        if ($currentRows !== [] && $currentCategory !== null) {
            $sections[] = new BulkMenuPreviewSection($currentCategory, $currentRows);
        }

        return $sections;
    }

    private function parseRow(int $sourceLine, string $line): BulkMenuRow
    {
        $cells = explode("\t", $line);
        $category = trim((string)($cells[0] ?? ''));
        $item = trim((string)($cells[1] ?? ''));
        $variant = trim((string)($cells[2] ?? ''));
        $priceRaw = trim((string)($cells[3] ?? ''));

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

        return new BulkMenuRow(
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

    private function isHeaderRow(string $line): bool
    {
        $cells = array_map(
            static fn (string $cell): string => strtolower(trim($cell)),
            explode("\t", $line)
        );
        $cells = array_slice($cells, 0, 4);
        return $cells === self::HEADER;
    }

    private function globalError(string $code): BulkMenuParseResult
    {
        return new BulkMenuParseResult(
            rows: [],
            sections: [],
            validCount: 0,
            invalidCount: 0,
            globalError: $code,
        );
    }
}
