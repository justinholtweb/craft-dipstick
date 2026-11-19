<?php

// Removes every Dipstick harness fixture: the dipstick_editor user, dipstick_* GraphQL schemas and tokens,
// the dipstickfr site, and sandbox rows. Never touches anything not prefixed `dipstick`.
// Run with: ddev artisan tinker --execute "require 'storage/dipstick-cleanup.php';"

use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Facades\Gql;
use CraftCms\Cms\Support\Facades\Sites;
use CraftCms\Cms\User\Elements\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

foreach (User::find()->username(['dipstick_editor'])->status(null)->all() as $user) {
    Elements::deleteElement($user, true);
}

foreach (Gql::getTokens() as $token) {
    if (str_starts_with((string) $token->name, 'dipstick_')) {
        Gql::deleteTokenById($token->id);
    }
}

foreach (Gql::getSchemas() as $schema) {
    if (str_starts_with((string) $schema->name, 'dipstick_')) {
        Gql::deleteSchema($schema);
    }
}

if ($fr = Sites::getSiteByHandle('dipstickfr', true)) {
    Sites::deleteSite($fr);
}

if (Schema::hasTable('dipstick_scans')) {
    DB::table('dipstick_scans')->where('status', 'sandbox')->delete();
}

echo 'ok';
