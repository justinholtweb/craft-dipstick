<?php

declare(strict_types=1);

namespace JustinHoltWeb\Dipstick\Commands;

use CraftCms\Cms\Console\CraftCommand;
use Illuminate\Console\Command;
use JustinHoltWeb\Dipstick\Audit\Auditor;
use JustinHoltWeb\Dipstick\Audit\Scans;
use JustinHoltWeb\Dipstick\Findings\Finding;
use JustinHoltWeb\Dipstick\Findings\Rules;
use JustinHoltWeb\Dipstick\Findings\Score;
use JustinHoltWeb\Dipstick\Findings\Severity;
use Throwable;

/**
 * `craft:dipstick:scan` — the audit from the command line or CI.
 *
 * Exit codes: 0 nothing at or above `--fail-on`, 1 findings at or above it,
 * 2 the audit couldn't run.
 */
class ScanCommand extends Command
{
    use CraftCommand;

    protected $signature = 'craft:dipstick:scan
        {--json : Print the report as JSON}
        {--no-probe : Static scan only; skip the runtime probe}
        {--fail-on=critical : Exit 1 when a finding is at least this severe (critical, warning, notice or none)}';

    protected $description = 'Audits the app, installed plugins and templates for state that leaks between requests under Octane';

    public function handle(Auditor $auditor, Scans $scans): int
    {
        $failOn = (string) $this->option('fail-on');
        $threshold = $failOn === 'none' ? null : Severity::tryFrom($failOn);

        if ($failOn !== 'none' && ($threshold === null || $threshold === Severity::Pass)) {
            $this->error('--fail-on must be critical, warning, notice or none.');

            return 2;
        }

        $id = $scans->create('cli', null, 'running');

        try {
            $findings = $auditor->run($id, $this->option('no-probe') ? false : null);
        } catch (Throwable $e) {
            $this->error('The audit failed: '.$e->getMessage());

            return 2;
        }

        $scan = $scans->find($id) ?? [];
        $failing = $threshold !== null && array_filter($findings, fn (Finding $f) => $f->severity->atLeast($threshold)) !== [];

        if ($this->option('json')) {
            $this->line((string) json_encode([
                'id' => $id,
                'score' => Score::of($findings),
                'grade' => Score::grade(Score::of($findings)),
                'ready' => Score::ready(Score::counts($findings)),
                'counts' => Score::counts($findings),
                'meta' => $scan['meta'] ?? [],
                'findings' => array_map(fn (Finding $f) => [...$f->toArray(), 'title' => Rules::describe($f)['title']], $findings),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return $failing ? 1 : 0;
        }

        $counts = Score::counts($findings);
        $score = Score::of($findings);
        $this->info(sprintf('Octane readiness: %d/100 (%s) — %d critical, %d warning, %d notice', $score, Score::grade($score), $counts['critical'], $counts['warning'], $counts['notice']));

        $rows = [];

        foreach ($findings as $finding) {
            if ($finding->severity === Severity::Pass && ! $this->output->isVerbose()) {
                continue;
            }

            $first = $finding->evidence[0] ?? [];
            $where = isset($first['file']) ? $first['file'].(isset($first['line']) && $first['line'] ? ':'.$first['line'] : '') : ($first['holder'] ?? '');
            $rows[] = [$finding->severity->value, Rules::describe($finding)['title'], $finding->source.($finding->site ? " ({$finding->site})" : ''), $where, $finding->fingerprint()];
        }

        if ($rows !== []) {
            $this->table(['Severity', 'Finding', 'Source', 'First seen at', 'Fingerprint'], $rows);
        }

        $this->line("Full report: Dipstick in the control panel (audit #{$id}). Ignore a finding by adding its fingerprint to the Ignore setting.");

        return $failing ? 1 : 0;
    }
}
