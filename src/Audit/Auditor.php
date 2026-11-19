<?php

declare(strict_types=1);

namespace JustinHoltWeb\Dipstick\Audit;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use JustinHoltWeb\Dipstick\Events\AuditCompleted;
use JustinHoltWeb\Dipstick\Findings\Finding;
use JustinHoltWeb\Dipstick\Findings\Severity;
use JustinHoltWeb\Dipstick\Probe\ProbeRunner;
use JustinHoltWeb\Dipstick\Scan\SourceLocator;
use JustinHoltWeb\Dipstick\Scan\StaticScanner;
use JustinHoltWeb\Dipstick\Settings;
use Throwable;

use function CraftCms\Cms\t;

/**
 * Runs an audit: the static scan, then (if enabled) the runtime probe in a
 * child process, and stores the result.
 */
class Auditor
{
    public function __construct(
        private readonly Settings $settings,
        private readonly SourceLocator $locator,
        private readonly Scans $scans,
        private readonly ProbeRunner $probe,
    ) {}

    /**
     * @return list<Finding>
     */
    public function run(int $scanId, ?bool $probe = null): array
    {
        $started = hrtime(true);
        $probe ??= $this->settings->probeEnabled;
        $this->scans->markRunning($scanId);

        try {
            $scanner = new StaticScanner()->configure($this->settings->excludeList(), $this->settings->ignoreList());
            $findings = $scanner->scan($this->locator->sources());
            $meta = [
                'files.php' => $scanner->counts['php'],
                'files.blade' => $scanner->counts['blade'],
                'files.twig' => $scanner->counts['twig'],
                'octane' => class_exists('Laravel\Octane\Octane'),
            ];

            if ($probe) {
                try {
                    $result = $this->probe->run();
                    $ignored = array_flip($this->settings->ignoreList());
                    $findings = [...$findings, ...array_values(array_filter($result['findings'], fn (Finding $f) => ! isset($ignored[$f->fingerprint()])))];
                    $meta = [...$meta, ...$result['meta']];
                } catch (Throwable $e) {
                    Log::warning('Dipstick probe failed: '.$e->getMessage());
                    $findings[] = new Finding('probe-failed', Severity::Warning, 'runtime', 'app', params: [
                        'reason' => t('Details are in the log.', category: 'dipstick'),
                    ]);
                    $probe = false;
                }
            }

            $this->scans->complete($scanId, $findings, $meta, $probe, (int) ((hrtime(true) - $started) / 1_000_000));
            $this->scans->prune($this->settings->keepScans);
        } catch (Throwable $e) {
            Log::error('Dipstick audit failed: '.$e->getMessage(), ['exception' => $e]);
            $this->scans->fail($scanId, t('The audit failed. Details are in the log.', category: 'dipstick'));

            throw $e;
        }

        Event::dispatch(new AuditCompleted($scanId, $findings));

        return $findings;
    }
}
