<?php

declare(strict_types=1);

namespace JustinHoltWeb\Dipstick\Findings;

/**
 * Readiness score: 100 minus each finding's cost (critical 25, warning 10,
 * notice 3), never below 0. “Ready” means no critical or warning findings.
 */
final class Score
{
    /** @param  iterable<Finding>  $findings */
    public static function of(iterable $findings): int
    {
        $score = 100;

        foreach ($findings as $finding) {
            $score -= $finding->severity->cost();
        }

        return max(0, $score);
    }

    public static function grade(int $score): string
    {
        return match (true) {
            $score >= 90 => 'A',
            $score >= 80 => 'B',
            $score >= 70 => 'C',
            $score >= 60 => 'D',
            default => 'F',
        };
    }

    /**
     * @param  iterable<Finding>  $findings
     * @return array{critical: int, warning: int, notice: int, pass: int}
     */
    public static function counts(iterable $findings): array
    {
        $counts = ['critical' => 0, 'warning' => 0, 'notice' => 0, 'pass' => 0];

        foreach ($findings as $finding) {
            $counts[$finding->severity->value]++;
        }

        return $counts;
    }

    /** @param  array{critical: int, warning: int, notice: int, pass: int}  $counts */
    public static function ready(array $counts): bool
    {
        return $counts['critical'] === 0 && $counts['warning'] === 0;
    }
}
