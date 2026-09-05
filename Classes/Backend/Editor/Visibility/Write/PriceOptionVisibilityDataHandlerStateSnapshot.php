<?php

declare(strict_types=1);

namespace Anatolkin\ArpRestaurant\Backend\Editor\Visibility\Write;

/**
 * Pure snapshot of DataHandler public write-result state for PriceOption.hidden.
 *
 * @param list<string> $errorLog
 */
final readonly class PriceOptionVisibilityDataHandlerStateSnapshot
{
    public function __construct(
        public array $errorLog,
    ) {}

    public static function empty(): self
    {
        return new self([]);
    }

    public static function fromDataHandler(object $dataHandler): self
    {
        $errorLog = [];
        if (isset($dataHandler->errorLog) && is_array($dataHandler->errorLog)) {
            foreach ($dataHandler->errorLog as $message) {
                $errorLog[] = (string)$message;
            }
        }

        return new self($errorLog);
    }
}
