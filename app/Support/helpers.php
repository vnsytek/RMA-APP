<?php

use Carbon\CarbonInterface;

if (! function_exists('money_vnd')) {
    /**
     * Format an amount of Vietnamese dong, e.g. "1.200.000 ₫".
     */
    function money_vnd(int|float|null $amount): string
    {
        return number_format((float) ($amount ?? 0), 0, ',', '.').' ₫';
    }
}

if (! function_exists('vn_date')) {
    /**
     * Format a date as dd/mm/yyyy, or return the fallback when empty.
     */
    function vn_date(?CarbonInterface $date, string $fallback = ''): string
    {
        return $date?->format('d/m/Y') ?? $fallback;
    }
}

if (! function_exists('text_lines')) {
    /**
     * Split multi-line text into its non-empty, trimmed lines.
     *
     * @return list<string>
     */
    function text_lines(?string $text): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $text)), fn (string $line) => $line !== ''));
    }
}
