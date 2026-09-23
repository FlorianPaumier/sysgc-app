<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Command;

use App\Identity\Domain\Enum\UserRole;
use App\Identity\Domain\Model\Address;
use App\Identity\Domain\Model\Organization;
use App\Identity\Domain\Model\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:e2e:seed', description: 'Seeds the deterministic account used by Playwright.')]
final class SeedE2eCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $environment = getenv('APP_ENV');
        if (!\is_string($environment) || '' === $environment) {
            $environment = 'dev';
        }
        if (! \in_array($environment, ['dev', 'test'], true)) {
            $output->writeln('<error>E2E seeding is only available in dev or test.</error>');

            return Command::FAILURE;
        }

        $repository = $this->entityManager->getRepository(User::class);
        $email = 'e2e-owner@sysgc.test';
        $user = $repository->findOneBy(['email' => $email]);

        if ($user instanceof User) {
            $output->writeln(sprintf('E2E owner already exists: %s', $email));

            return Command::SUCCESS;
        }

        $organization = new Organization()
            ->setName('E2E Construction')
            ->setRbqLicense('RBQ-9999-9999-99')
            ->setAddress(new Address()
                ->setStreet('100 Rue de Test')
                ->setPostalCode('H1H 1H1')
                ->setCity('Montréal')
                ->setState('QC'));

        $user = new User()
            ->setEmail($email)
            ->setRoles([UserRole::OWNER->value])
            ->setIsVerified(true)
            ->setOrganization($organization);

        $this->entityManager->persist($organization);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $output->writeln(sprintf('Seeded E2E owner: %s', $email));

        return Command::SUCCESS;
    }
}
