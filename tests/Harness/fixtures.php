<?php

// Dipstick harness: a non-admin CP user and GraphQL schemas + tokens. Idempotent.
//   users   dipstick_editor (accessCp + every utility; not an admin), password dipstickpassword
//   schemas dipstick_readiness (the readiness component + the site), dipstick_none (the site only)
//   tokens  "dp{name}" padded to 32 chars
// Run with: ddev artisan tinker --execute "require 'storage/dipstick-fixtures.php';"

use CraftCms\Cms\Gql\Data\GqlSchema;
use CraftCms\Cms\Gql\Data\GqlToken;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Facades\Gql;
use CraftCms\Cms\Support\Facades\Sites;
use CraftCms\Cms\Support\Facades\UserPermissions;
use CraftCms\Cms\Support\Facades\Users;
use CraftCms\Cms\User\Elements\User;

$user = User::find()->username('dipstick_editor')->status(null)->one();

if (! $user) {
    $user = new User;
    $user->username = 'dipstick_editor';
    $user->email = 'dipstick_editor@dipstick.test';
    $user->firstName = 'Editor';
    $user->lastName = 'Dipstick';
    $user->newPassword = 'dipstickpassword';
    Elements::saveElement($user) || throw new RuntimeException('user: '.json_encode($user->errors()->all()));
    Users::activateUser($user);
}

UserPermissions::saveUserPermissions($user->id, ['accessCp', 'accessPlugin-dipstick', 'utility:clear-caches']);

$site = 'sites.'.Sites::getPrimarySite()->uid.':read';

foreach (['readiness' => [$site, 'dipstick.readiness:read'], 'none' => [$site]] as $name => $scope) {
    $schema = collect(Gql::getSchemas())->first(fn ($s) => $s->name === "dipstick_{$name}") ?? new GqlSchema;
    $schema->name = "dipstick_{$name}";
    $schema->scope = $scope;
    Gql::saveSchema($schema) || throw new RuntimeException(json_encode($schema->errors()->all()));

    $token = collect(Gql::getTokens())->first(fn ($t) => $t->name === "dipstick_{$name}") ?? new GqlToken;
    $token->name = "dipstick_{$name}";
    $token->schemaId = $schema->id;
    $token->accessToken = str_pad("dp{$name}", 32, '0');
    Gql::saveToken($token) || throw new RuntimeException(json_encode($token->errors()->all()));
}

Gql::invalidateCaches();
echo 'ok';
