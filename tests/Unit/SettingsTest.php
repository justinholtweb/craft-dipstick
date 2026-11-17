<?php

declare(strict_types=1);

use JustinHoltWeb\Dipstick\Settings;

it('has sensible defaults', function () {
    $settings = new Settings;

    expect($settings->scanVendor)->toBe(true);
});
