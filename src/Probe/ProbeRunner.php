<?php

declare(strict_types=1);

namespace JustinHoltWeb\Dipstick\Probe;

use Illuminate\Support\Facades\Process;
use JustinHoltWeb\Dipstick\Findings\Finding;
use JustinHoltWeb\Dipstick\Findings\Rules;
use RuntimeException;

/**
 * Runs the probe in a child PHP process (`craft:dipstick:probe --json`), so
 * whatever the simulated requests leave behind dies with that process and
 * never reaches a web or queue worker.
 */
class ProbeRunner
{
    public const string MARKER = 'DIPSTICK-JSON:';

    public const int TIMEOUT = 300;

    /**
     * @return array{findings: list<Finding>, meta: array<string, scalar|null>}
     *
     * @throws RuntimeException When the probe can't run or its output can't be read.
     */
    public function run(): array
    {
        $result = Process::path(base_path())
            ->timeout(self::TIMEOUT)
            ->env(['DIPSTICK_PROBE_CHILD' => '1'])
            ->run([$this->php(), 'artisan', 'craft:dipstick:probe', '--json', '--no-interaction']);

        $payload = null;

        foreach (array_reverse(explode("\n", $result->output())) as $line) {
            if (str_starts_with($line, self::MARKER)) {
                $payload = json_decode(substr($line, strlen(self::MARKER)), true);
                break;
            }
        }

        if (! is_array($payload)) {
            throw new RuntimeException($result->successful()
                ? 'The probe produced no report.'
                : 'The probe process exited with code '.$result->exitCode().'.');
        }

        if (isset($payload['error'])) {
            throw new RuntimeException('The probe failed: '.mb_substr((string) $payload['error'], 0, 200));
        }

        $findings = [];

        foreach (is_array($payload['findings'] ?? null) ? $payload['findings'] : [] as $row) {
            // Only rules the probe can report: the child's output is checked, not trusted.
            if (is_array($row) && isset(Rules::RUNTIME[(string) ($row['rule'] ?? '')])) {
                $findings[] = Finding::fromArray(['kind' => 'runtime', ...$row]);
            }
        }

        $meta = [];

        foreach (is_array($payload['meta'] ?? null) ? $payload['meta'] : [] as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $meta['probe.'.$key] = $value;
            }
        }

        return ['findings' => $findings, 'meta' => $meta];
    }

    /**
     * The PHP CLI binary: `DIPSTICK_PHP_BINARY`, else the running binary when
     * that's the CLI, else `php` next to it, else `php` on the path.
     */
    public function php(): string
    {
        $configured = getenv('DIPSTICK_PHP_BINARY');

        if (is_string($configured) && $configured !== '' && is_executable($configured)) {
            return $configured;
        }

        if (PHP_SAPI === 'cli') {
            return PHP_BINARY;
        }

        $sibling = PHP_BINDIR.'/php';

        return is_executable($sibling) ? $sibling : 'php';
    }
}
