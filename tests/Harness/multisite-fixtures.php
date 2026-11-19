<?php

// Dipstick harness: Dipstick's own second site, `dipstickfr` (French). Never touches a site Dipstick
// didn't create; cleanup.php removes it. Run under bin/harness-lock:
//   ddev artisan tinker --execute "require 'storage/dipstick-multisite-fixtures.php';"

use CraftCms\Cms\Site\Data\Site;
use CraftCms\Cms\Support\Facades\Sites;

$fr = Sites::getSiteByHandle('dipstickfr', true);

if (! $fr) {
    $primary = Sites::getPrimarySite();
    $fr = new Site;
    $fr->groupId = $primary->groupId;
    $fr->name = 'Dipstick Français';
    $fr->handle = 'dipstickfr';
    $fr->language = 'fr-FR';
    $fr->hasUrls = true;
    // `@web` isn't parsed in a Craft 6 site URL: use an absolute one.
    $fr->baseUrl = rtrim((string) config('app.url'), '/').'/dipstickfr/';
    $fr->enabled = true;
    $fr->sortOrder = 99;
    Sites::saveSite($fr) || throw new RuntimeException('site: '.json_encode($fr->errors()->all()));
}

echo $fr->id;
