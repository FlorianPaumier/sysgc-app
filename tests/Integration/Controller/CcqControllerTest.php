<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller;

use App\Identity\Domain\Enum\UserRole;
use App\Compliance\Domain\Model\CCQDeclaration;
use App\Tests\Factory\OrganizationFactory;
use App\Tests\Factory\ProjectFactory;
use App\Tests\Factory\UserFactory;
use App\Tests\Integration\IntegrationTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
final class CcqControllerTest extends IntegrationTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    public function test_declarations_is_an_organization_month_workspace_with_context_totals_and_lifecycle(): void
    {
        $organization = OrganizationFactory::createOne(['name' => 'Construction Nord']);
        $owner = UserFactory::createOne(['organization' => $organization, 'roles' => [UserRole::OWNER->value]]);
        ProjectFactory::createOne(['organization' => $organization, 'name' => 'Résidence Nord', 'address' => '100 rue du Nord']);
        ProjectFactory::createOne(['organization' => $organization, 'name' => 'École du Sud', 'address' => '200 rue du Sud']);
        $this->client->loginUser($owner);

        $this->client->request('GET', '/declarations');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('[data-testid="ccq-workspace"]', 'Déclaration CCQ');
        $this->assertSelectorTextContains('[data-testid="ccq-workspace"]', 'CCQ-2026.1');
        $this->assertSelectorTextContains('[data-testid="ccq-statuses"]', 'Brouillon');
        $this->assertSelectorTextContains('[data-testid="ccq-statuses"]', 'Validée');
        $this->assertSelectorTextContains('[data-testid="ccq-statuses"]', 'Exportée');
        $this->assertSelectorTextContains('[data-testid="ccq-statuses"]', 'Déposée');
        $this->assertSelectorExists('[data-testid="ccq-grid"] th[data-sticky="identity"]');
        $this->assertSelectorExists('[data-testid="ccq-grid"] tfoot[data-sticky="totals"]');
    }

    public function test_ccq_pdf_is_only_available_to_the_declaration_organization(): void
    {
        $organization = OrganizationFactory::createOne();
        $owner = UserFactory::createOne(['organization' => $organization, 'roles' => [UserRole::OWNER->value]]);
        $project = ProjectFactory::createOne(['organization' => $organization]);
        $declaration = (new CCQDeclaration())
            ->setOrganization($organization)
            ->setProject($project)
            ->setPeriodMonth(9)
            ->setPeriodYear(2026)
            ->setDeclaration([])
            ->setStatus('exported')
            ->markExported('/uploads/ccq/missing.pdf', new \DateTimeImmutable());
        $this->entityManager()->persist($declaration);
        $this->entityManager()->flush();

        $otherOrganization = OrganizationFactory::createOne();
        $otherOwner = UserFactory::createOne(['organization' => $otherOrganization, 'roles' => [UserRole::OWNER->value]]);
        $this->client->loginUser($otherOwner);
        $this->client->request('GET', '/declarations/'.$declaration->getId().'/pdf');

        $this->assertResponseStatusCodeSame(403);
    }

    public function test_ccq_pdf_can_be_downloaded_by_the_declaration_organization(): void
    {
        $organization = OrganizationFactory::createOne();
        $owner = UserFactory::createOne(['organization' => $organization, 'roles' => [UserRole::OWNER->value]]);
        $project = ProjectFactory::createOne(['organization' => $organization]);
        $filename = 'ccq/'.bin2hex(random_bytes(8)).'.pdf';
        $directory = dirname(__DIR__, 3).'/var/uploads/'.dirname($filename);
        mkdir($directory, 0770, true);
        file_put_contents(dirname(__DIR__, 3).'/var/uploads/'.$filename, '%PDF-1.7 test');
        try {
            $declaration = (new CCQDeclaration())
                ->setOrganization($organization)
                ->setProject($project)
                ->setPeriodMonth(9)
                ->setPeriodYear(2026)
                ->setDeclaration([])
                ->setStatus('exported')
                ->markExported($filename, new \DateTimeImmutable());
            $this->entityManager()->persist($declaration);
            $this->entityManager()->flush();
            $this->client->loginUser($owner);

            $this->client->request('GET', '/declarations/'.$declaration->getId().'/pdf');

            $this->assertResponseIsSuccessful();
            self::assertSame('application/pdf', $this->client->getResponse()->headers->get('content-type'));
        } finally {
            @unlink(dirname(__DIR__, 3).'/var/uploads/'.$filename);
            @rmdir($directory);
        }
    }

    private function entityManager(): \Doctrine\ORM\EntityManagerInterface
    {
        $entityManager = self::getContainer()->get(\Doctrine\ORM\EntityManagerInterface::class);
        self::assertInstanceOf(\Doctrine\ORM\EntityManagerInterface::class, $entityManager);

        return $entityManager;
    }
}
