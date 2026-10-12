<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller;

use App\Identity\Domain\Enum\UserRole;
use App\Identity\Domain\Model\Invitation;
use App\Identity\Domain\Model\User;
use App\Identity\Infrastructure\Doctrine\UserRepository;
use App\Identity\Infrastructure\Doctrine\InvitationRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Tests\Factory\OrganizationFactory;
use App\Tests\Factory\ProjectFactory;
use App\Tests\Factory\UserFactory;
use App\Tests\Integration\IntegrationTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
final class MembershipControllerTest extends IntegrationTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    public function test_members_page_displays_role_vocabulary_for_the_current_organization_only(): void
    {
        $organization = OrganizationFactory::createOne();
        $owner = UserFactory::createOne(['email' => 'owner@example.com', 'organization' => $organization, 'roles' => [UserRole::OWNER->value]]);
        UserFactory::createOne(['email' => 'admin@example.com', 'organization' => $organization, 'roles' => [UserRole::ADMIN->value]]);
        UserFactory::createOne(['email' => 'hidden@example.com', 'roles' => [UserRole::ADMIN->value]]);

        $this->client->loginUser($owner);
        $this->client->request('GET', '/organisation/membres');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'Propriétaire');
        $this->assertSelectorTextContains('body', 'Administrateur');
        $this->assertSelectorTextNotContains('body', 'hidden@example.com');
        $this->assertSelectorExists('form[name="invitation"]');
    }

    public function test_foreman_cannot_open_members_page(): void
    {
        $foreman = UserFactory::createOne(['roles' => [UserRole::FOREMAN->value]]);
        $this->client->loginUser($foreman);

        $this->client->request('GET', '/organisation/membres');

        $this->assertResponseStatusCodeSame(403);
    }

    public function test_inviting_foreman_binds_invitation_to_organization_and_selected_projects(): void
    {
        $organization = OrganizationFactory::createOne();
        $owner = UserFactory::createOne(['organization' => $organization, 'roles' => [UserRole::OWNER->value]]);
        $project = ProjectFactory::createOne(['organization' => $organization, 'address' => '100 rue du Chantier']);

        $this->client->loginUser($owner);
        $this->client->request('GET', '/organisation/membres');
        $this->client->submitForm('Inviter', [
            'invitation[email]' => 'Foreman@Example.com',
            'invitation[role]' => UserRole::FOREMAN->value,
            'invitation[projects]' => [(string) $project->getId()],
        ]);

        $this->assertResponseRedirects('/organisation/membres');
        $invitationRepository = self::getContainer()->get(InvitationRepository::class);
        self::assertInstanceOf(InvitationRepository::class, $invitationRepository);
        $invitation = $invitationRepository->findOneBy(['email' => 'foreman@example.com']);
        self::assertInstanceOf(Invitation::class, $invitation);
        self::assertSame((string) $organization->getId(), (string) $invitation->getOrganization()->getId());
        $assignedProjectIds = array_map(
            static fn ($assignedProject): string => (string) $assignedProject->getId(),
            $invitation->getAssignedProjects()->toArray(),
        );
        self::assertContains((string) $project->getId(), $assignedProjectIds);
    }

    public function test_invitation_magic_link_creates_member_once_and_lands_on_role_dashboard(): void
    {
        $organization = OrganizationFactory::createOne();
        $rawToken = 'raw-invitation-token';
        $invitation = new Invitation($organization, 'new-admin@example.com', UserRole::ADMIN, hash('sha256', $rawToken));
        $entityManager = $this->entityManager();
        $entityManager->persist($invitation);
        $entityManager->flush();

        $this->client->request('GET', '/invitation/accepter?token='.$rawToken);

        $this->assertResponseRedirects('/dashboard');
        $member = $this->users()->findOneBy(['email' => 'new-admin@example.com']);
        self::assertInstanceOf(User::class, $member);
        self::assertTrue($member->isAdmin());
        self::assertSame($organization->getId()->toRfc4122(), $member->getOrganization()?->getId()->toRfc4122());
    }

    public function test_owner_can_revoke_pending_invitation(): void
    {
        $organization = OrganizationFactory::createOne();
        $owner = UserFactory::createOne(['organization' => $organization, 'roles' => [UserRole::OWNER->value]]);
        $invitation = new Invitation($organization, 'pending@example.com', UserRole::ADMIN, hash('sha256', 'token'));
        $em = $this->entityManager();
        $em->persist($invitation);
        $em->flush();

        $this->client->loginUser($owner);
        $crawler = $this->client->request('GET', '/organisation/membres');
        $this->client->submit($crawler->filter('form[action="/organisation/membres/invitations/'.$invitation->getId().'/revoquer"]')->form());

        $this->assertResponseRedirects('/organisation/membres');
        self::assertTrue($this->entityManager()->find(Invitation::class, $invitation->getId())?->isRevoked());
    }

    public function test_owner_can_deactivate_member_and_member_is_marked_inactive(): void
    {
        $organization = OrganizationFactory::createOne();
        $owner = UserFactory::createOne(['organization' => $organization, 'roles' => [UserRole::OWNER->value]]);
        $member = UserFactory::createOne(['organization' => $organization, 'roles' => [UserRole::ADMIN->value]]);

        $this->client->loginUser($owner);
        $crawler = $this->client->request('GET', '/organisation/membres');
        $this->client->submit($crawler->filter('form[action="/organisation/membres/'.$member->getId().'/desactiver"]')->form());

        $this->assertResponseRedirects('/organisation/membres');
        self::assertFalse($this->users()->find($member->getId())?->isActive());
    }

    public function test_owner_can_transfer_ownership_to_an_active_member(): void
    {
        $organization = OrganizationFactory::createOne();
        $owner = UserFactory::createOne(['organization' => $organization, 'roles' => [UserRole::OWNER->value]]);
        $member = UserFactory::createOne(['organization' => $organization, 'roles' => [UserRole::ADMIN->value]]);

        $this->client->loginUser($owner);
        $crawler = $this->client->request('GET', '/organisation/membres');
        $this->client->submit($crawler->filter('form[action="/organisation/membres/'.$member->getId().'/transferer-propriete"]')->form());

        $this->assertResponseRedirects('/organisation/membres');
        self::assertTrue($this->users()->find($member->getId())?->isOwner());
        self::assertFalse($this->users()->find($owner->getId())?->isOwner());
    }

    private function entityManager(): EntityManagerInterface
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        return $entityManager;
    }

    private function users(): UserRepository
    {
        $repository = self::getContainer()->get(UserRepository::class);
        self::assertInstanceOf(UserRepository::class, $repository);

        return $repository;
    }
}
