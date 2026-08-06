<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Enum\ProjectStatus;
use App\Entity\Organization;
use App\Entity\Project;
use Zenstruck\Foundry\ModelFactory;
use Zenstruck\Foundry\Proxy as FoundryProxy;

/**
 * @extends ModelFactory<Project>
 *
 * @method static Project|FoundryProxy create(array|callable $attributes = [])
 * @method static Project[]|FoundryProxy[] createMany(int $number, array|callable $attributes = [])
 *
 * @phpstan-type TModel = Project
 * @phpstan-type TProxy = FoundryProxy&Project
 */
final class ProjectFactory extends ModelFactory
{
    #[\Override]
    protected function defaults(): array|callable
    {
        return [
            'address' => self::faker()->streetAddress(),
            'clientName' => self::faker()->company(),
            'startDate' => new \DateTime(self::faker()->date()),
            'endDate' => self::faker()->optional(0.7)->passthrough(new \DateTime(self::faker()->date())),
            'status' => self::faker()->randomElement(ProjectStatus::cases()),
            'budget' => self::faker()->randomFloat(2, 10000, 500000),
            'organization' => OrganizationFactory::new(),
        ];
    }

    #[\Override]
    protected function initialize(): self
    {
        return $this->instantiate(fn(array $attributes) => new Project(
            $attributes['organization'],
            $attributes['address'],
            $attributes['clientName']
        ));
    }

    #[\Override]
    public static function class(): string
    {
        return Project::class;
    }

    public function active(): self
    {
        return $this->with(['status' => ProjectStatus::ACTIVE]);
    }

    public function completed(): self
    {
        return $this->with(['status' => ProjectStatus::COMPLETED]);
    }

    public function onHold(): self
    {
        return $this->with(['status' => ProjectStatus::ON_HOLD]);
    }
}
