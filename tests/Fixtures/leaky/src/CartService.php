<?php

namespace Leaky;

use CraftCms\Cms\Support\Facades\Sites;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;

use function CraftCms\Cms\currentUserElement;

#[Singleton]
class CartService
{
    public static ?object $currentUser = null;

    public static array $seen = [];

    private static int $hits = 0;

    public function __construct(private Request $request)
    {
        $this->site = Sites::getCurrentSite();
    }

    public function remember(string $key): void
    {
        self::$seen[$key] = true;
        static::$hits++;
        self::$currentUser = currentUserElement();
        array_push(self::$seen, $key);
    }

    public function legacy(): string
    {
        static $cache;
        global $config;
        $GLOBALS['x'] = 1;

        if (isset($_GET['debug'])) {
            header('X-Debug: 1');
            exit;
        }

        date_default_timezone_set('UTC');
        register_shutdown_function(fn () => null);
        Event::listen('thing', fn () => null);
        define('LEAKY', 1);

        if (! defined('GUARDED')) {
            define('GUARDED', 1);
        }

        // @dipstick-ignore
        self::$hits = 5;

        return (string) Auth::id();
    }

    public static function boot(): void
    {
        self::$hits = 0;
        Event::listen('booted', fn () => null);
    }
}
