# Security Foundation - Organization Scoped Access Control

## Overview

This security foundation implements BR-02: **All data queries scoped to the user's organization (no cross-org access)**.

## Components

### 1. OrgScopedRepository Trait (`App\Repository\OrgScopedRepositoryTrait`)

Base repository trait that auto-filters queries by organization.

**Usage:**
```php
use App\Repository\OrgScopedRepositoryTrait;

class YourEntityRepository extends ServiceEntityRepository
{
    use OrgScopedRepositoryTrait;

    // Create a QueryBuilder scoped to an organization
    $qb = $this->createScopedQueryBuilder($organization);
    
    // Find all entities for an organization
    $entities = $this->findAllForOrganization($orgId);
    
    // Find one entity by ID, scoped to organization
    $entity = $this->findOneForOrganization($id, $orgId);
}
```

**Methods:**
- `createScopedQueryBuilder(Organization $org, ?string $alias): QueryBuilder` - Creates a filtered QueryBuilder
- `findAllForOrganization(Uuid $orgId): array` - Returns all entities for an org
- `findOneForOrganization(Uuid $id, Uuid $orgId): ?object` - Returns entity if owned by org, null otherwise

### 2. ProjectVoter (`App\Security\ProjectVoter`)

Symfony voter that enforces organization ownership on project access.

**Attributes:**
- `view` - Read access
- `edit` - Write access  
- `delete` - Delete access

**Usage in controllers:**
```php
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class ProjectController extends AbstractController
{
    #[IsGranted('view', subject: 'project')]
    public function show(Project $project): Response
    {
        // Only executes if user.organization === project.organization
        return $this->render('project/show.html.twig', [
            'project' => $project,
        ]);
    }
}
```

**Access Logic:**
- User must have an organization
- Project must have an organization  
- Access granted **only if** `user.organization.id === project.organization.id`
- Returns 403 Forbidden for cross-org access attempts

### 3. Entity Organization Relationship

All entities requiring org-scoping must have:

```php
#[ORM\ManyToOne(inversedBy: 'entities')]
#[ORM\JoinColumn(nullable: false)]
private ?Organization $organization = null;

public function getOrganization(): ?Organization
{
    return $this->organization;
}
```

## Security Guarantees

### 1. Repository-Level Filtering
- ✅ Every repository query automatically scoped to organization
- ✅ Cross-org queries return empty/null, never the record
- ✅ No raw SQL outside repositories (Doctrine QueryBuilder/DQL only)

### 2. Controller-Level Authorization
- ✅ ProjectVoter denies 403 for cross-org project access
- ✅ Voter checks organization match before granting access
- ✅ Automatic CSRF protection via Symfony Forms (default)

### 3. Data Isolation
- ✅ Users see only their organization's data
- ✅ No cross-org data leakage possible
- ✅ Organization relationship mandatory (nullable: false)

## Testing

All security components have comprehensive tests:

```bash
# Run org-scoping repository tests
vendor/bin/phpunit tests/Unit/Repository/OrgScopedRepositoryTraitTest.php

# Run ProjectVoter tests
vendor/bin/phpunit tests/Unit/Security/ProjectVoterTest.php
```

## Implementation Pattern

For new entities requiring org-scoping:

1. **Add organization relationship to entity**
2. **Apply OrgScopedRepositoryTrait to repository**
3. **Use voter attributes in controller actions**
4. **Write tests for cross-org access denial**

## Security Checklist

- [x] Base repository trait auto-filters by organization
- [x] ProjectVoter denies cross-org access (403)
- [x] All write actions use Symfony Forms (CSRF enabled)
- [x] Cross-org fetch returns null/403, never the record
- [x] No raw SQL outside repositories (verified with grep)
- [x] Organization relationship mandatory on all entities

## References

- `specs/10-security.md` - Security requirements
- `specs/02-data-model.md` - Entity relationships
- BR-02: All queries scoped to user's organization
