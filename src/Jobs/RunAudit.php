<?php

declare(strict_types=1);

namespace JustinHoltWeb\Dipstick\Jobs;

use CraftCms\Cms\Queue\Job;
use JustinHoltWeb\Dipstick\Audit\Auditor;
use JustinHoltWeb\Dipstick\Audit\Scans;

use function CraftCms\Cms\t;

/**
 * An audit started from the control panel. The probe runs in its own process,
 * so this worker is never touched by the simulated requests.
 */
class RunAudit extends Job
{
    public int $timeout = 600;

    public int $tries = 1;

    public function __construct(public int $scanId)
    {
        parent::__construct();
    }

    public function handle(Auditor $auditor, Scans $scans): void
    {
        if ($scans->find($this->scanId) === null) {
            return;
        }

        $auditor->run($this->scanId);
    }

    protected function defaultDescription(): string
    {
        return t('Auditing for Octane', category: 'dipstick');
    }
}
