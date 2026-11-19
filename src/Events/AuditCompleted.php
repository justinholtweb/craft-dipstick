<?php

declare(strict_types=1);

namespace JustinHoltWeb\Dipstick\Events;

use JustinHoltWeb\Dipstick\Findings\Finding;

/**
 * Fired after an audit is stored. For other tools (Microscope, CI hooks) that
 * want Dipstick's findings: rule handles, severities, sources and evidence.
 */
final class AuditCompleted
{
    /** @param  list<Finding>  $findings */
    public function __construct(
        public readonly int $scanId,
        public readonly array $findings,
    ) {}
}
