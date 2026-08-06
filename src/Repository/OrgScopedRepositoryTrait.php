<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Organization;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Uid\Uuid;

/**
 * Repository trait for organization-scoped queries.
 *
 * Prevents cross-organization data access by automatically filtering
 * queries to the user's organization. Use this trait in any repository
 * that manages entities with an organization relationship.
 *
 * @see specs/10-security.md (BR-02)
 */
trait OrgScopedRepositoryTrait
{
    /**
     * Create a QueryBuilder scoped to a specific organization.
     *
     * @param Organization $organization the organization to scope to
     * @param string|null  $alias        the entity alias (defaults to the entity name)
     *
     * @return QueryBuilder the scoped query builder
     */
    public function createScopedQueryBuilder(Organization $organization, ?string $alias = null): QueryBuilder
    {
        $alias ??= $this->getAlias();
        $qb = $this->createQueryBuilder($alias);

        $this->applyOrganizationFilter($qb, $organization, $alias);

        return $qb;
    }

    /**
     * Apply organization filter to an existing QueryBuilder.
     *
     * @param QueryBuilder $qb            the query builder to modify
     * @param Organization  $organization  the organization to filter by
     * @param string        $alias         the entity alias
     */
    protected function applyOrganizationFilter(QueryBuilder $qb, Organization $organization, string $alias): void
    {
        $qb->andWhere(\sprintf('%s.organization = :org', $alias))
            ->setParameter('org', $organization->getId(), 'uuid');
    }

    /**
     * Find all entities for a specific organization.
     *
     * @param Uuid $orgId the organization ID
     *
     * @return array<int, object> the entities
     *
     * @phpstan-return array<int, object>
     */
    public function findAllForOrganization(Uuid $orgId): array
    {
        $alias = $this->getAlias();

        /** @var array<int, object> */
        return $this->createQueryBuilder($alias)
            ->join(\sprintf('%s.organization', $alias), 'o')
            ->andWhere('o.id = :orgId')
            ->setParameter('orgId', $orgId, 'uuid')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find one entity by ID scoped to an organization.
     *
     * Returns null if the entity exists but belongs to a different organization.
     *
     * @param Uuid $id    the entity ID
     * @param Uuid $orgId the organization ID
     *
     * @return object|null the entity or null if not found or wrong org
     *
     * @phpstan-return object|null
     */
    public function findOneForOrganization(Uuid $id, Uuid $orgId): ?object
    {
        $alias = $this->getAlias();

        /** @var object|null */
        return $this->createQueryBuilder($alias)
            ->andWhere(\sprintf('%s.id = :id', $alias))
            ->join(\sprintf('%s.organization', $alias), 'o')
            ->andWhere('o.id = :orgId')
            ->setParameter('id', $id, 'uuid')
            ->setParameter('orgId', $orgId, 'uuid')
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Get the entity alias for this repository.
     *
     * @return string the alias (e.g., 'u' for User, 'p' for Project)
     */
    protected function getAlias(): string
    {
        // Derive alias from entity class name (e.g., 'App\Entity\User' => 'u')
        $entityClass = $this->getEntityName();
        $parts = explode('\\', $entityClass);
        $className = end($parts);

        // First letter lowercase (User => u, Project => p)
        return strtolower($className[0]);
    }
}
