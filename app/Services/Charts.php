<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Server-rendered inline-SVG charts for the dashboard. Output is safe to
 * echo: every label is escaped, colours must be #rrggbb, and only SVG
 * presentation attributes (allowed by the strict CSP) and classes are used.
 * Callers add an adjacent HTML legend and a visually-hidden table, so the
 * numbers never depend on colour or on the picture alone.
 */
final class Charts
{
    private const GRID = '#e7e7e4';
    private const INK = '#5a625f';
    private const SURFACE = '#ffffff';

    /**
     * Donut chart.
     *
     * @param list<array{label: string, value: int, color: string}> $slices
     * @param string $centerLabel Small caption under the total in the middle.
     */
    public static function donut(array $slices, string $ariaLabel, string $centerLabel = ''): string
    {
        $total = 0;
        foreach ($slices as $s) {
            $total += max(0, $s['value']);
        }
        $r = 70.0;
        $c = 2 * M_PI * $r;
        $svg = '<svg class="chart chart--donut" viewBox="0 0 200 200" role="img" aria-label="' . e($ariaLabel) . '" focusable="false">';
        $svg .= '<circle cx="100" cy="100" r="' . $r . '" fill="none" stroke="' . self::GRID . '" stroke-width="26"/>';
        if ($total > 0) {
            $offset = 0.0;
            $visible = array_values(array_filter($slices, static fn (array $s): bool => $s['value'] > 0));
            $gap = count($visible) > 1 ? 2.0 : 0.0;
            foreach ($visible as $s) {
                $len = $c * $s['value'] / $total;
                $dash = max(0.01, $len - $gap);
                $svg .= sprintf(
                    '<circle cx="100" cy="100" r="%s" fill="none" stroke="%s" stroke-width="26" stroke-dasharray="%.3F %.3F" stroke-dashoffset="%.3F" transform="rotate(-90 100 100)"><title>%s</title></circle>',
                    $r,
                    self::color($s['color']),
                    $dash,
                    $c - $dash,
                    -$offset,
                    e($s['label'] . ': ' . $s['value'])
                );
                $offset += $len;
            }
        }
        $svg .= '<text class="chart__total" x="100" y="102" text-anchor="middle" font-size="30" font-weight="700" fill="#242424">' . $total . '</text>';
        if ($centerLabel !== '') {
            $svg .= '<text class="chart__caption" x="100" y="122" text-anchor="middle" font-size="11" fill="' . self::INK . '">' . e($centerLabel) . '</text>';
        }
        return $svg . '</svg>';
    }

    /**
     * Vertical bar chart with a y axis.
     *
     * @param list<string> $labels
     * @param list<int> $values
     * @param list<string>|null $tickLabels Shorter x axis labels (tooltips keep the full label).
     */
    public static function bars(array $labels, array $values, string $ariaLabel, string $color = '#9d1d2a', ?array $tickLabels = null): string
    {
        $w = 260;
        $h = 210;
        $left = 26;
        $right = 4;
        $top = 10;
        $bottom = 24;
        $plotW = $w - $left - $right;
        $plotH = $h - $top - $bottom;
        $max = max(1, ...($values === [] ? [1] : $values));
        [$step, $ticks] = self::niceScale($max);
        $axisMax = $step * $ticks;

        $svg = '<svg class="chart chart--bars" viewBox="0 0 ' . $w . ' ' . $h . '" role="img" aria-label="' . e($ariaLabel) . '" focusable="false">';
        for ($i = 0; $i <= $ticks; $i++) {
            $v = $i * $step;
            $y = $top + $plotH - ($plotH * $v / $axisMax);
            $svg .= sprintf('<line x1="%d" y1="%.2F" x2="%d" y2="%.2F" stroke="%s" stroke-width="1"/>', $left, $y, $w - $right, $y, self::GRID);
            $svg .= sprintf('<text x="%d" y="%.2F" text-anchor="end" font-size="10" fill="%s">%d</text>', $left - 5, $y + 4, self::INK, $v);
        }
        $n = max(1, count($values));
        $slot = $plotW / $n;
        $barW = min(18.0, $slot * 0.62);
        foreach ($values as $i => $v) {
            $x = $left + $slot * $i + ($slot - $barW) / 2;
            $bh = $plotH * $v / $axisMax;
            $label = (string) ($labels[$i] ?? '');
            if ($v > 0) {
                $yTop = $top + $plotH - $bh;
                $r = min(4.0, $bh, $barW / 2);
                $svg .= sprintf(
                    '<path d="M%.2F %.2F V%.2F a%.2F %.2F 0 0 1 %.2F -%.2F H%.2F a%.2F %.2F 0 0 1 %.2F %.2F V%.2F Z" fill="%s"><title>%s</title></path>',
                    $x, $top + $plotH, $yTop + $r, $r, $r, $r, $r, $x + $barW - $r, $r, $r, $r, $r, $top + $plotH, self::color($color), e($label . ': ' . $v)
                );
            }
            $svg .= sprintf('<text x="%.2F" y="%d" text-anchor="middle" font-size="10" fill="%s">%s</text>', $x + $barW / 2, $h - 8, self::INK, e((string) ($tickLabels[$i] ?? $label)));
        }
        return $svg . '</svg>';
    }

    /** @return array{0: int, 1: int} step and number of intervals so the axis covers $max. */
    private static function niceScale(int $max): array
    {
        foreach ([1, 2, 5, 10, 20, 25, 50, 100, 200, 250, 500, 1000, 2000, 5000, 10000, 100000] as $step) {
            if ($max / $step <= 5) {
                return [$step, max(1, (int) ceil($max / $step))];
            }
        }
        return [100000, (int) ceil($max / 100000)];
    }

    private static function color(string $color): string
    {
        return preg_match('/^#[0-9a-fA-F]{6}$/', $color) === 1 ? $color : '#818b89';
    }
}
