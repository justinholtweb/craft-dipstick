<?php

namespace Leaky;

use function CraftCms\Cms\currentUser;

class ReportController
{
    private $user;

    public function __construct()
    {
        $this->user = currentUser();
    }
}
