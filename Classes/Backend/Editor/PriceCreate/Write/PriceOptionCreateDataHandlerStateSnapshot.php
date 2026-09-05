<?php

declare(strict_types=1);

namespace Anatolkin\ArpRestaurant\Backend\Editor\PriceCreate\Write;

/**
 * Pure snapshot of DataHandler public write-result state.
 * Used after both normal process_datamap return and thrown process_datamap.
 *
 * @param list<string> $errorLog
 * @param array<string, int|string> $substNEWwithIDs
 * @param array<string, string> $substNEWwithIDsTable
 */
final readonly class PriceOptionCreateDataHandlerStateSnapshot
{
    public function __construct(
        public array $errorLog,
        public array $substNEWwithIDs,
        public array $substNEWwithIDsTable,
    ) {}

    public static function empty(): self
    {
        return new self([], [], []);
    }

    public static function fromDataHandler(object $dataHandler): self
    {
        $errorLog = [];
        if (isset($dataHandler->errorLog) && is_array($dataHandler->errorLog)) {
            foreach ($dataHandler->errorLog as $message) {
                $errorLog[] = (string)$message;
            }
        }

        $subst = [];
        if (isset($dataHandler->substNEWwithIDs) && is_array($dataHandler->substNEWwithIDs)) {
            foreach ($dataHandler->substNEWwithIDs as $token => $uid) {
                $subst[(string)$token] = $uid;
            }
        }

        $substTable = [];
        if (isset($dataHandler->substNEWwithIDs_table) && is_array($dataHandler->substNEWwithIDs_table)) {
            foreach ($dataHandler->substNEWwithIDs_table as $token => $table) {
                $substTable[(string)$token] = (string)$table;
            }
        }

        return new self($errorLog, $subst, $substTable);
    }
}
