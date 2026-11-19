<?php

declare(strict_types=1);

namespace JustinHoltWeb\Dipstick\Scan;

use JustinHoltWeb\Dipstick\Findings\Finding;
use JustinHoltWeb\Dipstick\Findings\Rules;
use JustinHoltWeb\Dipstick\Findings\Severity;
use PhpParser\ErrorHandler\Collecting;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeVisitor\ParentConnectingVisitor;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Throwable;

/**
 * Reads PHP and Blade files and reports leak patterns, one finding per rule
 * per source with the matching lines as evidence.
 *
 * Files are only ever read and parsed: nothing is included, autoloaded or
 * executed. Twig templates are counted but not checked; compiled Twig
 * can't keep state between requests.
 */
final class StaticScanner
{
    /** Skip files bigger than this (generated or vendored code). */
    public const int MAX_FILE_BYTES = 1_048_576;

    /** Evidence rows kept per finding; the count still covers them all. */
    public const int MAX_EVIDENCE = 50;

    private const array SKIP_DIRS = ['vendor', 'node_modules', 'tests', 'test', '.git', 'storage', 'web', 'public'];

    private Parser $parser;

    /** @var list<string> */
    private array $exclude = [];

    /** @var array<string, true> */
    private array $ignore = [];

    /** @var array{php: int, blade: int, twig: int} */
    public array $counts = ['php' => 0, 'blade' => 0, 'twig' => 0];

    /** @var list<string> Singleton classes (attribute or binding) seen while scanning, for the runtime probe. */
    public array $singletons = [];

    public function __construct()
    {
        $this->parser = new ParserFactory()->createForHostVersion();
    }

    /**
     * @param  list<string>  $exclude  Globs matched against displayed paths, e.g. `app/Legacy/*`.
     * @param  list<string>  $ignore  Evidence fingerprints to drop.
     */
    public function configure(array $exclude = [], array $ignore = []): self
    {
        $this->exclude = array_values(array_filter(array_map('trim', $exclude)));
        $this->ignore = array_fill_keys(array_filter(array_map('trim', $ignore)), true);

        return $this;
    }

    /**
     * @param  list<Source>  $sources
     * @return list<Finding>
     */
    public function scan(array $sources): array
    {
        $findings = [];
        $classes = [];
        $bindings = [];
        $hitsBySource = [];

        foreach ($sources as $source) {
            $hitsBySource[$source->name] = [];

            foreach ($this->files($source) as [$file, $display, $type]) {
                $this->counts[$type]++;

                if ($type === 'twig') {
                    continue;
                }

                $result = $this->scanFile($file, $type === 'blade');

                if ($result === null) {
                    $hitsBySource[$source->name][] = ['rule' => 'parse-error', 'line' => 0, 'symbol' => '', 'target' => null, 'file' => $display, 'code' => ''];

                    continue;
                }

                [$visitor, $lines] = $result;

                foreach ($visitor->hits as $hit) {
                    $hitsBySource[$source->name][] = [...$hit, 'file' => $display, 'code' => self::snippet($lines, $hit['line'])];
                }

                foreach ($visitor->classes as $class => $info) {
                    $classes[$class] = [...$info, 'file' => $display, 'source' => $source->name, 'lines' => $lines];
                }

                $bindings = [...$bindings, ...$visitor->bindings];
            }
        }

        // Constructor captures, now that every binding is known.
        foreach ($classes as $class => $info) {
            $binding = $bindings[$class] ?? null;
            $scoped = $info['scoped'] || $binding === 'scoped';
            $singleton = ! $scoped && ($info['singleton'] || $binding === 'singleton');

            if ($singleton) {
                $this->singletons[] = $class;
            }

            if ($info['captures'] === [] || $scoped) {
                continue;
            }

            $rule = match (true) {
                $singleton => 'singleton-captures-request',
                $info['controller'] => 'controller-captures-request',
                default => 'constructor-captures-request',
            };

            foreach ($info['captures'] as $capture) {
                $hitsBySource[$info['source']][] = [
                    'rule' => $rule, 'line' => $capture['line'], 'symbol' => $class.'::__construct()',
                    'target' => $class, 'file' => $info['file'], 'code' => self::snippet($info['lines'], $capture['line']),
                    'kind' => $capture['kind'],
                ];
            }
        }

        foreach ($sources as $source) {
            $grouped = $this->group($source, $hitsBySource[$source->name]);
            $findings = [...$findings, ...($grouped !== [] ? $grouped : [new Finding('static-clean', Severity::Pass, self::kindOf($source), $source->name)])];
        }

        return $findings;
    }

    /**
     * @return array{LeakVisitor, list<string>}|null Null when the file can't be parsed at all.
     */
    public function scanFile(string $file, bool $blade = false): ?array
    {
        $code = @file_get_contents($file);

        if ($code === false) {
            return null;
        }

        return $this->scanCode($code, $blade);
    }

    /**
     * @return array{LeakVisitor, list<string>}|null
     */
    public function scanCode(string $code, bool $blade = false): ?array
    {
        $lines = explode("\n", $code);
        $php = $blade ? BladeExtractor::toPhp($code) : $code;

        try {
            $errors = new Collecting;
            $ast = $this->parser->parse($php, $errors);
        } catch (Throwable) {
            return null;
        }

        if ($ast === null || ($ast === [] && $errors->hasErrors())) {
            return null;
        }

        // Names first, in their own pass: the leak visitor looks ahead into child nodes.
        $ast = new NodeTraverser(new NameResolver(null, ['replaceNodes' => true]), new ParentConnectingVisitor)->traverse($ast);
        $visitor = new LeakVisitor(template: $blade);
        new NodeTraverser($visitor)->traverse($ast);

        return [$visitor, $lines];
    }

    /**
     * @param  list<array<string, mixed>>  $hits
     * @return list<Finding>
     */
    private function group(Source $source, array $hits): array
    {
        $byRule = [];

        foreach ($hits as $hit) {
            $rule = (string) $hit['rule'];
            $fingerprint = substr(sha1($rule.'|'.$hit['file'].'|'.$hit['symbol'].'|'.($hit['target'] ?? '')), 0, 12);

            if (isset($this->ignore[$fingerprint]) || self::ignoredInline($hit)) {
                continue;
            }

            $byRule[$rule][] = [
                'file' => (string) $hit['file'],
                'line' => (int) $hit['line'],
                'symbol' => (string) $hit['symbol'],
                'target' => isset($hit['target']) ? (string) $hit['target'] : null,
                'kind' => isset($hit['kind']) ? (string) $hit['kind'] : null,
                'code' => (string) $hit['code'],
                'fingerprint' => $fingerprint,
            ];
        }

        $findings = [];

        foreach ($byRule as $rule => $rows) {
            usort($rows, fn (array $a, array $b) => [$a['file'], $a['line']] <=> [$b['file'], $b['line']]);
            $finding = new Finding(
                rule: $rule,
                severity: Rules::severity($rule),
                kind: self::kindOf($source),
                source: $source->name,
                evidence: array_slice($rows, 0, self::MAX_EVIDENCE),
                occurrences: count($rows),
            );

            if (! isset($this->ignore[$finding->fingerprint()])) {
                $findings[] = $finding;
            }
        }

        usort($findings, fn (Finding $a, Finding $b) => $a->severity->rank() <=> $b->severity->rank() ?: strcmp($a->rule, $b->rule));

        return $findings;
    }

    /** @param  array<string, mixed>  $hit */
    private static function ignoredInline(array $hit): bool
    {
        return str_contains((string) ($hit['code'] ?? ''), '@dipstick-ignore') || str_contains((string) ($hit['previous'] ?? ''), '@dipstick-ignore');
    }

    private static function kindOf(Source $source): string
    {
        return $source->kind === Source::KIND_TEMPLATES ? 'template' : 'static';
    }

    /**
     * The matched line, trimmed and shortened, with long string literals masked
     * so a hard-coded key never lands in a report. A `@dipstick-ignore` comment
     * on the line above is carried over so it can be honoured.
     *
     * @param  list<string>  $lines
     */
    public static function snippet(array $lines, int $line): string
    {
        $text = trim($lines[$line - 1] ?? '');
        $text = (string) preg_replace('/([\'"])[^\'"\s]{24,}\1/', '$1…$1', $text);
        $text = mb_strlen($text) > 160 ? mb_substr($text, 0, 159).'…' : $text;

        if (str_contains($lines[$line - 2] ?? '', '@dipstick-ignore') && ! str_contains($text, '@dipstick-ignore')) {
            $text .= ' // @dipstick-ignore';
        }

        return $text;
    }

    /**
     * @return iterable<array{string, string, 'php'|'blade'|'twig'}> Absolute path, displayed path, type.
     */
    private function files(Source $source): iterable
    {
        $seen = [];

        foreach ($source->paths as $path) {
            if (is_file($path)) {
                $candidates = [new SplFileInfo($path)];
            } elseif (is_dir($path)) {
                $candidates = new RecursiveIteratorIterator(
                    new SkipDirsFilter(new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS), self::SKIP_DIRS),
                );
            } else {
                continue;
            }

            foreach ($candidates as $info) {
                if (! $info instanceof SplFileInfo || ! $info->isFile() || $info->getSize() > self::MAX_FILE_BYTES) {
                    continue;
                }

                $name = $info->getFilename();
                $type = match (true) {
                    str_ends_with($name, '.blade.php') => 'blade',
                    str_ends_with($name, '.php') => 'php',
                    str_ends_with($name, '.twig') => 'twig',
                    default => null,
                };

                // Templates are Blade and Twig only; PHP sources skip Twig.
                if ($type === null || ($source->kind === Source::KIND_TEMPLATES && $type === 'php') || ($source->kind !== Source::KIND_TEMPLATES && $type === 'twig')) {
                    continue;
                }

                $real = $info->getRealPath();
                $display = $real === false ? null : $source->display($real);
                $display ??= $source->display($info->getPathname());

                if ($display === null || isset($seen[$display]) || $this->excluded($display)) {
                    continue;
                }

                $seen[$display] = true;

                yield [$real ?: $info->getPathname(), $display, $type];
            }
        }
    }

    private function excluded(string $display): bool
    {
        foreach ($this->exclude as $pattern) {
            if (fnmatch($pattern, $display) || str_starts_with($display, rtrim($pattern, '/*').'/')) {
                return true;
            }
        }

        return false;
    }
}
