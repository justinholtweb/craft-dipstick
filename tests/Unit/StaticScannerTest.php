<?php

declare(strict_types=1);

use JustinHoltWeb\Dipstick\Findings\Finding;
use JustinHoltWeb\Dipstick\Findings\Severity;
use JustinHoltWeb\Dipstick\Scan\Source;
use JustinHoltWeb\Dipstick\Scan\StaticScanner;

function leakySource(): Source
{
    $root = dirname(__DIR__).'/Fixtures/leaky';

    return new Source('acme/leaky', Source::KIND_PLUGIN, $root, 'acme/leaky/', [$root.'/src'], ['Leaky\\']);
}

/** @return array<string, Finding> */
function byRule(array $findings): array
{
    $out = [];
    foreach ($findings as $f) {
        $out[$f->rule] = $f;
    }

    return $out;
}

/** @return list<string> "symbol:line" pairs */
function places(Finding $f): array
{
    return array_map(fn ($e) => basename((string) $e['file']).':'.$e['line'], $f->evidence);
}

it('finds static writes, split by how they leak', function () {
    $rules = byRule(new StaticScanner()->scan([leakySource()]));

    expect(places($rules['static-request-state']))->toBe(['CartService.php:31'])
        ->and($rules['static-request-state']->severity)->toBe(Severity::Critical)
        ->and($rules['static-request-state']->evidence[0]['target'])->toBe('Leaky\CartService::$currentUser')
        ->and(places($rules['static-array-growth']))->toBe(['CartService.php:29', 'CartService.php:32'])
        // boot() writes are once per worker; the @dipstick-ignore line is skipped; the closure in boot runs per request.
        ->and(places($rules['static-property-write']))->toBe(['CartService.php:30', 'LeakyPlugin.php:15']);
});

it('joins constructor captures with how the class is bound', function () {
    $rules = byRule(new StaticScanner()->scan([leakySource()]));

    expect(array_column($rules['singleton-captures-request']->evidence, 'symbol'))->toBe([
        'Leaky\BoundSingleton::__construct()', 'Leaky\CartService::__construct()', 'Leaky\CartService::__construct()',
    ])
        ->and(array_column($rules['singleton-captures-request']->evidence, 'kind'))->toBe(['site', 'request', 'site'])
        ->and(places($rules['controller-captures-request']))->toBe(['ReportController.php:13'])
        // ScopedThing takes the request too, but it's scoped: rebuilt per request.
        ->and($rules)->not->toHaveKey('constructor-captures-request');
});

it('finds globals, superglobals, exits, native headers and process settings outside console code', function () {
    $rules = byRule(new StaticScanner()->scan([leakySource()]));

    expect(places($rules['static-variable']))->toBe(['CartService.php:37'])
        ->and(places($rules['global-state']))->toBe(['CartService.php:38', 'CartService.php:39'])
        ->and(places($rules['superglobal-access']))->toBe(['CartService.php:41'])
        ->and(places($rules['native-response']))->toBe(['CartService.php:42'])
        ->and(places($rules['worker-exit']))->toBe(['CartService.php:43'])
        ->and(places($rules['process-state']))->toBe(['CartService.php:46'])
        ->and(places($rules['shutdown-function']))->toBe(['CartService.php:47'])
        ->and(places($rules['listener-in-request']))->toBe(['CartService.php:48'])
        ->and(places($rules['runtime-define']))->toBe(['CartService.php:49'])
        ->and(places($rules['parse-error']))->toBe(['Broken.php:0']);
});

it('shows files by package path, never absolute', function () {
    foreach (new StaticScanner()->scan([leakySource()]) as $finding) {
        foreach ($finding->evidence as $row) {
            expect((string) $row['file'])->toStartWith('acme/leaky/src/')->not->toContain(dirname(__DIR__));
        }
    }
});

it('honours ignore fingerprints and excluded paths', function () {
    $scanner = new StaticScanner;
    $all = byRule($scanner->scan([leakySource()]));
    $print = $all['worker-exit']->evidence[0]['fingerprint'];

    $rules = byRule(new StaticScanner()->configure(exclude: ['acme/leaky/src/ReportController.php'], ignore: [(string) $print, $all['parse-error']->fingerprint()])->scan([leakySource()]));

    expect($rules)->not->toHaveKey('worker-exit')
        ->and($rules)->not->toHaveKey('controller-captures-request')
        ->and($rules)->not->toHaveKey('parse-error')
        ->and($rules)->toHaveKey('native-response');
});

it('reports a clean source as a pass', function () {
    $root = dirname(__DIR__).'/Fixtures/clean';
    $findings = new StaticScanner()->scan([new Source('acme/clean', Source::KIND_PLUGIN, $root, 'acme/clean/', [$root.'/src'], ['Clean\\'])]);

    expect($findings)->toHaveCount(1)
        ->and($findings[0]->rule)->toBe('static-clean')
        ->and($findings[0]->severity)->toBe(Severity::Pass);
});

it('checks Blade templates line by line and counts Twig', function () {
    $root = dirname(__DIR__).'/Fixtures/leaky';
    $scanner = new StaticScanner;
    $rules = byRule($scanner->scan([new Source('templates', Source::KIND_TEMPLATES, $root, '', [$root.'/views'])]));

    expect(places($rules['superglobal-access']))->toBe(['page.blade.php:4'])
        ->and(places($rules['static-property-write']))->toBe(['page.blade.php:6'])
        ->and(places($rules['native-response']))->toBe(['page.blade.php:7'])
        // The commented-out `static` never counts.
        ->and($rules)->not->toHaveKey('static-variable')
        ->and($rules['superglobal-access']->kind)->toBe('template')
        ->and($scanner->counts)->toBe(['php' => 0, 'blade' => 1, 'twig' => 1]);
});

it('lists singletons for the runtime probe', function () {
    $scanner = new StaticScanner;
    $scanner->scan([leakySource()]);

    expect($scanner->singletons)->toContain('Leaky\CartService', 'Leaky\BoundSingleton')->not->toContain('Leaky\ScopedThing');
});

it('masks long string literals in snippets', function () {
    expect(StaticScanner::snippet(["self::\$key = 'averyveryverylongliteralvalue1234';"], 1))->toBe("self::\$key = '…';");
});
