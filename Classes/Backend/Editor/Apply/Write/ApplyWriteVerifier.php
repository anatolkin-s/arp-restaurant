<?php

declare(strict_types=1);

namespace Anatolkin\ArpRestaurant\Backend\Editor\Apply\Write;

/**
 * Pure post-write verification over expected creates + fetched rows.
 *
 * @param array<string, int> $substNEWwithIDs NEW token => uid
 * @param array<string, string> $substNEWwithIDsTable NEW token => table
 * @param list<string> $errorLog
 * @param array<string, array<string, mixed>> $fetchedRows key "table:uid" => row
 */
final class ApplyWriteVerifier
{
    /**
     * @param array<string, int> $substNEWwithIDs
     * @param array<string, string> $substNEWwithIDsTable
     * @param list<string> $errorLog
     * @param array<string, array<string, mixed>> $fetchedRows
     */
    public function verify(
        ApplyDataMap $dataMap,
        array $substNEWwithIDs,
        array $substNEWwithIDsTable,
        array $errorLog,
        array $fetchedRows,
        int $expectedPid,
    ): ApplyExecutionResult {
        $verifiedByKind = [
            'category' => 0,
            'item' => 0,
            'placement' => 0,
            'priceoption' => 0,
        ];
        $createdUids = [];
        $diagnostics = [];
        $verifiedCount = 0;
        $expectedCount = count($dataMap->expectedCreates);

        foreach ($dataMap->expectedCreates as $expected) {
            $token = $expected->newToken;
            if (!isset($substNEWwithIDs[$token])) {
                $diagnostics[] = 'missingNewMapping:' . $expected->entityKind;
                continue;
            }
            $uid = (int)$substNEWwithIDs[$token];
            if ($uid <= 0) {
                $diagnostics[] = 'invalidMappedUid:' . $expected->entityKind;
                continue;
            }
            if (
                isset($substNEWwithIDsTable[$token])
                && $substNEWwithIDsTable[$token] !== $expected->table
            ) {
                $diagnostics[] = 'wrongTableMapping:' . $expected->entityKind;
                continue;
            }

            $rowKey = $expected->table . ':' . $uid;
            $row = $fetchedRows[$rowKey] ?? null;
            if (!is_array($row)) {
                $diagnostics[] = 'missingFetchedRow:' . $expected->entityKind;
                continue;
            }

            $mismatch = $this->rowMismatch($expected, $row, $expectedPid, $substNEWwithIDs);
            if ($mismatch !== null) {
                $diagnostics[] = $mismatch;
                continue;
            }

            ++$verifiedCount;
            ++$verifiedByKind[$expected->entityKind];
            $createdUids[$expected->localRef] = $uid;
        }

        $hasErrors = $errorLog !== [];
        if ($hasErrors) {
            foreach (array_slice($errorLog, 0, 5) as $message) {
                $diagnostics[] = $this->normalizeDiagnostic((string)$message);
            }
        }

        if ($verifiedCount === $expectedCount && $expectedCount > 0 && !$hasErrors) {
            return new ApplyExecutionResult(
                outcome: 'applied',
                createdCategories: $verifiedByKind['category'],
                createdItems: $verifiedByKind['item'],
                createdPlacements: $verifiedByKind['placement'],
                createdPriceOptions: $verifiedByKind['priceoption'],
                dataHandlerAttempted: false,
                diagnostics: [],
                createdUidsByLocalRef: $createdUids,
            );
        }

        if ($verifiedCount === 0) {
            return new ApplyExecutionResult(
                outcome: 'failed',
                createdCategories: 0,
                createdItems: 0,
                createdPlacements: 0,
                createdPriceOptions: 0,
                dataHandlerAttempted: false,
                diagnostics: $diagnostics === [] ? ['applyFailed'] : array_values(array_unique($diagnostics)),
                createdUidsByLocalRef: [],
            );
        }

        return new ApplyExecutionResult(
            outcome: 'partialFailure',
            createdCategories: $verifiedByKind['category'],
            createdItems: $verifiedByKind['item'],
            createdPlacements: $verifiedByKind['placement'],
            createdPriceOptions: $verifiedByKind['priceoption'],
            dataHandlerAttempted: false,
            diagnostics: array_values(array_unique($diagnostics)),
            createdUidsByLocalRef: $createdUids,
        );
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, int> $substNEWwithIDs
     */
    private function rowMismatch(
        ApplyExpectedCreate $expected,
        array $row,
        int $expectedPid,
        array $substNEWwithIDs,
    ): ?string {
        if ((int)($row['uid'] ?? 0) <= 0) {
            return 'invalidUid:' . $expected->entityKind;
        }
        if ((int)($row['pid'] ?? -1) !== $expectedPid) {
            return 'wrongPid:' . $expected->entityKind;
        }
        if ((int)($row['sys_language_uid'] ?? -1) !== 0) {
            return 'nonzeroLanguage:' . $expected->entityKind;
        }
        if ((int)($row['deleted'] ?? 1) !== 0) {
            return 'deletedRecord:' . $expected->entityKind;
        }
        if (!ApplyPublicUuid::isUsable(trim((string)($row['public_uuid'] ?? '')))) {
            return 'invalidUuid:' . $expected->entityKind;
        }

        foreach ($expected->expectedFields as $field => $expectedValue) {
            if ($field === 'pid' || $field === 'sys_language_uid' || $field === 'sorting') {
                // sorting is write-time detail; pid/language already checked
                if ($field === 'sorting') {
                    continue;
                }
                continue;
            }

            $actual = $row[$field] ?? null;
            if (is_string($expectedValue) && str_starts_with($expectedValue, 'NEW')) {
                $mapped = $substNEWwithIDs[$expectedValue] ?? null;
                if ($mapped === null || (int)$actual !== (int)$mapped) {
                    return 'relationMismatch:' . $expected->entityKind . ':' . $field;
                }
                continue;
            }

            if ((string)$actual !== (string)$expectedValue) {
                // integer-ish compare for amount / menu / category / item
                if (is_int($expectedValue) || ctype_digit((string)$expectedValue)) {
                    if ((int)$actual !== (int)$expectedValue) {
                        return 'fieldMismatch:' . $expected->entityKind . ':' . $field;
                    }
                    continue;
                }

                return 'fieldMismatch:' . $expected->entityKind . ':' . $field;
            }
        }

        return null;
    }

    private function normalizeDiagnostic(string $message): string
    {
        $trimmed = trim($message);
        if ($trimmed === '') {
            return 'dataHandlerError';
        }
        if (strlen($trimmed) > 200) {
            return substr($trimmed, 0, 200);
        }

        return $trimmed;
    }
}
