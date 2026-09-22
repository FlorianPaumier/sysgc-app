<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller;

use App\Commercial\Domain\Model\ChangeOrder;
use App\Identity\Domain\Enum\UserRole;
use App\Project\Domain\Enum\ProjectStatus;
use App\Tests\Factory\OrganizationFactory;
use App\Tests\Factory\ProjectFactory;
use App\Tests\Factory\UserFactory;
use App\Tests\Integration\IntegrationTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Doctrine\ORM\EntityManagerInterface;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
final class ChangeOrderControllerTest extends IntegrationTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    public function testChangeOrderDetailRendersAccessibleChronologicalAuditTrail(): void
    {
        [$project, $owner] = $this->project(ProjectStatus::ACTIVE);
        $order = (new ChangeOrder())->setProject($project)->setTitle('Conduite')->setCostImpact('1200.00');
        $order->recordCreated($owner->getEmail());
        $this->entityManager()->persist($order);
        $this->entityManager()->flush();
        $this->client->loginUser($owner);

        $this->client->request('GET', '/chantiers/'.$project->getId().'/avenants/'.$order->getId());

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('section.audit-trail ol[aria-label="Historique des modifications"]');
        $this->assertSelectorExists('section.audit-trail time[datetime]');
        $this->assertSelectorTextContains('section.audit-trail', $owner->getEmail());
    }

    public function testArchivedProjectShowsChangeOrdersReadOnly(): void
    {
        [$project, $owner] = $this->project(ProjectStatus::ARCHIVED);
        $this->client->loginUser($owner);

        $this->client->request('GET', '/chantiers/'.$project->getId().'/avenants');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('[role="alert"]', 'lecture seule');
        $this->assertSelectorNotExists('a[href*="/avenants/nouveau"]');
    }

    /** @return array{0: \App\Project\Domain\Model\Project, 1: \App\Identity\Domain\Model\User} */
    private function project(ProjectStatus $status): array
    {
        $organization = OrganizationFactory::createOne();
        $owner = UserFactory::createOne(['organization' => $organization, 'roles' => [UserRole::OWNER->value]]);
        $project = ProjectFactory::createOne(['organization' => $organization, 'status' => $status]);

        return [$project, $owner];
    }

    private function entityManager(): EntityManagerInterface
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        return $entityManager;
    }
}
