<?php

declare(strict_types=1);

namespace JustinHoltWeb\Dipstick\Gql;

use CraftCms\Cms\Gql\GqlEntityRegistry;
use CraftCms\Cms\Gql\GqlHelper;
use CraftCms\Cms\Gql\Queries\Query;
use GraphQL\Error\UserError;
use GraphQL\Type\Definition\FieldDefinition;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use JustinHoltWeb\Dipstick\Readiness;

/**
 * `dipstickReadiness` — the latest audit's score, grade and counts, for a
 * status page or a deploy check. Only for schemas that tick “View the Octane
 * readiness summary”. Never returns files, classes, findings or paths.
 *
 * @phpstan-import-type UnnamedFieldDefinitionConfig from FieldDefinition
 */
class ReadinessQuery extends Query
{
    public const string COMPONENT = 'dipstick.readiness:read';

    /** @return array<string, UnnamedFieldDefinitionConfig> */
    public static function getQueries(bool $checkToken = true): array
    {
        if ($checkToken && ! GqlHelper::canSchema('dipstick.readiness')) {
            return [];
        }

        return [
            'dipstickReadiness' => [
                'type' => self::type(),
                'resolve' => function (): ?array {
                    if (! GqlHelper::canSchema('dipstick.readiness')) {
                        throw new UserError('This schema can’t read the Dipstick readiness summary.');
                    }

                    return app(Readiness::class)->summary();
                },
                'description' => 'The latest Dipstick audit: Octane readiness score, grade and finding counts. Null before the first audit.',
            ],
        ];
    }

    private static function type(): ObjectType
    {
        return GqlEntityRegistry::getOrCreate('DipstickReadiness', fn () => new ObjectType([
            'name' => 'DipstickReadiness',
            'description' => 'How ready the site is for Laravel Octane, from the latest Dipstick audit.',
            'fields' => [
                'score' => ['type' => Type::nonNull(Type::int()), 'description' => 'Out of 100.'],
                'grade' => ['type' => Type::nonNull(Type::string()), 'description' => 'A to F.'],
                'ready' => ['type' => Type::nonNull(Type::boolean()), 'description' => 'True when there are no critical or warning findings.'],
                'critical' => ['type' => Type::nonNull(Type::int())],
                'warning' => ['type' => Type::nonNull(Type::int())],
                'notice' => ['type' => Type::nonNull(Type::int())],
                'scannedAt' => ['type' => Type::nonNull(Type::string()), 'description' => 'When the audit ran (ISO 8601).'],
            ],
        ]));
    }
}
