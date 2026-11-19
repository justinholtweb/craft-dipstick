<?php

declare(strict_types=1);

use JustinHoltWeb\Dipstick\Findings\Finding;
use JustinHoltWeb\Dipstick\Findings\Score;
use JustinHoltWeb\Dipstick\Findings\Severity;

$f = fn (Severity $s) => new Finding('x', $s, 'static', 'app');

it('scores 100 less each finding’s cost, never below zero', function () use ($f) {
    expect(Score::of([]))->toBe(100)
        ->and(Score::of([$f(Severity::Critical), $f(Severity::Warning), $f(Severity::Notice), $f(Severity::Pass)]))->toBe(62)
        ->and(Score::of(array_fill(0, 5, $f(Severity::Critical))))->toBe(0);
});

it('grades and counts', function () use ($f) {
    expect(Score::grade(95))->toBe('A')->and(Score::grade(62))->toBe('D')->and(Score::grade(10))->toBe('F')
        ->and(Score::counts([$f(Severity::Warning), $f(Severity::Warning), $f(Severity::Pass)]))->toBe(['critical' => 0, 'warning' => 2, 'notice' => 0, 'pass' => 1])
        ->and(Score::ready(['critical' => 0, 'warning' => 0, 'notice' => 4, 'pass' => 1]))->toBeTrue()
        ->and(Score::ready(['critical' => 0, 'warning' => 1, 'notice' => 0, 'pass' => 0]))->toBeFalse();
});

it('round-trips findings and keeps fingerprints stable', function () {
    $finding = new Finding('probe-site-leak', Severity::Critical, 'runtime', 'acme/x', 'fr', 'Acme\Svc->site', ['n' => 1], [['site' => 'fr', 'request' => 2]], 2);
    $copy = Finding::fromArray(json_decode(json_encode($finding->toArray()), true));

    expect($copy->toArray())->toBe($finding->toArray())
        ->and($copy->fingerprint())->toHaveLength(12)
        ->and(Finding::fromArray(['rule' => 'x', 'severity' => 'bogus', 'kind' => 'evil', 'evidence' => [['a' => ['nested']], 'nope']])->toArray())
        ->toMatchArray(['severity' => 'notice', 'kind' => 'static', 'evidence' => [[]]]);
});
