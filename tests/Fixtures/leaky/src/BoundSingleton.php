<?php

namespace Leaky;

use CraftCms\Cms\Site\Data\Site;

class BoundSingleton
{
    private Site $site;

    public function __construct(Site $site)
    {
        $this->site = $site;
    }
}
