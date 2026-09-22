<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Identity\Domain\Model\User;
use App\Identity\Domain\Model\Organization;
use App\Identity\Domain\Enum\UserRole;
use App\Project\Domain\Model\Project;
use App\Project\Infrastructure\Security\ProjectVoter;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @covers \App\Project\Infrastructure\Security\ProjectVoter
 */
final class ProjectVoterTest extends TestCase
{
    private ProjectVoter $voter;
    private TokenInterface&MockObject $token;
    private User $user;

    protected function setUp(): void
    {
        $this->voter = new ProjectVoter();
        $this->token = $this->createMock(TokenInterface::class);

        $org = new Organization();
        $org->setName('Test Org');
        $org->setRbqLicense('TEST-123');
        $org->setAddress(new \App\Identity\Domain\Model\Address());

        $this->user = new User();
        $this->user->setEmail('user@test.com');
        $this->user->setOrganization($org);
    }

    public function testVoteAbstainsOnUnsupportedAttribute(): void
    {
        $subject = new \stdClass();

        $this->token->method('getUser')->willReturn($this->user);

        $result = $this->voter->vote($this->token, $subject, ['UNSUPPORTED_ATTRIBUTE']);

        $this->assertSame(Voter::ACCESS_ABSTAIN, $result);
    }

    public function testDeniesAccessWhenProjectOrganizationDiffersFromUserOrganization(): void
    {
        $userOrg = new Organization();
        $userOrg->setName('User Org');
        $userOrg->setRbqLicense('USER-123');
        $userOrg->setAddress(new \App\Identity\Domain\Model\Address());
        $this->user->setOrganization($userOrg);

        $projectOrg = new Organization();
        $projectOrg->setName('Project Org');
        $projectOrg->setRbqLicense('PROJ-456');
        $projectOrg->setAddress(new \App\Identity\Domain\Model\Address());

        // Mock project with different org
        $project = $this->createStub(\App\Project\Domain\Model\Project::class);
        $project->method('getOrganization')->willReturn($projectOrg);

        $this->token->method('getUser')->willReturn($this->user);

        $result = $this->voter->vote($this->token, $project, ['view']);

        $this->assertSame(Voter::ACCESS_DENIED, $result);
    }

    public function testGrantsAccessWhenProjectOrganizationMatchesUserOrganization(): void
    {
        $sharedOrg = new Organization();
        $sharedOrg->setName('Shared Org');
        $sharedOrg->setRbqLicense('SHARED-789');
        $sharedOrg->setAddress(new \App\Identity\Domain\Model\Address());

        $this->user->setOrganization($sharedOrg);

        // Mock project with same org
        $project = $this->createStub(\App\Project\Domain\Model\Project::class);
        $project->method('getOrganization')->willReturn($sharedOrg);

        $this->token->method('getUser')->willReturn($this->user);

        $result = $this->voter->vote($this->token, $project, ['view']);

        $this->assertSame(Voter::ACCESS_GRANTED, $result);
    }

    public function testDeniesEditAccessForCrossOrgProject(): void
    {
        $userOrg = new Organization();
        $userOrg->setName('User Org');
        $userOrg->setRbqLicense('USER-123');
        $userOrg->setAddress(new \App\Identity\Domain\Model\Address());
        $this->user->setOrganization($userOrg);

        $projectOrg = new Organization();
        $projectOrg->setName('Project Org');
        $projectOrg->setRbqLicense('PROJ-456');
        $projectOrg->setAddress(new \App\Identity\Domain\Model\Address());

        $project = $this->createStub(\App\Project\Domain\Model\Project::class);
        $project->method('getOrganization')->willReturn($projectOrg);

        $this->token->method('getUser')->willReturn($this->user);

        $result = $this->voter->vote($this->token, $project, ['edit']);

        $this->assertSame(Voter::ACCESS_DENIED, $result);
    }

    public function testForemanCanViewOnlyAnAssignedProject(): void
    {
        $organization = $this->user->getOrganization();
        self::assertNotNull($organization);
        $project = new Project($organization, '123 Rue Test', 'Client');
        $this->user->setRoles([UserRole::FOREMAN->value]);
        $this->user->assignProject($project);
        $this->token->method('getUser')->willReturn($this->user);

        self::assertSame(Voter::ACCESS_GRANTED, $this->voter->vote($this->token, $project, ['view']));
        self::assertSame(Voter::ACCESS_DENIED, $this->voter->vote($this->token, $project, ['edit']));
    }

    public function testForemanCannotViewAnUnassignedProject(): void
    {
        $organization = $this->user->getOrganization();
        self::assertNotNull($organization);
        $project = new Project($organization, '123 Rue Test', 'Client');
        $this->user->setRoles([UserRole::FOREMAN->value]);
        $this->token->method('getUser')->willReturn($this->user);

        self::assertSame(Voter::ACCESS_DENIED, $this->voter->vote($this->token, $project, ['view']));
    }
}
