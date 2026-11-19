<?php

declare(strict_types=1);

namespace JustinHoltWeb\Dipstick\Audit;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use JustinHoltWeb\Dipstick\Findings\Finding;
use JustinHoltWeb\Dipstick\Findings\Score;
use JustinHoltWeb\Dipstick\Findings\Severity;

/**
 * Stored audits (`dipstick_scans`) and their findings (`dipstick_findings`).
 */
class Scans
{
    public const string SCANS = 'dipstick_scans';

    public const string FINDINGS = 'dipstick_findings';

    /** A new audit row; returns its ID. */
    public function create(string $trigger, ?int $userId, string $status = 'queued'): int
    {
        $now = Carbon::now('UTC');

        return (int) DB::table(self::SCANS)->insertGetId([
            'status' => $status,
            'trigger' => in_array($trigger, ['cli', 'cp'], true) ? $trigger : 'cli',
            'userId' => $userId,
            'dateCreated' => $now,
            'dateUpdated' => $now,
            'uid' => (string) Str::uuid(),
        ]);
    }

    public function markRunning(int $id): void
    {
        DB::table(self::SCANS)->where('id', $id)->update(['status' => 'running', 'dateUpdated' => Carbon::now('UTC')]);
    }

    public function fail(int $id, string $error): void
    {
        DB::table(self::SCANS)->where('id', $id)->update([
            'status' => 'failed',
            'error' => mb_substr($error, 0, 500),
            'dateUpdated' => Carbon::now('UTC'),
        ]);
    }

    /**
     * Saves an audit's findings and score.
     *
     * @param  list<Finding>  $findings
     * @param  array<string, scalar|null>  $meta
     */
    public function complete(int $id, array $findings, array $meta, bool $probed, int $durationMs): void
    {
        DB::transaction(function () use ($id, $findings, $meta, $probed, $durationMs): void {
            DB::table(self::FINDINGS)->where('scanId', $id)->delete();

            foreach (array_chunk($findings, 100) as $chunk) {
                DB::table(self::FINDINGS)->insert(array_map(fn (Finding $f) => [
                    'scanId' => $id,
                    'rule' => mb_substr($f->rule, 0, 64),
                    'severity' => $f->severity->value,
                    'kind' => $f->kind,
                    'source' => mb_substr($f->source, 0, 255),
                    'site' => $f->site === null ? null : mb_substr($f->site, 0, 255),
                    'target' => $f->target === null ? null : mb_substr($f->target, 0, 500),
                    'fingerprint' => $f->fingerprint(),
                    'occurrences' => $f->occurrences,
                    'params' => json_encode($f->params),
                    'evidence' => json_encode($f->evidence, JSON_INVALID_UTF8_SUBSTITUTE),
                ], $chunk));
            }

            DB::table(self::SCANS)->where('id', $id)->update([
                'status' => 'done',
                'score' => Score::of($findings),
                'counts' => json_encode(Score::counts($findings)),
                'meta' => json_encode($meta),
                'probed' => $probed,
                'error' => null,
                'durationMs' => $durationMs,
                'dateUpdated' => Carbon::now('UTC'),
            ]);
        });
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        $row = DB::table(self::SCANS)->where('id', $id)->first();

        return $row === null ? null : self::scan((array) $row);
    }

    /**
     * The newest finished audit.
     *
     * @return array<string, mixed>|null
     */
    public function latestDone(): ?array
    {
        $row = DB::table(self::SCANS)->where('status', 'done')->orderByDesc('id')->first();

        return $row === null ? null : self::scan((array) $row);
    }

    /** @return list<array<string, mixed>> */
    public function recent(int $limit = 20): array
    {
        return DB::table(self::SCANS)->orderByDesc('id')->limit($limit)->get()
            ->map(fn (object $row) => self::scan((array) $row))->values()->all();
    }

    /** Whether an audit is queued or running (newer than an hour; older ones are treated as abandoned). */
    public function inProgress(): bool
    {
        return DB::table(self::SCANS)->whereIn('status', ['queued', 'running'])
            ->where('dateCreated', '>', Carbon::now('UTC')->subHour())->exists();
    }

    /** @return list<Finding> Most urgent first. */
    public function findings(int $scanId): array
    {
        $findings = DB::table(self::FINDINGS)->where('scanId', $scanId)->orderBy('id')->get()
            ->map(fn (object $row) => Finding::fromArray([
                ...(array) $row,
                'params' => json_decode((string) ($row->params ?? '[]'), true),
                'evidence' => json_decode((string) ($row->evidence ?? '[]'), true),
            ]))->values()->all();

        usort($findings, fn (Finding $a, Finding $b) => $a->severity->rank() <=> $b->severity->rank());

        return $findings;
    }

    /** Deletes all but the newest `$keep` audits. */
    public function prune(int $keep): int
    {
        $ids = DB::table(self::SCANS)->orderByDesc('id')->offset(max(1, $keep))->limit(1000)->pluck('id')->all();

        return $ids === [] ? 0 : DB::table(self::SCANS)->whereIn('id', $ids)->delete();
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private static function scan(array $row): array
    {
        $counts = json_decode((string) ($row['counts'] ?? ''), true);
        $meta = json_decode((string) ($row['meta'] ?? ''), true);
        $counts = is_array($counts) ? $counts : [];
        $counts = array_map(fn (Severity $s) => (int) ($counts[$s->value] ?? 0), array_combine(array_map(fn (Severity $s) => $s->value, Severity::ordered()), Severity::ordered()));
        $score = $row['score'] === null ? null : (int) $row['score'];

        return [
            'id' => (int) $row['id'],
            'status' => (string) $row['status'],
            'trigger' => (string) $row['trigger'],
            'userId' => $row['userId'] === null ? null : (int) $row['userId'],
            'score' => $score,
            'grade' => $score === null ? null : Score::grade($score),
            'counts' => $counts,
            'ready' => $score !== null && $counts['critical'] === 0 && $counts['warning'] === 0,
            'meta' => is_array($meta) ? $meta : [],
            'probed' => (bool) $row['probed'],
            'error' => $row['error'] === null ? null : (string) $row['error'],
            'durationMs' => $row['durationMs'] === null ? null : (int) $row['durationMs'],
            'dateCreated' => Carbon::parse((string) $row['dateCreated'], 'UTC'),
        ];
    }
}
