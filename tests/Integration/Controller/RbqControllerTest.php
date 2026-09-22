<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller;

use App\Compliance\Domain\Model\Subcontractor;
use App\Identity\Domain\Enum\UserRole;
use App\Tests\Factory\OrganizationFactory;
use App\Tests\Factory\ProjectFactory;
use App\Tests\Factory\UserFactory;
use App\Tests\Integration\IntegrationTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\DomCrawler\Field\ChoiceFormField;
use Symfony\Component\DomCrawler\Field\FileFormField;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
final class RbqControllerTest extends IntegrationTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    public function test_list_displays_last_successful_verification_and_evidence_metadata(): void
    {
        [$project] = $this->authenticatedProject();
        $subcontractor = (new Subcontractor())
            ->setName('Toitures Exemple')
            ->setRbqLicenseNumber('RBQ-1234-5678-90')
            ->setVerified(true)
            ->setLastVerifiedAt(new \DateTimeImmutable('2026-09-14 13:45:00'));
        $project->addSubcontractor($subcontractor);
        $entityManager = $this->entityManager();
        $entityManager->persist($subcontractor);
        $entityManager->flush();

        $this->client->request('GET', '/chantiers/'.$project->getId().'/rbq');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('main', 'Toitures Exemple');
        $this->assertSelectorTextContains('main', 'Dernière vérification réussie');
        $this->assertSelectorTextContains('main', '14/09/2026');
        $this->assertSelectorExists('a[href$="/rbq/ajouter"]');
        $this->assertSelectorExists('a[href$="/rbq/import"]');
        $this->assertSelectorExists('[aria-live="polite"]');
    }

    public function test_add_form_collects_manual_source_notes_and_evidence(): void
    {
        [$project] = $this->authenticatedProject();

        $this->client->request('GET', '/chantiers/'.$project->getId().'/rbq/ajouter');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('input[name="subcontractor[manuallyVerified]"]');
        $this->assertSelectorExists('input[name="subcontractor[manualVerificationSource]"]');
        $this->assertSelectorExists('textarea[name="subcontractor[manualVerificationNotes]"]');
        $this->assertSelectorExists('input[type="file"][name="subcontractor[manualVerificationEvidence]"]');
    }

    public function test_manual_verification_records_actor_source_notes_and_evidence_name(): void
    {
        [$project, $owner] = $this->authenticatedProject();
        $crawler = $this->client->request('GET', '/chantiers/'.$project->getId().'/rbq/ajouter');
        $form = $crawler->selectButton('Ajouter')->form();
        $form['subcontractor[name]'] = 'Excavation Manuelle';
        $form['subcontractor[rbqLicenseNumber]'] = 'RBQ-4321-8765-09';
        $form['subcontractor[category]'] = 'Excavation';
        $form['subcontractor[expiryDate]'] = '2027-09-30';
        $manualField = $form->get('subcontractor[manuallyVerified]');
        self::assertInstanceOf(ChoiceFormField::class, $manualField);
        $manualField->tick();
        $form['subcontractor[manualVerificationSource]'] = 'Registre RBQ consulté par téléphone';
        $form['subcontractor[manualVerificationNotes]'] = 'Confirmation reçue de l’agente RBQ.';
        $evidenceField = $form->get('subcontractor[manualVerificationEvidence]');
        self::assertInstanceOf(FileFormField::class, $evidenceField);
        $evidenceField->upload(__DIR__.'/../../Fixtures/rbq-evidence.pdf');

        $this->client->submit($form);

        $this->assertResponseRedirects('/chantiers/'.$project->getId().'/rbq');
        $entityManager = $this->entityManager();
        $stored = $entityManager->getRepository(Subcontractor::class)->findOneBy(['name' => 'Excavation Manuelle']);
        self::assertInstanceOf(Subcontractor::class, $stored);
        self::assertTrue($stored->isManuallyVerified());
        self::assertSame('Registre RBQ consulté par téléphone', $stored->getVerificationSource());
        self::assertSame('Confirmation reçue de l’agente RBQ.', $stored->getManualVerificationNotes());
        self::assertSame('rbq-evidence.pdf', $stored->getEvidenceOriginalName());
        self::assertSame($owner->getId()->toRfc4122(), $stored->getVerifiedBy()?->getId()->toRfc4122());
        self::assertNotNull($stored->getLastVerifiedAt());
    }

    public function test_foreman_cannot_manage_rbq_subcontractors(): void
    {
        $organization = OrganizationFactory::createOne();
        $project = ProjectFactory::createOne(['organization' => $organization]);
        $foreman = UserFactory::createOne(['organization' => $organization, 'roles' => [UserRole::FOREMAN->value]]);
        $this->client->loginUser($foreman);

        $this->client->request('GET', '/chantiers/'.$project->getId().'/rbq/ajouter');

        $this->assertResponseStatusCodeSame(403);
    }

    public function test_single_verification_returns_to_the_list_with_manual_review_state(): void
    {
        [$project] = $this->authenticatedProject();
        $subcontractor = (new Subcontractor())->setName('Sans numéro de licence');
        $project->addSubcontractor($subcontractor);
        $entityManager = $this->entityManager();
        $entityManager->persist($subcontractor);
        $entityManager->flush();
        $crawler = $this->client->request('GET', '/chantiers/'.$project->getId().'/rbq');

        $this->client->submit($crawler->filter('#subcontractor-'.$subcontractor->getId().' form')->form());

        $this->assertResponseRedirects('/chantiers/'.$project->getId().'/rbq');
        $stored = $this->entityManager()->getRepository(Subcontractor::class)->find($subcontractor->getId());
        self::assertInstanceOf(Subcontractor::class, $stored);
        self::assertTrue($stored->requiresManualReview());
        $this->client->followRedirect();
        $this->assertSelectorTextContains('#subcontractor-'.$subcontractor->getId(), 'Vérification manuelle requise');
    }

    public function test_evidence_is_not_available_to_another_organization(): void
    {
        [$project, $owner] = $this->authenticatedProject();
        $subcontractor = (new Subcontractor())->setName('Preuve privée');
        $subcontractor->recordManualVerification('Registre RBQ', 'Note', 'preuve.pdf', 'rbq_evidence/private/proof.pdf', $owner, new \DateTimeImmutable());
        $project->addSubcontractor($subcontractor);
        $this->entityManager()->persist($subcontractor);
        $this->entityManager()->flush();

        $otherOrganization = OrganizationFactory::createOne();
        $otherOwner = UserFactory::createOne(['organization' => $otherOrganization, 'roles' => [UserRole::OWNER->value]]);
        $this->client->loginUser($otherOwner);
        $this->client->request('GET', '/chantiers/'.$project->getId().'/rbq/'.$subcontractor->getId().'/evidence');

        $this->assertResponseStatusCodeSame(403);
    }

    /** @return array{0: \App\Project\Domain\Model\Project, 1: \App\Identity\Domain\Model\User} */
    private function authenticatedProject(): array
    {
        $organization = OrganizationFactory::createOne();
        $owner = UserFactory::createOne(['organization' => $organization, 'roles' => [UserRole::OWNER->value]]);
        $project = ProjectFactory::createOne(['organization' => $organization, 'address' => '100 rue du Chantier']);
        $this->client->loginUser($owner);

        return [$project, $owner];
    }

    private function entityManager(): EntityManagerInterface
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        return $entityManager;
    }
}
