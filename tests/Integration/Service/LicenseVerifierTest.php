<?php

declare(strict_types=1);

namespace App\Tests\Integration\Service;

use App\Identity\Domain\Model\Address;
use App\Identity\Domain\Model\Organization;
use App\Project\Domain\Model\Project;
use App\Compliance\Domain\Model\Subcontractor;
use App\Compliance\Application\LicenseVerifier;
use App\Compliance\Domain\ValueObject\RbqLicenseResult;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\ResetDatabase;

final class LicenseVerifierTest extends WebTestCase
{
    use ResetDatabase;

    private LicenseVerifier $verifier;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $verifier = $container->get(LicenseVerifier::class);
        self::assertInstanceOf(LicenseVerifier::class, $verifier);
        $this->verifier = $verifier;

        $entityManager = $container->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $this->entityManager = $entityManager;
    }

    public function test_verifySingle_without_a_license_requires_manual_review_without_a_false_success(): void
    {
        $address = new Address();
        $address->setStreet('123 Main St')
            ->setPostalCode('H2X 1Y4')
            ->setCity('Montréal')
            ->setCountry('CA');

        $org = new Organization();
        $org->setName('Test Org');
        $org->setAddress($address);
        $org->setRbqLicense('RBQ-9999-9999-99');
        $this->entityManager->persist($org);

        $project = new Project($org, '123 Test St', 'Test Client');
        $this->entityManager->persist($project);

        $subcontractor = new Subcontractor();
        $subcontractor->setName('No License Sub');
        $project->addSubcontractor($subcontractor);
        $this->entityManager->persist($subcontractor);

        $this->entityManager->flush();

        $result = $this->verifier->verifySingle($subcontractor);

        $this->assertFalse($result->found);
        $this->assertFalse($subcontractor->isVerified());
        $this->assertNull($subcontractor->getLastVerifiedAt());
        $this->assertTrue($subcontractor->requiresManualReview());
        $this->assertNotNull($subcontractor->getLastVerificationAttemptAt());
    }

    public function test_verifyProject_returns_counts_for_all_subcontractors(): void
    {
        $address = new Address();
        $address->setStreet('789 Project Blvd')
            ->setPostalCode('H3A 1A1')
            ->setCity('Montréal')
            ->setCountry('CA');

        $org = new Organization();
        $org->setName('Test Org');
        $org->setAddress($address);
        $org->setRbqLicense('RBQ-7777-7777-77');
        $this->entityManager->persist($org);

        $project = new Project($org, '123 Test St', 'Test Client');
        $this->entityManager->persist($project);

        // Create 3 subcontractors, 2 with licenses
        $sub1 = new Subcontractor();
        $sub1->setName('Sub 1');
        $sub1->setRbqLicenseNumber('RBQ-1111-1111-11');
        $project->addSubcontractor($sub1);

        $sub2 = new Subcontractor();
        $sub2->setName('Sub 2');
        $sub2->setRbqLicenseNumber('RBQ-2222-2222-22');
        $project->addSubcontractor($sub2);

        $sub3 = new Subcontractor();
        $sub3->setName('Sub 3');
        $project->addSubcontractor($sub3);

        $this->entityManager->persist($sub1);
        $this->entityManager->persist($sub2);
        $this->entityManager->persist($sub3);
        $this->entityManager->flush();

        $result = $this->verifier->verifyProject($project);

        $this->assertSame(3, $result['accepted']);
        $this->assertSame(2, $result['queued']);
    }
}
