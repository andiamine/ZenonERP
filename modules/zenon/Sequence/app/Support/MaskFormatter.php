<?php

namespace Modules\Sequence\Support;

use Illuminate\Support\Carbon;

/**
 * Pure mask renderer (CLAUDE.md §9.2 — Odoo/Dolibarr numbering-mask lineage). No I/O, no
 * state: the caller supplies the number, company code and (optional) period string.
 *
 * Tokens: {seq} raw counter, {seq:N} zero-padded to N, {year} 4-digit year, {year2}
 * 2-digit year, {month} 01–12, {company} company code (empty for tenant-wide). Unknown
 * tokens are left literal.
 *
 * The period string drives {year}/{month}: 'YYYY' (fiscal-year reset) or 'YYYY-MM' (month
 * reset). When it is null (a never-resetting sequence) the date tokens fall back to the
 * current calendar date — a non-resetting sequence has no fiscal boundary of its own.
 */
final class MaskFormatter
{
    public static function format(string $mask, int $number, string $companyCode, ?string $period): string
    {
        $now = Carbon::now();

        $year = ($period !== null && preg_match('/^(\d{4})/', $period, $m) === 1)
            ? $m[1]
            : $now->format('Y');

        $month = ($period !== null && preg_match('/^\d{4}-(\d{2})/', $period, $m) === 1)
            ? $m[1]
            : $now->format('m');

        $rendered = preg_replace_callback(
            '/\{([a-zA-Z][a-zA-Z0-9]*)(?::(\d+))?\}/',
            static function (array $match) use ($number, $year, $month, $companyCode): string {
                $token = $match[1];
                $pad = isset($match[2]) ? (int) $match[2] : null;

                return match ($token) {
                    'seq' => $pad !== null
                        ? str_pad((string) $number, $pad, '0', STR_PAD_LEFT)
                        : (string) $number,
                    'year' => $year,
                    'year2' => substr($year, -2),
                    'month' => $month,
                    'company' => $companyCode,
                    default => $match[0], // unknown token → left literal
                };
            },
            $mask,
        );

        return $rendered ?? $mask;
    }
}
