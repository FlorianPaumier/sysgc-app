<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Project\Domain\Enum\ProjectStatus;
use App\Project\Domain\Model\Project;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Project>
 */
final class ProjectFactory extends PersistentObjectFactory
{
    #[\Override]
    protected function defaults(): array|callable
    {
        return [
            'address' => self::faker()->streetAddress(),
            'clientName' => self::faker()->company(),
            'startDate' => new \DateTime(self::faker()->date()),
            'endDate' => self::faker()->optional(0.7)->passthrough(new \DateTime(self::faker()->date())),
            // Test projects should be usable by default; archived/read-only
            // projects are created explicitly through archived().
            'status' => ProjectStatus::ACTIVE,
            'budget' => self::faker()->randomFloat(2, 10000, 500000),
            'organization' => OrganizationFactory::new(),
        ];
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

    public function archived(): self
    {
        return $this->with(['status' => ProjectStatus::ARCHIVED]);
    }

    public function onHold(): self
    {
        return $this->with(['status' => ProjectStatus::ON_HOLD]);
    }
}
