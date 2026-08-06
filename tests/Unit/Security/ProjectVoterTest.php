<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Entity\User;
use App\Entity\Organization;
use App\Security\ProjectVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @covers \App\Security\ProjectVoter
 */
final class ProjectVoterTest extends TestCase
{
    private ProjectVoter $voter;
    private TokenInterface $token;
    private User $user;

    protected function setUp(): void
    {
        $this->voter = new ProjectVoter();
        $this->token = $this->createStub(TokenInterface::class);

        $org = new Organization();
        $org->setName('Test Org');
        $org->setRbqLicense('TEST-123');
        $org->setAddress(new \App\Entity\Address());

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
        $userOrg->setAddress(new \App\Entity\Address());
        $this->user->setOrganization($userOrg);

        $projectOrg = new Organization();
        $projectOrg->setName('Project Org');
        $projectOrg->setRbqLicense('PROJ-456');
        $projectOrg->setAddress(new \App\Entity\Address());

        // Mock project with different org
        $project = $this->createStub(\App\Entity\Project::class);
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
        $sharedOrg->setAddress(new \App\Entity\Address());

        $this->user->setOrganization($sharedOrg);

        // Mock project with same org
        $project = $this->createStub(\App\Entity\Project::class);
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
        $userOrg->setAddress(new \App\Entity\Address());
        $this->user->setOrganization($userOrg);

        $projectOrg = new Organization();
        $projectOrg->setName('Project Org');
        $projectOrg->setRbqLicense('PROJ-456');
        $projectOrg->setAddress(new \App\Entity\Address());

        $project = $this->createStub(\App\Entity\Project::class);
        $project->method('getOrganization')->willReturn($projectOrg);

        $this->token->method('getUser')->willReturn($this->user);

        $result = $this->voter->vote($this->token, $project, ['edit']);

        $this->assertSame(Voter::ACCESS_DENIED, $result);
    }
}
