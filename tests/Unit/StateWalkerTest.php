<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use JustinHoltWeb\Dipstick\Probe\StateWalker;

class WalkerOwned
{
    public ?object $child = null;

    public array $byId = [];

    public function __construct(private ?Request $request = null, protected mixed $user = null) {}
}

class ForeignHolder
{
    public function __construct(public Request $request) {}
}

function walker(): StateWalker
{
    return new StateWalker(fn (string $class) => str_starts_with($class, 'Walker'));
}

it('finds requests and users held by audited objects, with the path', function () {
    $owned = new WalkerOwned(Request::create('/a'), new GenericUser(['id' => 7]));
    $owned->child = new WalkerOwned(Request::create('/b'));

    $found = walker()->find($owned, 'Svc');

    expect(array_map(fn ($f) => $f['path'].'='.$f['kind'].'='.($f['direct'] ? 'direct' : 'mapped'), $found))->toEqualCanonicalizing([
        'Svc->child->request=request=direct', 'Svc->request=request=direct', 'Svc->user=user=direct',
    ]);
});

it('marks values inside arrays and collections as indirect', function () {
    $owned = new WalkerOwned;
    $owned->byId = [3 => new GenericUser(['id' => 3])];
    $owned->child = new WalkerOwned(user: new Collection([new GenericUser(['id' => 4])]));

    $found = walker()->find($owned, 'Svc');

    expect(array_map(fn ($f) => [$f['path'], $f['direct']], $found))->toEqualCanonicalizing([
        ['Svc->byId[3]', false], ['Svc->child->user[0]', false],
    ]);
});

it('doesn’t look inside classes it isn’t auditing', function () {
    $owned = new WalkerOwned;
    $owned->child = new ForeignHolder(Request::create('/x'));

    expect(walker()->find($owned, 'Svc'))->toBe([]);
});

it('copes with cycles and skips lazy objects without initialising them', function () {
    $a = new WalkerOwned;
    $a->child = $a;
    $initialised = false;
    $lazy = new ReflectionClass(WalkerOwned::class)->newLazyGhost(function () use (&$initialised) {
        $initialised = true;
    });
    $b = new WalkerOwned;
    $b->child = $lazy;

    expect(walker()->find($a, 'A'))->toBe([])
        ->and(walker()->find($b, 'B'))->toBe([])
        ->and($initialised)->toBeFalse();
});

it('masks odd array keys in paths', function () {
    $owned = new WalkerOwned;
    $owned->byId = ['user@example.com' => Request::create('/')];

    expect(walker()->find($owned, 'Svc')[0]['path'])->toBe('Svc->byId[…]');
});
