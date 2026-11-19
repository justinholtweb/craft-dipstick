<?php

namespace Leaky;

use Illuminate\Http\Request;

class ScopedThing
{
    public function __construct(private Request $request) {}
}
