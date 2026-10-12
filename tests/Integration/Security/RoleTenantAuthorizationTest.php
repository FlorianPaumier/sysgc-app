<?php

declare(strict_types=1);

namespace App\Tests\Integration\Security;

use App\Identity\Domain\Enum\UserRole;
use App\Identity\Domain\Model\Invitation;
use App\Identity\Infrastructure\Doctrine\UserRepository;
use App\Project\Domain\Enum\ProjectStatus;
use App\Tests\Factory\OrganizationFactory;
use App\Tests\Factory\ProjectFactory;
use App\Tests\Factory\UserFactory;
use App\Tests\Integration\IntegrationTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * HTTP authorization coverage for role, tenant, assignment, invitation, and archive boundaries.
 */
#[ResetDatabase]
final class RoleTenantAuthorizationTest extends IntegrationTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    public function test_foreman_can_view_an_assigned_project_but_not_an_unassigned_or_cross_tenant_project(): void
    {
        $organization = OrganizationFactory::createOne();
        $foreman = UserFactory::createOne([
            'organization' => $organization,
            'roles' => [UserRole::FOREMAN->value],
        ]);
        $assigned = ProjectFactory::createOne(['organization' => $organization]);
        $unassigned = ProjectFactory::createOne(['organization' => $organization]);
        $otherProject = ProjectFactory::createOne(['address' => 'Tenant B']);
        $foreman->assignProject($assigned);
        $this->entityManager()->flush();

        $this->client->loginUser($foreman);
        $this->client->request('GET', '/chantiers/'.$assigned->getId());
        self::assertResponseIsSuccessful();

        $this->client->request('GET', '/chantiers/'.$unassigned->getId());
        self::assertResponseStatusCodeSame(403);

        $this->client->request('GET', '/chantiers/'.$otherProject->getId());
        self::assertResponseStatusCodeSame(403);
    }

    public function test_foreman_cannot_use_project_management_actions_even_for_an_assigned_project(): void
    {
        $organization = OrganizationFactory::createOne();
        $foreman = UserFactory::createOne([
            'organization' => $organization,
            'roles' => [UserRole::FOREMAN->value],
        ]);
        $project = ProjectFactory::createOne(['organization' => $organization]);
        $foreman->assignProject($project);
        $this->entityManager()->flush();
        $this->client->loginUser($foreman);

        $this->client->request('GET', '/chantiers/'.$project->getId().'/modifier');
        self::assertResponseStatusCodeSame(403);
        $this->client->request('POST', '/chantiers/'.$project->getId().'/archiver');
        self::assertResponseStatusCodeSame(403);
        $this->client->request('GET', '/chantiers/'.$project->getId().'/rbq/ajouter');
        self::assertResponseStatusCodeSame(403);
    }

    public function test_archived_projects_are_read_only_for_owner_on_all_mutating_project_surfaces(): void
    {
        $organization = OrganizationFactory::createOne();
        $owner = UserFactory::createOne([
            'organization' => $organization,
            'roles' => [UserRole::OWNER->value],
        ]);
        $project = ProjectFactory::createOne([
            'organization' => $organization,
            'status' => ProjectStatus::ARCHIVED,
        ]);
        $this->client->loginUser($owner);

        $this->client->request('GET', '/chantiers/'.$project->getId().'/modifier');
        self::assertResponseStatusCodeSame(403);
        $this->client->request('GET', '/chantiers/'.$project->getId().'/rbq/ajouter');
        self::assertResponseStatusCodeSame(403);
        $this->client->request('GET', '/chantiers/'.$project->getId().'/rbq/import');
        self::assertResponseStatusCodeSame(403);
        $this->client->request('POST', '/chantiers/'.$project->getId().'/archiver');
        self::assertResponseStatusCodeSame(403);
    }

    public function test_admin_cannot_transfer_ownership_but_owner_can_transfer_to_same_tenant_active_member(): void
    {
        $organization = OrganizationFactory::createOne();
        $owner = UserFactory::createOne([
            'organization' => $organization,
            'roles' => [UserRole::OWNER->value],
        ]);
        $admin = UserFactory::createOne([
            'organization' => $organization,
            'roles' => [UserRole::ADMIN->value],
        ]);
        $this->client->loginUser($admin);
        $this->client->request('POST', '/organisation/membres/'.$owner->getId().'/transferer-propriete');
        self::assertResponseStatusCodeSame(403);

        $this->client->loginUser($owner);
        $crawler = $this->client->request('GET', '/organisation/membres');
        $this->client->submit($crawler->filter('form[action="/organisation/membres/'.$admin->getId().'/transferer-propriete"]')->form());
        self::assertResponseRedirects('/organisation/membres');
        $users = self::getContainer()->get(UserRepository::class);
        self::assertInstanceOf(UserRepository::class, $users);
        $reloadedAdmin = $users->find($admin->getId());
        $reloadedOwner = $users->find($owner->getId());
        self::assertNotNull($reloadedAdmin);
        self::assertNotNull($reloadedOwner);
        self::assertTrue($reloadedAdmin->isOwner());
        self::assertFalse($reloadedOwner->isOwner());
    }

    public function test_invitation_acceptance_is_single_use_organization_bound_and_assigns_foreman_projects(): void
    {
        $organization = OrganizationFactory::createOne();
        $project = ProjectFactory::createOne(['organization' => $organization]);
        $rawToken = 'role-tenant-invitation-token';
        $invitation = new Invitation($organization, 'invited@example.com', UserRole::FOREMAN, hash('sha256', $rawToken));
        $invitation->assignProject($project);
        $entityManager = $this->entityManager();
        $entityManager->persist($invitation);
        $entityManager->flush();

        $this->client->request('GET', '/invitation/accepter?token='.$rawToken);
        self::assertResponseRedirects('/dashboard/terrain');
        $repository = self::getContainer()->get(UserRepository::class);
        self::assertInstanceOf(UserRepository::class, $repository);
        $member = $repository->findOneBy(['email' => 'invited@example.com']);
        self::assertNotNull($member);
        self::assertSame($organization->getId()->toRfc4122(), $member->getOrganization()?->getId()->toRfc4122());
        self::assertTrue($member->isForeman());
        self::assertTrue($member->getAssignedProjects()->contains($project));

        $this->client->getCookieJar()->clear();
        $this->client->request('GET', '/invitation/accepter?token='.$rawToken);
        self::assertResponseStatusCodeSame(404);
    }

    private function entityManager(): EntityManagerInterface
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        return $entityManager;
    }
}
