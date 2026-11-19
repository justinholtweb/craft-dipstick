<?php

declare(strict_types=1);

namespace JustinHoltWeb\Dipstick\Listeners;

use CraftCms\Cms\Gql\Events\GqlSchemaComponentsResolving;
use CraftCms\Cms\User\Elements\User;
use JustinHoltWeb\Dipstick\Gql\ReadinessQuery;

use function CraftCms\Cms\t;

/**
 * One schema permission: “View the Octane readiness summary”. Off unless a
 * schema ticks it; for user-authenticated requests, admins only.
 */
class RegisterGqlComponents
{
    public function handle(GqlSchemaComponentsResolving $event): void
    {
        $event->queries['Dipstick'] = [
            ReadinessQuery::COMPONENT => [
                'label' => t('View the Octane readiness summary', category: 'dipstick'),
                'info' => t('Adds the `dipstickReadiness` query: the latest audit’s score, grade and counts. No files, classes or findings.', category: 'dipstick'),
                'authenticated' => fn (User $user): bool => (bool) $user->admin,
            ],
        ];
    }
}
