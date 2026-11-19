<?php

declare(strict_types=1);

namespace JustinHoltWeb\Dipstick\Http\Controllers;

use CraftCms\Cms\Http\Responses\CpScreenResponse;
use Illuminate\Http\RedirectResponse;
use JustinHoltWeb\Dipstick\Audit\Scans;
use JustinHoltWeb\Dipstick\Findings\Finding;
use JustinHoltWeb\Dipstick\Findings\Rules;
use JustinHoltWeb\Dipstick\Jobs\RunAudit;

use function CraftCms\Cms\cp_url;
use function CraftCms\Cms\currentUserElement;
use function CraftCms\Cms\t;

/**
 * Dipstick in the control panel: the latest audit (or an older one), and a
 * button to run a new one on the queue. Admins only (routes/cp.php).
 */
class ReportController
{
    public function index(Scans $scans): CpScreenResponse
    {
        return $this->screen($scans, $scans->recent(1)[0] ?? null);
    }

    public function show(Scans $scans, int $scanId): CpScreenResponse
    {
        return $this->screen($scans, $scans->find($scanId) ?? abort(404));
    }

    public function run(Scans $scans): RedirectResponse
    {
        if ($scans->inProgress()) {
            return redirect(cp_url('dipstick'))->with('error', t('An audit is already queued or running.', category: 'dipstick'));
        }

        $id = $scans->create('cp', currentUserElement()?->id);
        RunAudit::dispatch($id);

        return redirect(cp_url('dipstick'))->with('success', t('Audit queued. This page shows it when it’s done.', category: 'dipstick'));
    }

    /** @param  array<string, mixed>|null  $scan */
    private function screen(Scans $scans, ?array $scan): CpScreenResponse
    {
        $findings = $scan !== null && $scan['status'] === 'done' ? $scans->findings((int) $scan['id']) : [];

        return new CpScreenResponse()
            ->title(t('Dipstick', category: 'dipstick'))
            ->contentTemplate('dipstick/_report', [
                'scan' => $scan,
                'findings' => array_map(self::present(...), $findings),
                'recent' => $scans->recent(10),
                'busy' => $scans->inProgress(),
                'runUrl' => cp_url('dipstick/run'),
            ]);
    }

    /** @return array<string, mixed> */
    private static function present(Finding $finding): array
    {
        return [
            ...Rules::describe($finding),
            'rule' => $finding->rule,
            'severity' => $finding->severity->value,
            'severityLabel' => $finding->severity->label(),
            'kind' => $finding->kind,
            'source' => $finding->source,
            'site' => $finding->site,
            'target' => $finding->target,
            'occurrences' => $finding->occurrences,
            'fingerprint' => $finding->fingerprint(),
            'evidence' => $finding->evidence,
        ];
    }
}
