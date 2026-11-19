<?php

declare(strict_types=1);

use JustinHoltWeb\Dipstick\Scan\BladeExtractor;
use JustinHoltWeb\Dipstick\Settings;

it('has sensible defaults', function () {
    $settings = new Settings;

    expect($settings->scanVendor)->toBeTrue()
        ->and($settings->scanApp)->toBeTrue()
        ->and($settings->probeEnabled)->toBeTrue()
        ->and($settings->requests())->toBe(3);
});

it('parses exclude and ignore lists, dropping comments and junk', function () {
    $settings = new Settings;
    $settings->excludePaths = "app/Legacy/*\n\n  acme/x/src/Old.php  # old\n";
    $settings->ignore = "3f9a1c2b7d4e # fine\nnot-a-fingerprint\nABCDEF123456\n0123456789ab";
    $settings->probeRequests = 50;
    $settings->probeSkip = [' \\App\\Svc ', ''];

    expect($settings->excludeList())->toBe(['app/Legacy/*', 'acme/x/src/Old.php'])
        ->and($settings->ignoreList())->toBe(['3f9a1c2b7d4e', '0123456789ab'])
        ->and($settings->requests())->toBe(10)
        ->and($settings->skipList())->toBe(['App\Svc']);
});

it('lifts PHP out of Blade on the same lines', function () {
    $php = BladeExtractor::toPhp("<p>{{ \$a }}</p>\n{{-- {{ \$hidden }}\n--}}\n@php\n  \$b = 1;\n@endphp\n{!! \$c !!} @php(\$d)");
    $lines = explode("\n", $php);

    expect($lines[0])->toContain('($a);')
        ->and($php)->not->toContain('hidden')
        ->and($lines[4])->toContain('$b = 1;')
        ->and($lines[6])->toContain('($c);')->toContain('($d);');
});
