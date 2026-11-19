<?php

namespace Clean;

class Clean
{
    public function __construct(private readonly string $name) {}

    public function name(): string
    {
        return $this->name;
    }
}
