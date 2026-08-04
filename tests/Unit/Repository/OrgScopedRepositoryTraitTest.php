<?php

declare(strict_types=1);

namespace App\Tests\Unit\Repository;

use App\Entity\Address;
use App\Entity\Organization;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * @covers \App\Repository\OrgScopedRepositoryTrait
 */
final class OrgScopedRepositoryTraitTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private UserRepository $userRepository;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        $this->userRepository = $this->entityManager->getRepository(User::class);
    }

    private function createAddress(string $street, string $postalCode, string $city): Address
    {
        $address = new Address();
        $address->setStreet($street)
            ->setPostalCode($postalCode)
            ->setCity($city)
            ->setCountry('FR');

        return $address;
    }

    public function testCreateQueryBuilderFiltersByOrganization(): void
    {
        $orgA = new Organization();
        $orgA->setName('Org A');
        $orgA->setRbqLicense('RBQ-A');
        $orgA->setAddress($this->createAddress('123 Rue A', 'A1A1A1', 'City A'));

        $orgB = new Organization();
        $orgB->setName('Org B');
        $orgB->setRbqLicense('RBQ-B');
        $orgB->setAddress($this->createAddress('456 Rue B', 'B2B2B2', 'City B'));

        $userA = new User();
        $userA->setEmail('user-a@example.com');
        $userA->setOrganization($orgA);

        $userB = new User();
        $userB->setEmail('user-b@example.com');
        $userB->setOrganization($orgB);

        $this->entityManager->persist($orgA);
        $this->entityManager->persist($orgB);
        $this->entityManager->persist($userA);
        $this->entityManager->persist($userB);
        $this->entityManager->flush();

        // Test query scoped to orgA should only return userA
        $qb = $this->userRepository->createScopedQueryBuilder($orgA);

        $result = $qb->getQuery()->getResult();

        $this->assertCount(1, $result);
        $this->assertSame('user-a@example.com', $result[0]->getEmail());
    }

    public function testFindAllWithinOrganizationReturnsOnlyOrgUsers(): void
    {
        $org = new Organization();
        $org->setName('Test Org');
        $org->setRbqLicense('TEST-123');
        $org->setAddress($this->createAddress('789 Test St', 'T1T1T1', 'Testville'));

        $user1 = new User();
        $user1->setEmail('user1@test.com');
        $user1->setOrganization($org);

        $user2 = new User();
        $user2->setEmail('user2@test.com');
        $user2->setOrganization($org);

        $otherOrg = new Organization();
        $otherOrg->setName('Other Org');
        $otherOrg->setRbqLicense('OTHER-456');
        $otherOrg->setAddress($this->createAddress('999 Other Ave', 'O2O2O2', 'Othertown'));

        $otherUser = new User();
        $otherUser->setEmail('other@other.com');
        $otherUser->setOrganization($otherOrg);

        $this->entityManager->persist($org);
        $this->entityManager->persist($otherOrg);
        $this->entityManager->persist($user1);
        $this->entityManager->persist($user2);
        $this->entityManager->persist($otherUser);
        $this->entityManager->flush();

        $users = $this->userRepository->findAllForOrganization($org->getId());

        $this->assertCount(2, $users);
        $emails = array_map(static fn (User $u) => $u->getEmail(), $users);
        $this->assertContains('user1@test.com', $emails);
        $this->assertContains('user2@test.com', $emails);
        $this->assertNotContains('other@other.com', $emails);
    }

    public function testFindOneOutsideOrganizationReturnsNull(): void
    {
        $orgA = new Organization();
        $orgA->setName('Org A');
        $orgA->setRbqLicense('RBQ-A');
        $orgA->setAddress($this->createAddress('111 Rue A', 'A1A1A1', 'City A'));

        $orgB = new Organization();
        $orgB->setName('Org B');
        $orgB->setRbqLicense('RBQ-B');
        $orgB->setAddress($this->createAddress('222 Rue B', 'B2B2B2', 'City B'));

        $userInOrgB = new User();
        $userInOrgB->setEmail('user-in-b@example.com');
        $userInOrgB->setOrganization($orgB);

        $this->entityManager->persist($orgA);
        $this->entityManager->persist($orgB);
        $this->entityManager->persist($userInOrgB);
        $this->entityManager->flush();

        // Trying to find user from orgB while scoped to orgA should return null
        $result = $this->userRepository->findOneForOrganization($userInOrgB->getId(), $orgA->getId());

        $this->assertNull($result);
    }
}
