<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Entity\Organization;
use App\Entity\Project;
use App\Entity\Subcontractor;
use Zenstruck\Foundry\ModelFactory;
use Zenstruck\Foundry\Proxy;
use Zenstruck\Foundry\RepositoryProxy;

/**
 * @extends ModelFactory<Subcontractor>
 *
 * @method static Subcontractor|Proxy create(array|callable $attributes = [])
 * @method static Subcontractor|Proxy createOne(array $attributes = [])
 * @method static Subcontractor|Proxy find(object|array|mixed $criteria)
 * @method static Subcontractor|Proxy findOrCreate(array $attributes)
 * @method static Subcontractor|Proxy first(string $sortedField = 'id')
 * @method static Subcontractor|Proxy last(string $sortedField = 'id')
 * @method static Subcontractor|Proxy random(array $attributes = [])
 * @method static Subcontractor|Proxy randomOrCreate(array $attributes = [])
 * @method static Subcontractor[]|Proxy[] all()
 * @method static Subcontractor[]|Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static Subcontractor[]|Proxy[] createSequence(array|sequence $attributes)
 * @method static SubcontractorRepository|RepositoryProxy repository()
 * @method Subcontractor|Proxy create(array|callable $attributes = [])
 */
final class SubcontractorFactory extends ModelFactory
{
    public function __construct()
    {
        parent::__construct();
    }

    protected function getDefaults(): array
    {
        return [
            'name' => self::faker()->company(),
            'rbqLicenseNumber' => self::faker()->optional()->regex('RBQ-[0-9]{4}-[0-9]{4}-[0-9]{2}'),
            'isVerified' => false,
            'lastVerifiedAt' => null,
        ];
    }

    protected function initialize(): self
    {
        return $this
            ->afterInstantiate(function (Subcontractor $subcontractor) {
                // Optionally add to a random project if needed
            })
        ;
    }

    protected static function getClass(): string
    {
        return Subcontractor::class;
    }
}
