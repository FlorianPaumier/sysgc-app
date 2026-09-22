<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller;

use App\Identity\Domain\Enum\SubscriptionTier;
use App\Identity\Domain\Enum\UserRole;
use App\Project\Domain\Enum\ProjectStatus;
use App\Safety\Domain\Model\SafetyLog;
use App\Tests\Factory\AddressFactory;
use App\Tests\Factory\OrganizationFactory;
use App\Tests\Factory\ProjectFactory;
use App\Tests\Factory\UserFactory;
use App\Tests\Integration\IntegrationTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
final class DashboardControllerTest extends IntegrationTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    public function test_dashboard_requires_authentication(): void
    {
        $this->client->request('GET', '/dashboard');

        $this->assertResponseRedirects('/connexion');
    }

    public function test_dashboard_renders_authenticated_shell(): void
    {
        $org = OrganizationFactory::createOne();
        $user = UserFactory::createOne(['organization' => $org]);
        $this->client->loginUser($user);

        $this->client->request('GET', '/dashboard');
        $this->assertResponseIsSuccessful();

        // App shell elements
        $this->assertSelectorExists('aside.app-sidebar');
        $this->assertSelectorExists('nav.app-sidebar__nav');
        $this->assertSelectorExists('.app-header');
        $this->assertSelectorExists('.breadcrumb');

        // User menu
        $this->assertSelectorExists('.app-sidebar__user');
        $this->assertSelectorExists('a.app-sidebar__logout[href="/deconnexion"]');

        // Dashboard content
        $this->assertSelectorExists('.stat-card');

        // Public chrome must be hidden
        $this->assertSelectorNotExists('footer#contact');
    }

    public function test_dashboard_only_loads_authenticated_frontend(): void
    {
        $org = OrganizationFactory::createOne();
        $user = UserFactory::createOne(['organization' => $org]);
        $this->client->loginUser($user);

        $crawler = $this->client->request('GET', '/dashboard');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('body[data-frontend="authenticated"]');
        $this->assertSelectorExists('[data-app-chrome]');
        $this->assertSelectorNotExists('[data-public-chrome]');

        $this->assertSelectorExists('link[rel="stylesheet"][href*="styles/authenticated-"]');
        $this->assertSelectorNotExists('link[rel="stylesheet"][href*="styles/public-"]');
        $this->assertSelectorTextContains('script[type="module"]', "import 'authenticated'");
        $this->assertSelectorTextNotContains('script[type="module"]', "import 'public'");
    }

    public function test_dashboard_sidebar_has_grouped_sections(): void
    {
        $org = OrganizationFactory::createOne();
        $user = UserFactory::createOne(['organization' => $org]);
        $this->client->loginUser($user);

        $this->client->request('GET', '/dashboard');
        $this->assertResponseIsSuccessful();

        $this->assertSelectorExists('.app-sidebar__group');
        $this->assertSelectorTextContains('.app-sidebar', 'Principal');
        $this->assertSelectorTextContains('.app-sidebar', 'Chantiers');
        $this->assertSelectorTextContains('.app-sidebar', 'Conformité');
        $this->assertSelectorTextContains('.app-sidebar', 'Administration');
    }

    public function test_dashboard_shows_user_identity(): void
    {
        $org = OrganizationFactory::createOne();
        $user = UserFactory::createOne(['organization' => $org]);
        $this->client->loginUser($user);

        $this->client->request('GET', '/dashboard');
        $this->assertResponseIsSuccessful();

        $this->assertSelectorTextContains('.app-sidebar__user', $user->getEmail());
    }

    public function test_dashboard_shows_welcome_with_display_name(): void
    {
        $org = OrganizationFactory::createOne();
        $user = UserFactory::createOne(['organization' => $org]);
        $this->client->loginUser($user);

        $this->client->request('GET', '/dashboard');
        $this->assertResponseIsSuccessful();

        $this->assertSelectorTextContains('.page-header', $user->getDisplayName());
    }

    public function test_dashboard_defaults_to_active_and_on_hold_projects_and_excludes_archives(): void
    {
        $org = OrganizationFactory::createOne();
        $user = UserFactory::createOne(['organization' => $org, 'roles' => [UserRole::OWNER->value]]);
        ProjectFactory::createOne(['organization' => $org, 'name' => 'Actif prioritaire', 'status' => ProjectStatus::ACTIVE]);
        ProjectFactory::createOne(['organization' => $org, 'name' => 'En pause', 'status' => ProjectStatus::ON_HOLD]);
        ProjectFactory::createOne(['organization' => $org, 'name' => 'Archive historique', 'status' => ProjectStatus::ARCHIVED]);
        $this->client->loginUser($user);

        $this->client->request('GET', '/dashboard');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorCount(2, '[data-dashboard-project]');
        $this->assertSelectorTextContains('[data-dashboard-projects]', 'Actif prioritaire');
        $this->assertSelectorTextContains('[data-dashboard-projects]', 'En pause');
        $this->assertSelectorTextNotContains('[data-dashboard-projects]', 'Archive historique');
        $this->assertSelectorTextContains('[data-dashboard-project-status="active"]', 'Actif');
        $this->assertSelectorTextContains('[data-dashboard-project-status="on_hold"]', 'En pause');
        $this->assertSelectorExists('[data-dashboard-filter="current"][aria-current="page"]');
    }

    public function test_dashboard_archive_filter_shows_completed_projects_only(): void
    {
        $org = OrganizationFactory::createOne();
        $user = UserFactory::createOne(['organization' => $org, 'roles' => [UserRole::OWNER->value]]);
        ProjectFactory::createOne(['organization' => $org, 'name' => 'Actif courant', 'status' => ProjectStatus::ACTIVE]);
        ProjectFactory::createOne(['organization' => $org, 'name' => 'Archive historique', 'status' => ProjectStatus::ARCHIVED]);
        $this->client->loginUser($user);

        $this->client->request('GET', '/dashboard?status=archived');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorCount(1, '[data-dashboard-project]');
        $this->assertSelectorTextContains('[data-dashboard-projects]', 'Archive historique');
        $this->assertSelectorTextNotContains('[data-dashboard-projects]', 'Actif courant');
        $this->assertSelectorTextContains('[data-dashboard-project-status="archived"]', 'Archivé');
        $this->assertSelectorExists('[data-dashboard-filter="archived"][aria-current="page"]');
        $this->assertSelectorNotExists('[data-dashboard-alert]');
    }

    public function test_dashboard_sorts_current_projects_by_risk_before_recency(): void
    {
        $org = OrganizationFactory::createOne();
        $user = UserFactory::createOne(['organization' => $org, 'roles' => [UserRole::OWNER->value]]);
        ProjectFactory::createOne([
            'organization' => $org,
            'name' => 'Risque élevé',
            'status' => ProjectStatus::ACTIVE,
            'startDate' => new \DateTime('2024-01-01'),
        ]);
        $safer = ProjectFactory::createOne([
            'organization' => $org,
            'name' => 'Risque modéré',
            'status' => ProjectStatus::ON_HOLD,
            'startDate' => new \DateTime('2026-01-01'),
        ]);
        $subcontractor = new \App\Compliance\Domain\Model\Subcontractor();
        $subcontractor->setName('Licence valide')->setVerified(true);
        $safer->addSubcontractor($subcontractor);
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $entityManager->persist($subcontractor);
        $entityManager->flush();
        $this->client->loginUser($user);

        $crawler = $this->client->request('GET', '/dashboard');

        $names = $crawler->filter('[data-dashboard-project] h3')->each(static fn ($node): string => trim($node->text()));
        self::assertSame(['Risque élevé', 'Risque modéré'], $names);
    }

    public function test_dashboard_summary_and_alerts_only_count_current_portfolio(): void
    {
        $org = OrganizationFactory::createOne();
        $user = UserFactory::createOne(['organization' => $org, 'roles' => [UserRole::OWNER->value]]);
        ProjectFactory::createOne(['organization' => $org, 'name' => 'Actif', 'status' => ProjectStatus::ACTIVE]);
        ProjectFactory::createOne(['organization' => $org, 'name' => 'Pause', 'status' => ProjectStatus::ON_HOLD]);
        ProjectFactory::createOne(['organization' => $org, 'name' => 'Archive sans rapport', 'status' => ProjectStatus::ARCHIVED]);
        $this->client->loginUser($user);

        $this->client->request('GET', '/dashboard');

        $this->assertSelectorTextContains('[data-stat="active"]', '1');
        $this->assertSelectorTextContains('[data-stat="on-hold"]', '1');
        $this->assertSelectorTextContains('[data-dashboard-alerts]', 'Actif');
        $this->assertSelectorTextContains('[data-dashboard-alerts]', 'Pause');
        $this->assertSelectorTextNotContains('[data-dashboard-alerts]', 'Archive sans rapport');
        $this->assertSelectorExists('[data-dashboard-alert][href*="/securite"]');
    }

    public function test_dashboard_exposes_role_specific_actions_and_redirects_foremen(): void
    {
        $org = OrganizationFactory::createOne();
        $owner = UserFactory::createOne(['organization' => $org, 'roles' => [UserRole::OWNER->value]]);
        $this->client->loginUser($owner);
        $this->client->request('GET', '/dashboard');
        $this->assertSelectorExists('[data-dashboard-role-action="owner"][href="/organisation/membres"]');

        $admin = UserFactory::createOne(['organization' => $org, 'roles' => [UserRole::ADMIN->value]]);
        $this->client->loginUser($admin);
        $this->client->request('GET', '/dashboard');
        $this->assertSelectorExists('[data-dashboard-role-action="admin"][href="/declarations"]');
        $this->assertSelectorNotExists('[data-dashboard-role-action="owner"]');

        $foreman = UserFactory::createOne(['organization' => $org, 'roles' => [UserRole::FOREMAN->value]]);
        $this->client->loginUser($foreman);
        $this->client->request('GET', '/dashboard');
        $this->assertResponseRedirects('/dashboard/terrain');
    }

    public function test_owner_shell_shows_office_destinations_and_subscription(): void
    {
        $org = OrganizationFactory::createOne([
            'address' => AddressFactory::new(),
            'subscriptionTier' => SubscriptionTier::Pro,
        ]);
        $user = UserFactory::createOne([
            'organization' => $org,
            'roles' => [UserRole::OWNER->value],
        ]);
        $this->client->loginUser($user);

        $this->client->request('GET', '/dashboard');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('aside.app-sidebar[data-shell-role="owner"]');
        $this->assertSelectorTextContains('.app-sidebar__role', 'Propriétaire');
        $this->assertSelectorExists('.app-sidebar__link[href="/dashboard"][aria-current="page"]');
        $this->assertSelectorExists('.app-sidebar__link[href="/chantiers"]');
        $this->assertSelectorExists('.app-sidebar__link[href="/declarations"]');
        $this->assertSelectorExists('.app-sidebar__link[href="/travailleurs"]');
        $this->assertSelectorExists('.app-sidebar__link[href="/organisation"]');
        $this->assertSelectorExists('.app-sidebar__subscription[href="/forfaits"]');
        $this->assertSelectorTextContains('.app-sidebar__subscription', 'Pro');
        $this->assertSelectorCount(4, '.bottom-nav__btn');
        $this->assertSelectorTextContains('.bottom-nav', 'Conformité');
    }

    public function test_admin_shell_omits_owner_billing_destination(): void
    {
        $org = OrganizationFactory::createOne([
            'address' => AddressFactory::new(),
            'subscriptionTier' => SubscriptionTier::Starter,
        ]);
        $user = UserFactory::createOne([
            'organization' => $org,
            'roles' => [UserRole::ADMIN->value],
        ]);
        $this->client->loginUser($user);

        $this->client->request('GET', '/dashboard');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('aside.app-sidebar[data-shell-role="admin"]');
        $this->assertSelectorTextContains('.app-sidebar__role', 'Administrateur');
        $this->assertSelectorNotExists('.app-sidebar a[href="/forfaits"]');
        $this->assertSelectorTextContains('.app-sidebar__subscription', 'Starter');
        $this->assertSelectorExists('.app-sidebar__link[href="/organisation"]');
    }

    public function test_foreman_shell_only_shows_field_destinations(): void
    {
        $org = OrganizationFactory::createOne(['address' => AddressFactory::new()]);
        $user = UserFactory::createOne([
            'organization' => $org,
            'roles' => [UserRole::FOREMAN->value],
        ]);
        $this->client->loginUser($user);

        $this->client->request('GET', '/dashboard/terrain');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('aside.app-sidebar[data-shell-role="foreman"]');
        $this->assertSelectorTextContains('.app-sidebar__role', 'Contremaître');
        $this->assertSelectorExists('.app-sidebar__link[href="/dashboard/terrain"][aria-current="page"]');
        $this->assertSelectorExists('.app-sidebar__link[href="/chantiers"]');
        $this->assertSelectorNotExists('.app-sidebar a[href="/declarations"]');
        $this->assertSelectorNotExists('.app-sidebar a[href="/travailleurs"]');
        $this->assertSelectorNotExists('.app-sidebar a[href="/organisation"]');
        $this->assertSelectorNotExists('.app-sidebar a[href="/forfaits"]');
        $this->assertSelectorExists('.breadcrumb a[href="/dashboard/terrain"]');
        $this->assertSelectorCount(3, '.bottom-nav__btn');
        $this->assertSelectorTextContains('.bottom-nav', 'Sécurité');
        $this->assertSelectorTextContains('.bottom-nav', 'Mes chantiers');
        $this->assertSelectorTextContains('.bottom-nav', 'Compte');
    }

    public function test_terrain_dashboard_prioritizes_assigned_safety_work_and_sync_state(): void
    {
        $org = OrganizationFactory::createOne(['address' => AddressFactory::new()]);
        $foreman = UserFactory::createOne([
            'organization' => $org,
            'roles' => [UserRole::FOREMAN->value],
        ]);
        $assignedIncomplete = ProjectFactory::createOne([
            'organization' => $org,
            'name' => 'Chantier Nord',
            'address' => '10 rue du Nord',
            'status' => ProjectStatus::ACTIVE,
        ]);
        $assignedMissing = ProjectFactory::createOne([
            'organization' => $org,
            'name' => 'Chantier Sud',
            'address' => '20 rue du Sud',
            'status' => ProjectStatus::ACTIVE,
        ]);
        ProjectFactory::createOne([
            'organization' => $org,
            'name' => 'Chantier non assigné',
            'address' => '30 rue de l’Est',
            'status' => ProjectStatus::ACTIVE,
        ]);
        $foreman->assignProject($assignedIncomplete);
        $foreman->assignProject($assignedMissing);

        $today = new \DateTimeImmutable('today');
        $incompleteToday = SafetyLog::record(
            project: $assignedIncomplete,
            date: $today,
            weather: 'Ensoleillé',
            checklistItems: [['label' => 'Casque porté', 'checked' => false, 'comment' => '']],
            hazardPhotos: [],
            workerSignatures: [],
            incidentReport: null,
        );
        $completedYesterday = SafetyLog::record(
            project: $assignedIncomplete,
            date: $today->modify('-1 day'),
            weather: 'Nuageux',
            checklistItems: [['label' => 'Casque porté', 'checked' => true, 'comment' => '']],
            hazardPhotos: [],
            workerSignatures: [],
            incidentReport: null,
        );
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $entityManager->persist($incompleteToday);
        $entityManager->persist($completedYesterday);
        $entityManager->flush();

        $this->client->loginUser($foreman);
        $this->client->request('GET', '/dashboard/terrain');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorCount(2, '[data-terrain-project]');
        $this->assertSelectorTextContains('[data-terrain-projects]', 'Chantier Nord');
        $this->assertSelectorTextContains('[data-terrain-projects]', 'Chantier Sud');
        $this->assertSelectorTextNotContains('[data-terrain-projects]', 'Chantier non assigné');
        $this->assertSelectorTextContains('[data-terrain-today-task]', 'Chantier Nord');
        $this->assertSelectorTextContains('[data-terrain-today-task]', 'À compléter');
        $this->assertSelectorTextContains('[data-terrain-missing]', 'Chantier Sud');
        $this->assertSelectorTextContains('[data-terrain-missing]', 'Rapport non rempli');
        $this->assertSelectorTextContains('[data-terrain-recent]', 'Chantier Nord');
        $this->assertSelectorTextContains('[data-terrain-recent]', 'Rapport complété');
        $this->assertSelectorExists('[data-offline-status][data-sync-state="online"]');
        $this->assertSelectorTextContains('[data-offline-status]', 'Synchronisation');
    }

    public function test_shell_marks_domain_navigation_active_on_nested_routes(): void
    {
        $org = OrganizationFactory::createOne(['address' => AddressFactory::new()]);
        $user = UserFactory::createOne([
            'organization' => $org,
            'roles' => [UserRole::OWNER->value],
        ]);
        $this->client->loginUser($user);

        $this->client->request('GET', '/declarations');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('.app-sidebar__link[href="/declarations"][aria-current="page"]');
        $this->assertSelectorExists('.bottom-nav__btn[href="/declarations"][aria-current="page"]');
        $this->assertSelectorTextContains('.breadcrumb__current', 'Déclarations CCQ');
    }
}
