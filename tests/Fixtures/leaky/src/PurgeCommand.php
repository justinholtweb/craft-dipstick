<?php

namespace Leaky;

use Illuminate\Console\Command;

class PurgeCommand extends Command
{
    public function handle(): int
    {
        if ($_SERVER['argv'] ?? null) {
            exit(1);
        }

        ini_set('memory_limit', '1G');

        return 0;
    }
}
