<?php

declare(strict_types=1);

namespace JustinHoltWeb\Dipstick\Findings;

use function CraftCms\Cms\t;

/**
 * How urgent a finding is. Critical: state from one request reaches another
 * (a user, site or request). Warning: shared state that will drift or grow.
 * Notice: worth a look. Pass: checked and fine.
 */
enum Severity: string
{
    case Critical = 'critical';
    case Warning = 'warning';
    case Notice = 'notice';
    case Pass = 'pass';

    /** Points each finding takes off the score (out of 100), as Microscope does. */
    public function cost(): int
    {
        return match ($this) {
            self::Critical => 25,
            self::Warning => 10,
            self::Notice => 3,
            self::Pass => 0,
        };
    }

    public function rank(): int
    {
        return match ($this) {
            self::Critical => 0,
            self::Warning => 1,
            self::Notice => 2,
            self::Pass => 3,
        };
    }

    /** Whether this severity is at least as urgent as `$threshold`. */
    public function atLeast(self $threshold): bool
    {
        return $this->rank() <= $threshold->rank();
    }

    public function label(): string
    {
        return match ($this) {
            self::Critical => t('Critical', category: 'dipstick'),
            self::Warning => t('Warning', category: 'dipstick'),
            self::Notice => t('Notice', category: 'dipstick'),
            self::Pass => t('Pass', category: 'dipstick'),
        };
    }

    /** @return list<self> Most urgent first. */
    public static function ordered(): array
    {
        return [self::Critical, self::Warning, self::Notice, self::Pass];
    }
}
