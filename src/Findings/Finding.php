<?php

declare(strict_types=1);

namespace JustinHoltWeb\Dipstick\Findings;

/**
 * One thing Dipstick noticed: a rule that matched in one source (the app, a
 * plugin package or the templates), with the places it matched as evidence.
 *
 * Findings store a rule handle and parameters, not prose: titles, impact and
 * fix recipes are looked up in {@see Rules} when shown, so they're translated
 * into the reader's language rather than the scanner's.
 *
 * Evidence never holds an absolute path. Files are shown as
 * `<package>/<path in package>` or relative to the project root.
 */
final class Finding
{
    /**
     * @param  'static'|'template'|'runtime'  $kind
     * @param  array<string, scalar|null>  $params  Values for the rule's `{placeholders}`.
     * @param  list<array<string, scalar|null>>  $evidence  Where it matched: file/line/symbol/code, or site/request/holder.
     */
    public function __construct(
        public readonly string $rule,
        public readonly Severity $severity,
        public readonly string $kind,
        public readonly string $source,
        public readonly ?string $site = null,
        public readonly ?string $target = null,
        public readonly array $params = [],
        public readonly array $evidence = [],
        public readonly int $occurrences = 1,
    ) {}

    /** Stable across scans (no line numbers), so a finding can be ignored by it. */
    public function fingerprint(): string
    {
        return substr(sha1(implode('|', [$this->rule, $this->source, (string) $this->target, (string) $this->site])), 0, 12);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'rule' => $this->rule,
            'severity' => $this->severity->value,
            'kind' => $this->kind,
            'source' => $this->source,
            'site' => $this->site,
            'target' => $this->target,
            'params' => $this->params,
            'evidence' => $this->evidence,
            'occurrences' => $this->occurrences,
            'fingerprint' => $this->fingerprint(),
        ];
    }

    /** @param  array<string, mixed>  $row */
    public static function fromArray(array $row): self
    {
        $kind = (string) ($row['kind'] ?? 'static');

        return new self(
            rule: (string) ($row['rule'] ?? ''),
            severity: Severity::tryFrom((string) ($row['severity'] ?? '')) ?? Severity::Notice,
            kind: in_array($kind, ['static', 'template', 'runtime'], true) ? $kind : 'static',
            source: (string) ($row['source'] ?? ''),
            site: isset($row['site']) ? (string) $row['site'] : null,
            target: isset($row['target']) ? (string) $row['target'] : null,
            params: self::scalars($row['params'] ?? []),
            evidence: array_values(array_map(self::scalars(...), array_filter(is_array($row['evidence'] ?? null) ? $row['evidence'] : [], is_array(...)))),
            occurrences: max(1, (int) ($row['occurrences'] ?? 1)),
        );
    }

    /** @return array<string, scalar|null> */
    private static function scalars(mixed $value): array
    {
        $out = [];

        foreach (is_array($value) ? $value : [] as $k => $v) {
            if (is_scalar($v) || $v === null) {
                $out[(string) $k] = $v;
            }
        }

        return $out;
    }
}
