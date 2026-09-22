<?php

declare(strict_types=1);

namespace App\Tests\Project\Integration\Presentation;

use App\Identity\Domain\Enum\UserRole;
use App\Project\Domain\Enum\ProjectStatus;
use App\Project\Domain\Model\Project;
use App\Project\Infrastructure\Doctrine\ProjectRepository;
use App\Tests\Factory\OrganizationFactory;
use App\Tests\Factory\ProjectFactory;
use App\Tests\Factory\UserFactory;
use App\Tests\Integration\IntegrationTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
final class ProjectControllerTest extends IntegrationTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    public function test_default_list_shows_current_projects_and_archive_filter_is_explicit(): void
    {
        $organization = OrganizationFactory::createOne();
        $owner = UserFactory::createOne([
            'organization' => $organization,
            'roles' => [UserRole::OWNER->value],
        ]);
        ProjectFactory::createOne(['organization' => $organization, 'address' => '10 rue Active', 'status' => ProjectStatus::ACTIVE]);
        ProjectFactory::createOne(['organization' => $organization, 'address' => '20 rue Pause', 'status' => ProjectStatus::ON_HOLD]);
        ProjectFactory::createOne(['organization' => $organization, 'address' => '30 rue Archive', 'status' => ProjectStatus::ARCHIVED]);

        $this->client->loginUser($owner);
        $this->client->request('GET', '/chantiers');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('main', '10 rue Active');
        $this->assertSelectorTextContains('main', '20 rue Pause');
        $this->assertSelectorTextNotContains('main', '30 rue Archive');
        $this->assertSelectorTextContains('[data-project-filter="current"]', 'En cours');
        $this->assertSelectorTextContains('.status-badge--on-hold', 'En pause');

        $this->client->request('GET', '/chantiers?status=archived');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('main', '30 rue Archive');
        $this->assertSelectorTextNotContains('main', '10 rue Active');
        $this->assertSelectorExists('[data-project-filter="archived"][aria-current="page"]');
    }

    public function test_foreman_list_contains_only_assigned_projects_and_no_management_action(): void
    {
        $organization = OrganizationFactory::createOne();
        $foreman = UserFactory::createOne([
            'organization' => $organization,
            'roles' => [UserRole::FOREMAN->value],
        ]);
        $assigned = ProjectFactory::createOne(['organization' => $organization, 'address' => '40 rue Assignée', 'status' => ProjectStatus::ACTIVE]);
        ProjectFactory::createOne(['organization' => $organization, 'address' => '50 rue Cachée', 'status' => ProjectStatus::ACTIVE]);
        $foreman->assignProject($assigned);
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $entityManager->flush();

        $this->client->loginUser($foreman);
        $this->client->request('GET', '/chantiers');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Mes chantiers');
        $this->assertSelectorTextContains('main', '40 rue Assignée');
        $this->assertSelectorTextNotContains('main', '50 rue Cachée');
        $this->assertSelectorNotExists('a[href="/chantiers/nouveau"]');
    }

    public function test_project_detail_exposes_five_shareable_tabs_in_one_turbo_frame(): void
    {
        $organization = OrganizationFactory::createOne();
        $owner = UserFactory::createOne(['organization' => $organization, 'roles' => [UserRole::OWNER->value]]);
        $project = ProjectFactory::createOne([
            'organization' => $organization,
            'address' => '60 boulevard Central',
            'clientName' => 'Construction Boréale',
            'status' => ProjectStatus::ON_HOLD,
        ]);

        $this->client->loginUser($owner);
        $this->client->request('GET', '/chantiers/'.$project->getId());

        $this->assertResponseIsSuccessful();
        $this->assertSelectorCount(5, '.tab-bar__link');
        $this->assertSelectorTextContains('.tab-bar__link[aria-current="page"]', 'Aperçu');
        $this->assertSelectorExists('turbo-frame#project-detail');
        $this->assertSelectorTextContains('.status-badge--on-hold', 'En pause');

        $this->client->request('GET', '/chantiers/'.$project->getId().'?tab=conformite', server: ['HTTP_TURBO_FRAME' => 'project-detail']);

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('turbo-frame#project-detail');
        $this->assertSelectorTextContains('turbo-frame#project-detail', 'Conformité');
        $this->assertSelectorTextContains('.tab-bar__link[aria-current="page"]', 'Conformité');
        $this->assertSelectorNotExists('.app-sidebar');
    }

    public function test_project_forms_use_shared_header_and_allow_on_hold_state(): void
    {
        $organization = OrganizationFactory::createOne();
        $owner = UserFactory::createOne(['organization' => $organization, 'roles' => [UserRole::OWNER->value]]);
        $project = ProjectFactory::createOne(['organization' => $organization, 'status' => ProjectStatus::ACTIVE]);

        $this->client->loginUser($owner);
        $this->client->request('GET', '/chantiers/nouveau');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('.page-header h1', 'Nouveau chantier');
        $this->assertSelectorExists('.project-form');

        $crawler = $this->client->request('GET', '/chantiers/'.$project->getId().'/modifier');
        $form = $crawler->selectButton('Enregistrer le chantier')->form([
            'project[address]' => '70 avenue Révisée',
            'project[clientName]' => 'Client Révisé',
            'project[startDate]' => '2026-09-01',
            'project[endDate]' => '2026-12-01',
            'project[budget]' => '125000',
            'project[status]' => ProjectStatus::ON_HOLD->value,
        ]);
        $this->client->submit($form);

        $this->assertResponseRedirects('/chantiers/'.$project->getId());
        $repository = self::getContainer()->get(ProjectRepository::class);
        self::assertInstanceOf(ProjectRepository::class, $repository);
        $updated = $repository->find($project->getId());
        self::assertInstanceOf(Project::class, $updated);
        self::assertSame(ProjectStatus::ON_HOLD, $updated->getStatus());
    }

    public function test_archived_project_is_read_only_and_displays_archive_state(): void
    {
        $organization = OrganizationFactory::createOne();
        $owner = UserFactory::createOne(['organization' => $organization, 'roles' => [UserRole::OWNER->value]]);
        $project = ProjectFactory::createOne(['organization' => $organization, 'status' => ProjectStatus::ARCHIVED]);

        $this->client->loginUser($owner);
        $this->client->request('GET', '/chantiers/'.$project->getId());

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('.status-badge--archived', 'Archivé');
        $this->assertSelectorTextContains('[data-project-read-only]', 'lecture seule');
        $this->assertSelectorNotExists('a[href$="/modifier"]');
        $this->assertSelectorNotExists('form[action$="/archiver"]');
    }
}
