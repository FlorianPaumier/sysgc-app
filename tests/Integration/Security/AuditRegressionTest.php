<?php

declare(strict_types=1);

namespace App\Tests\Integration\Security;

use App\Compliance\Domain\Model\CCQDeclaration;
use App\Compliance\Domain\Model\Subcontractor;
use App\Identity\Domain\Enum\UserRole;
use App\Tests\Factory\OrganizationFactory;
use App\Tests\Factory\ProjectFactory;
use App\Tests\Factory\UserFactory;
use App\Tests\Integration\IntegrationTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
final class AuditRegressionTest extends IntegrationTestCase
{
    use InteractsWithLiveComponents;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    public function test_sensitive_actions_require_a_csrf_token(): void
    {
        $org = OrganizationFactory::createOne();
        $owner = UserFactory::createOne(['organization' => $org, 'roles' => [UserRole::OWNER->value]]);
        $admin = UserFactory::createOne(['organization' => $org, 'roles' => [UserRole::ADMIN->value]]);
        $this->client->loginUser($owner);
        foreach (['/organisation/membres/'.$admin->getId().'/desactiver', '/organisation/membres/'.$admin->getId().'/transferer-propriete', '/organisation/suppression', '/organisation/suppression/annuler'] as $url) {
            $this->client->request('POST', $url);
            self::assertResponseStatusCodeSame(403);
        }
    }

    public function test_deactivation_ends_a_previously_authenticated_session(): void
    {
        $member = UserFactory::createOne(['roles' => [UserRole::ADMIN->value]]);
        $this->client->loginUser($member);
        $this->client->request('GET', '/dashboard');
        self::assertResponseIsSuccessful();
        $em = $this->entityManager();
        $reloaded = $em->find(\App\Identity\Domain\Model\User::class, $member->getId());
        self::assertNotNull($reloaded);
        $reloaded->deactivate();
        $em->flush();
        $this->client->request('GET', '/dashboard');
        self::assertResponseRedirects('/connexion');
    }

    public function test_foreman_history_hides_unassigned_projects_and_outcome_is_forbidden(): void
    {
        $org = OrganizationFactory::createOne();
        $member = UserFactory::createOne(['organization' => $org, 'roles' => [UserRole::FOREMAN->value]]);
        $project = ProjectFactory::createOne(['organization' => $org, 'address' => 'Secret Payroll']);
        $declaration = new CCQDeclaration()->setProject($project)->setOrganization($org)->setPeriodMonth(6)->setPeriodYear(2026)->setStatus('draft');
        $em = $this->entityManager();
        $em->persist($declaration);
        $em->flush();
        $this->client->loginUser($member);
        $this->client->request('GET', '/declarations');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextNotContains('main', 'Secret Payroll');
        foreach (['', '/pdf'] as $suffix) {
            $this->client->request('GET', '/declarations/'.$declaration->getId().$suffix);
            self::assertResponseStatusCodeSame(403);
        }
        $this->client->request('POST', '/declarations/'.$declaration->getId().'/outcome');
        self::assertResponseStatusCodeSame(403);
    }

    public function test_read_only_organization_cannot_create_workers_or_projects(): void
    {
        $org = OrganizationFactory::createOne();
        $owner = UserFactory::createOne(['organization' => $org, 'roles' => [UserRole::OWNER->value]]);
        $org->scheduleDeletion();
        $this->entityManager()->flush();
        $this->client->loginUser($owner);
        foreach (['/travailleurs/nouveau', '/chantiers/nouveau'] as $url) {
            $this->client->request('GET', $url);
            self::assertResponseStatusCodeSame(403);
        }
    }

    public function test_single_rbq_check_only_enqueues_existing_message(): void
    {
        $org = OrganizationFactory::createOne();
        $owner = UserFactory::createOne(['organization' => $org, 'roles' => [UserRole::OWNER->value]]);
        $project = ProjectFactory::createOne(['organization' => $org]);
        $sub = new Subcontractor()->setName('Roof')->setRbqLicenseNumber('RBQ-1234-5678-90');
        $project->addSubcontractor($sub);
        $em = $this->entityManager();
        $em->persist($sub);
        $em->flush();
        $transport = new InMemoryTransport();
        self::getContainer()->set('messenger.transport.async', $transport);
        $this->client->disableReboot();
        $this->client->loginUser($owner);
        $crawler = $this->client->request('GET', '/chantiers/'.$project->getId().'/rbq');
        $form = $crawler->filter('form[action$="/'.$sub->getId().'/verifier"]')->form();
        $this->client->submit($form);
        self::assertResponseRedirects('/chantiers/'.$project->getId().'/rbq');
        self::assertCount(1, $transport->getSent());
        self::assertInstanceOf(\App\Compliance\Message\CheckSingleLicenseMessage::class, $transport->getSent()[0]->getMessage());
    }

    public function test_export_is_idempotent_and_live_filing_confirmation_survives(): void
    {
        $org = OrganizationFactory::createOne();
        $owner = UserFactory::createOne(['organization' => $org, 'roles' => [UserRole::OWNER->value]]);
        $project = ProjectFactory::createOne(['organization' => $org]);
        $this->client->loginUser($owner);
        $this->client->disableReboot();
        $live = $this->createLiveComponent('CcqGrid', ['project' => $project, 'month' => 6, 'year' => 2026], $this->client);
        $live->set('hoursData', [['workerId' => 7, 'workerName' => 'A', 'trade' => 'electrician', 'region' => 'montreal', 'dailyHours' => ['8' => '8']]]);
        $live->call('exportPdf');
        $component = $live->component();
        self::assertInstanceOf(\App\Compliance\Presentation\Component\CcqGrid::class, $component);
        $id = $component->declarationId;
        self::assertNotNull($id);
        $live->call('exportPdf');
        $component = $live->component();
        self::assertInstanceOf(\App\Compliance\Presentation\Component\CcqGrid::class, $component);
        self::assertSame($id, $component->declarationId);
        self::assertSame(1, $this->entityManager()->getRepository(CCQDeclaration::class)->count([]));
        $live->call('refreshExportStatus')->call('prepareFiling')->call('fileDeclaration');
        $component = $live->component();
        self::assertInstanceOf(\App\Compliance\Presentation\Component\CcqGrid::class, $component);
        self::assertSame('filed', $component->status);
    }
    public function test_hours_in_a_week_starting_last_month_are_not_lost(): void
    {
        $org = OrganizationFactory::createOne();
        $project = ProjectFactory::createOne(['organization' => $org]);
        $worker = new \App\Workforce\Domain\Model\Worker()->setName('Boundary')->setOrganization($org)->setCcqTrade(\App\Workforce\Domain\Enum\CcqTrade::Electrician)->setCcqRegion(\App\Workforce\Domain\Enum\CcqRegion::Montreal);
        $entry = new \App\Workforce\Domain\Model\HoursEntry()->setProject($project)->setWorker($worker)->setWeekStart(new \DateTime('2026-09-28'))->setHours(['thursday' => 8]);
        $em = $this->entityManager();
        $em->persist($worker);
        $em->persist($entry);
        $em->flush();
        $summarizer = self::getContainer()->get(\App\Compliance\Application\HoursSummarizer::class);
        self::assertInstanceOf(\App\Compliance\Application\HoursSummarizer::class, $summarizer);
        $rows = $summarizer->summarize($project, 10, 2026);
        self::assertCount(1, $rows);
        self::assertSame([1 => 8.0], $rows[0]['dailyHours']);
    }

    public function test_foreman_cannot_read_an_organization_snapshot_with_an_unassigned_project(): void
    {
        $org = OrganizationFactory::createOne();
        $foreman = UserFactory::createOne(['organization' => $org, 'roles' => [UserRole::FOREMAN->value]]);
        $assigned = ProjectFactory::createOne(['organization' => $org]);
        $other = ProjectFactory::createOne(['organization' => $org]);
        $foreman->assignProject($assigned);
        $declaration = new CCQDeclaration()->setProject($assigned)->setOrganization($org)->setPeriodMonth(6)->setPeriodYear(2026)->setStatus('draft')->setDeclaration([['projectId' => (string) $other->getId(), 'workerName' => 'Private payroll']]);
        $em = $this->entityManager();
        $em->persist($declaration);
        $em->flush();
        $this->client->loginUser($foreman);
        $this->client->request('GET', '/declarations/'.$declaration->getId());
        self::assertResponseStatusCodeSame(403);
    }

    private function entityManager(): EntityManagerInterface
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        return $em;
    }
}
