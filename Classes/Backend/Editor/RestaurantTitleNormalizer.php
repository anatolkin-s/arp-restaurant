<?php

declare(strict_types=1);

namespace Anatolkin\ArpRestaurant\Backend\Editor;

/**
 * Conservative restaurant title normalization.
 *
 * DISPLAY VALUE (cleanDisplayTitle): whitespace-normalized, case-preserving.
 * MATCH KEY (matchKey): cleanDisplayTitle + Unicode case fold.
 *
 * No transliteration, accent stripping, punctuation removal, or fuzzy matching.
 *
 * Production TYPO3 requires ext-mbstring; matchKey prefers MB_CASE_FOLD.
 * A UTF-8-safe ASCII fold fallback keeps the pure PHP test runner working
 * when mbstring is unavailable.
 */
final class RestaurantTitleNormalizer
{
    /**
     * Unicode-safe trim and collapse of whitespace / separator runs to one space.
     * Preserves capitalization, punctuation, accents, and script.
     */
    public function cleanDisplayTitle(string $value): string
    {
        $collapsed = preg_replace('/[\p{Z}\s]+/u', ' ', $value);
        if (!is_string($collapsed)) {
            return '';
        }

        return trim($collapsed, " \t\n\r\0\x0B");
    }

    /**
     * Identity comparison key: whitespace-normalized + Unicode case-folded.
     */
    public function matchKey(string $value): string
    {
        $clean = $this->cleanDisplayTitle($value);
        if (\function_exists('mb_convert_case') && \defined('MB_CASE_FOLD')) {
            return \mb_convert_case($clean, \MB_CASE_FOLD, 'UTF-8');
        }

        return $this->asciiCaseFoldUtf8($clean);
    }

    /**
     * Fold Basic Latin A–Z within a UTF-8 string without corrupting multi-byte
     * sequences. Used only when ext-mbstring is absent.
     */
    private function asciiCaseFoldUtf8(string $value): string
    {
        $out = '';
        $length = strlen($value);
        for ($i = 0; $i < $length; ++$i) {
            $byte = ord($value[$i]);
            if ($byte < 0x80) {
                if ($byte >= 0x41 && $byte <= 0x5A) {
                    $out .= chr($byte + 32);
                } else {
                    $out .= $value[$i];
                }
                continue;
            }
            if (($byte & 0xE0) === 0xC0 && $i + 1 < $length) {
                $out .= $value[$i] . $value[$i + 1];
                $i += 1;
                continue;
            }
            if (($byte & 0xF0) === 0xE0 && $i + 2 < $length) {
                $out .= $value[$i] . $value[$i + 1] . $value[$i + 2];
                $i += 2;
                continue;
            }
            if (($byte & 0xF8) === 0xF0 && $i + 3 < $length) {
                $out .= $value[$i] . $value[$i + 1] . $value[$i + 2] . $value[$i + 3];
                $i += 3;
                continue;
            }
            $out .= $value[$i];
        }

        return $out;
    }
}
