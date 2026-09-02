<?php

declare(strict_types=1);

namespace Anatolkin\ArpRestaurant\Backend\RecordLabel;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Utility\MathUtility;

/**
 * Backend-only record titles for IRRE and record lists.
 * This is not a domain model.
 */
final class RecordLabelProvider
{
    private const ITEM_TABLE = 'tx_arprestaurant_domain_model_item';

    /**
     * TYPO3 13.4 / 14.3 label_userFunc and formattedLabel_userFunc signature.
     *
     * @param array<string, mixed> $parameters
     */
    public function getPlacementTitle(array &$parameters): void
    {
        $row = is_array($parameters['row'] ?? null) ? $parameters['row'] : [];
        $title = $this->resolveItemTitle($row);
        if ($title === '') {
            $title = trim((string)($row['public_uuid'] ?? ''));
        }
        if ($title === '') {
            $title = $this->fallbackUid($row);
        }
        $parameters['title'] = $title;
    }

    /**
     * @param array<string, mixed> $parameters
     */
    public function getPriceOptionTitle(array &$parameters): void
    {
        $row = is_array($parameters['row'] ?? null) ? $parameters['row'] : [];
        $label = trim((string)($row['label'] ?? ''));
        if ($label !== '') {
            $parameters['title'] = $label;
            return;
        }

        if (isset($row['amount']) && MathUtility::canBeInterpretedAsInteger((string)$row['amount'])) {
            $parameters['title'] = sprintf($this->translateAmountFallback(), (string)(int)$row['amount']);
            return;
        }

        $uuid = trim((string)($row['public_uuid'] ?? ''));
        $parameters['title'] = $uuid !== '' ? $uuid : $this->fallbackUid($row);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function resolveItemTitle(array $row): string
    {
        $itemUid = $this->extractUid($row['item'] ?? 0);
        if ($itemUid <= 0) {
            return '';
        }

        $item = BackendUtility::getRecord(self::ITEM_TABLE, $itemUid, 'uid,title');
        if (!is_array($item)) {
            return '';
        }

        $languageUid = (int)($row['sys_language_uid'] ?? 0);
        if ($languageUid > 0) {
            $localized = BackendUtility::getRecordLocalization(self::ITEM_TABLE, $itemUid, $languageUid);
            if (is_array($localized) && $localized !== [] && is_array($localized[0])) {
                $localizedTitle = trim((string)($localized[0]['title'] ?? ''));
                if ($localizedTitle !== '') {
                    return $localizedTitle;
                }
            }
        }

        return trim((string)($item['title'] ?? ''));
    }

    private function extractUid(mixed $value): int
    {
        if (is_array($value)) {
            if (isset($value['uid'])) {
                return (int)$value['uid'];
            }
            $first = reset($value);
            return is_array($first) ? (int)($first['uid'] ?? 0) : (int)$first;
        }

        return MathUtility::canBeInterpretedAsInteger((string)$value) ? (int)$value : 0;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function fallbackUid(array $row): string
    {
        $uid = $row['uid'] ?? '';
        if ($uid === '' || $uid === null) {
            return '';
        }

        return (string)$uid;
    }

    private function translateAmountFallback(): string
    {
        $key = 'LLL:EXT:arp_restaurant/Resources/Private/Language/locallang_db.xlf:tx_arprestaurant_domain_model_priceoption.amountFallback';
        $languageService = $GLOBALS['LANG'] ?? null;
        if ($languageService instanceof LanguageService) {
            $translated = trim($languageService->sL($key));
            if ($translated !== '' && $translated !== $key) {
                return $translated;
            }
        }

        return '%s minor units';
    }
}
