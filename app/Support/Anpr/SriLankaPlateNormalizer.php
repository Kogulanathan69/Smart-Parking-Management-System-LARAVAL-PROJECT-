<?php

namespace App\Support\Anpr;

/**
 * Sri Lankan number plate normalisation for OCR output.
 *
 * Formats handled (documented, not exhaustive — unknown formats are kept and flagged for staff):
 *   modern            2–3 letters + 4 digits            CAB-1234, KX-4455
 *   modern_province   province code + modern            WP CAB-1234 (WP, CP, SP, NP, EP, NW, NC, UP, SG)
 *   legacy_numeric    1–3 digit series + 4 digits       19-1234, 250-1234 (the "ශ්‍රී" separator is accepted)
 *   unknown           anything else alphanumeric (4–10 chars), e.g. special/diplomatic plates
 *
 * Rules:
 *   - The raw text is never modified in place; the caller stores it separately.
 *   - Letter/digit confusion (O/0, I/1, B/8, S/5, Z/2, D/0, G/6) is only fixed when the result then matches a
 *     known format by position, and the result is flagged `confusion_corrected` → staff must verify.
 *   - This class never consults the vehicle register: it cannot "snap" a reading to a similar registered plate.
 */
final class SriLankaPlateNormalizer
{
    public const PROVINCES = ['WP', 'CP', 'SP', 'NP', 'EP', 'NW', 'NC', 'UP', 'SG'];

    private const LETTER_FOR_DIGIT = ['0' => 'O', '1' => 'I', '8' => 'B', '5' => 'S', '2' => 'Z', '6' => 'G'];

    private const DIGIT_FOR_LETTER = ['O' => '0', 'Q' => '0', 'D' => '0', 'I' => '1', 'L' => '1', 'B' => '8', 'S' => '5', 'Z' => '2', 'G' => '6'];

    /**
     * @return array{normalized: ?string, display: ?string, format: string, province: ?string, confusion_corrected: bool, valid: bool}
     */
    public function normalize(?string $raw): array
    {
        $empty = ['normalized' => null, 'display' => null, 'format' => 'unreadable', 'province' => null, 'confusion_corrected' => false, 'valid' => false];
        if ($raw === null || trim($raw) === '') {
            return $empty;
        }

        // Treat the Sinhala "Sri" symbol and any punctuation/whitespace as separators.
        $text = mb_strtoupper(trim($raw));
        $text = str_replace(['ශ්‍රී', 'ශ්රී', 'ශ්‍ර', 'ශ්‍'], '-', $text);
        $tokens = preg_split('/[^A-Z0-9]+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        $compact = implode('', $tokens);
        if ($compact === '' || strlen($compact) < 3 || strlen($compact) > 12) {
            return $empty;
        }

        foreach ([false, true] as $allowConfusion) {
            if ($result = $this->match($compact, $tokens, $allowConfusion)) {
                return $result;
            }
        }

        return [
            'normalized' => $compact,
            'display' => implode('-', $tokens),
            'format' => 'unknown',
            'province' => null,
            'confusion_corrected' => false,
            'valid' => strlen($compact) >= 4 && strlen($compact) <= 10,
        ];
    }

    /**
     * Normalise several engine candidates, keeping order and dropping unreadable/duplicate ones.
     *
     * @param  list<array{text: string, confidence_bp?: int}>  $candidates
     * @return list<array<string, mixed>>
     */
    public function normalizeCandidates(array $candidates): array
    {
        $out = [];
        foreach ($candidates as $candidate) {
            $n = $this->normalize($candidate['text'] ?? null);
            if (! $n['normalized'] || isset($out[$n['normalized']])) {
                continue;
            }
            $out[$n['normalized']] = $n + [
                'raw' => $candidate['text'],
                'confidence_bp' => (int) ($candidate['confidence_bp'] ?? 0),
                'detector_confidence_bp' => isset($candidate['detector_confidence_bp']) ? (int) $candidate['detector_confidence_bp'] : null,
            ];
        }

        return array_values($out);
    }

    private function match(string $compact, array $tokens, bool $allowConfusion): ?array
    {
        // Province prefix: only when it is a separate leading token or clearly followed by a modern number.
        $province = null;
        $body = $compact;
        $first = $tokens[0] ?? '';
        if (in_array(substr($compact, 0, 2), self::PROVINCES, true) && (in_array($first, self::PROVINCES, true) || strlen($compact) >= 8)) {
            $province = substr($compact, 0, 2);
            $body = substr($compact, 2);
        }

        // modern: 2–3 letters + 4 digits
        foreach ([3, 2] as $letters) {
            if (strlen($body) !== $letters + 4) {
                continue;
            }
            [$l, $d] = [substr($body, 0, $letters), substr($body, $letters)];
            [$l2, $d2, $changed] = [$l, $d, false];
            if ($allowConfusion) {
                $l2 = strtr($l, self::LETTER_FOR_DIGIT);
                $d2 = strtr($d, self::DIGIT_FOR_LETTER);
                $changed = $l2 !== $l || $d2 !== $d;
            }
            if (preg_match('/^[A-Z]+$/', $l2) && preg_match('/^\d{4}$/', $d2) && ($allowConfusion ? $changed : true)) {
                return [
                    'normalized' => ($province ?? '').$l2.$d2,
                    'display' => trim(($province ? $province.' ' : '').$l2.'-'.$d2),
                    'format' => $province ? 'modern_province' : 'modern',
                    'province' => $province,
                    'confusion_corrected' => $changed,
                    'valid' => true,
                ];
            }
        }

        // legacy numeric: 1–3 digits + 4 digits; require a separator so "191234" stays ambiguous unless split.
        if (! $province && count($tokens) === 2 && preg_match('/^\d{1,3}$/', $allowConfusion ? strtr($tokens[0], self::DIGIT_FOR_LETTER) : $tokens[0])) {
            $series = $allowConfusion ? strtr($tokens[0], self::DIGIT_FOR_LETTER) : $tokens[0];
            $number = $allowConfusion ? strtr($tokens[1], self::DIGIT_FOR_LETTER) : $tokens[1];
            $changed = $series !== $tokens[0] || $number !== $tokens[1];
            if (preg_match('/^\d{4}$/', $number) && ($allowConfusion ? $changed : true)) {
                return [
                    'normalized' => $series.$number,
                    'display' => $series.'-'.$number,
                    'format' => 'legacy_numeric',
                    'province' => null,
                    'confusion_corrected' => $changed,
                    'valid' => true,
                ];
            }
        }

        return null;
    }

    /**
     * Identifier as stored on vehicles (same rule as Vehicle::normalizePlate) — exact matching only.
     */
    public static function identifier(string $plate): string
    {
        return strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $plate));
    }
}
