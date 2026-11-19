<?php

declare(strict_types=1);

namespace JustinHoltWeb\Dipstick;

use JustinHoltWeb\Dipstick\Audit\Scans;

use function CraftCms\Cms\currentUser;

/**
 * The latest audit as a small summary: score, grade, whether it's ready and
 * how many findings of each severity. Never files, classes or paths.
 *
 * In templates (Twig `dipstick.readiness()`, Blade `$dipstick->readiness()`)
 * it's only given to signed-in admins; everyone else gets null.
 */
class Readiness
{
    public function __construct(private readonly Scans $scans) {}

    /**
     * @return array{score: int, grade: string, ready: bool, critical: int, warning: int, notice: int, scannedAt: string}|null
     */
    public function summary(): ?array
    {
        $scan = rescue(fn () => $this->scans->latestDone(), null, false);

        if (! is_array($scan) || $scan['score'] === null) {
            return null;
        }

        return [
            'score' => (int) $scan['score'],
            'grade' => (string) $scan['grade'],
            'ready' => (bool) $scan['ready'],
            'critical' => (int) $scan['counts']['critical'],
            'warning' => (int) $scan['counts']['warning'],
            'notice' => (int) $scan['counts']['notice'],
            'scannedAt' => $scan['dateCreated']->toIso8601String(),
        ];
    }

    /**
     * The summary for a signed-in admin, else null.
     *
     * @return array{score: int, grade: string, ready: bool, critical: int, warning: int, notice: int, scannedAt: string}|null
     */
    public function readiness(): ?array
    {
        return currentUser()?->isAdmin() === true ? $this->summary() : null;
    }
}
