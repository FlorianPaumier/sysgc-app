<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller;

use App\Identity\Domain\Enum\UserRole;
use App\Safety\Domain\Model\SafetyLog;
use App\Tests\Factory\OrganizationFactory;
use App\Tests\Factory\ProjectFactory;
use App\Tests\Factory\UserFactory;
use App\Tests\Integration\IntegrationTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
final class SafetyControllerTest extends IntegrationTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    public function test_calendar_navigates_to_a_requested_month_and_marks_incidents(): void
    {
        [$project] = $this->authenticatedProject();
        $log = SafetyLog::record($project, new \DateTimeImmutable('2026-06-09'), 'Pluie', [
            ['id' => 'baseline-1', 'label' => 'Casques', 'checked' => true, 'comment' => '', 'protected' => true],
        ], [], [], 'Zone glissante.');
        $log->complete();
        $this->entityManager()->persist($log);
        $this->entityManager()->flush();

        $this->client->request('GET', '/chantiers/'.$project->getId().'/securite?month=6&year=2026');

        $this->assertResponseIsSuccessful();
        $content = $this->client->getResponse()->getContent();
        self::assertIsString($content);
        self::assertStringContainsString('juin 2026', $content);
        $this->assertSelectorExists('[role="gridcell"][aria-label*="incident déclaré"]');
        $this->assertSelectorExists('a[aria-label*="Mois précédent"]');
        $this->assertSelectorExists('a[aria-label*="Mois suivant"]');
    }

    public function test_completed_daily_report_is_read_only(): void
    {
        [$project] = $this->authenticatedProject();
        $log = SafetyLog::record($project, new \DateTimeImmutable('2026-06-09'), 'Ensoleillé', [], [], [], null);
        $log->complete();
        $this->entityManager()->persist($log);
        $this->entityManager()->flush();

        $this->client->request('GET', '/chantiers/'.$project->getId().'/securite/2026-06-09');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('.safety-checklist', 'lecture seule');
        $this->assertSelectorNotExists('[data-live-action-param="complete"]');
        $this->assertSelectorNotExists('input[type="file"]');
    }

    /** @return array{0: \App\Project\Domain\Model\Project, 1: \App\Identity\Domain\Model\User} */
    private function authenticatedProject(): array
    {
        $organization = OrganizationFactory::createOne();
        $owner = UserFactory::createOne(['organization' => $organization, 'roles' => [UserRole::OWNER->value]]);
        $project = ProjectFactory::createOne(['organization' => $organization]);
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
