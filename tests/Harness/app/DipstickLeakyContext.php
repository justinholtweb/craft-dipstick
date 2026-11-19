<?php

// Dipstick harness fixture (copied to app/Dipstick/LeakyContext.php by tests/Harness/check.sh, removed after).
// A deliberately Octane-unsafe service: a singleton that keeps the request, user and site it was built with,
// and statics that keep growing and keep the last user.

namespace App\Dipstick;

use CraftCms\Cms\Support\Facades\Sites;
use Illuminate\Http\Request;

use function CraftCms\Cms\currentUserElement;

class LeakyContext
{
    /** @var list<string> */
    public static array $seenUrls = [];

    public static mixed $lastUser = null;

    private mixed $user;

    private mixed $site;

    public function __construct(private Request $request)
    {
        $this->user = currentUserElement();
        $this->site = Sites::getCurrentSite();
    }

    public static function remember(Request $request): void
    {
        self::$seenUrls[] = $request->fullUrl();
        self::$lastUser = currentUserElement();
    }
}
