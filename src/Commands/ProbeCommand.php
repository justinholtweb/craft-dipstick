<?php

declare(strict_types=1);

namespace JustinHoltWeb\Dipstick\Commands;

use CraftCms\Cms\Console\CraftCommand;
use Illuminate\Console\Command;
use JustinHoltWeb\Dipstick\Findings\Finding;
use JustinHoltWeb\Dipstick\Findings\Rules;
use JustinHoltWeb\Dipstick\Probe\ProbeRunner;
use JustinHoltWeb\Dipstick\Probe\RuntimeProbe;
use JustinHoltWeb\Dipstick\Probe\Sandbox;
use Throwable;

/**
 * `craft:dipstick:probe` — the runtime probe on its own, in this process.
 * Audits run it as a child process; it's also handy for seeing probe errors.
 * Nothing it does is kept: see {@see Sandbox}.
 */
class ProbeCommand extends Command
{
    use CraftCommand;

    protected $signature = 'craft:dipstick:probe
        {--json : Print machine-readable output (used by audits)}
        {--requests= : Simulated requests per site (2–10)}
        {--no-render : Don’t render pages, only simulate the request lifecycle}';

    protected $description = 'Simulates several requests per site in one process and reports state carried between them';

    public function handle(RuntimeProbe $probe): int
    {
        $requests = $this->option('requests') === null ? null : max(2, min(10, (int) $this->option('requests')));

        try {
            $result = $probe->run($requests, $this->option('no-render') ? false : null);
        } catch (Throwable $e) {
            if ($this->option('json')) {
                $this->line(ProbeRunner::MARKER.json_encode(['error' => $e::class.': '.$e->getMessage()]));
            } else {
                $this->error('The probe failed: '.$e->getMessage());
            }

            return 2;
        }

        if ($this->option('json')) {
            $this->line(ProbeRunner::MARKER.json_encode([
                'findings' => array_map(fn (Finding $f) => $f->toArray(), $result['findings']),
                'meta' => $result['meta'],
            ], JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE));

            return 0;
        }

        $this->table(['Severity', 'Finding', 'Site', 'Holder'], array_map(fn (Finding $f) => [
            $f->severity->value, Rules::describe($f)['title'], (string) $f->site, (string) $f->target,
        ], $result['findings']));

        return 0;
    }
}
