<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Command;

use App\Identity\Application\OrganizationRetentionService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:organizations:purge', description: 'Purge les organisations dont la période de récupération est échue')]
final class PurgeOrganizationsCommand extends Command
{
    public function __construct(private readonly OrganizationRetentionService $retentionService)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $count = $this->retentionService->purgeDueOrganizations(new \DateTimeImmutable());
        $output->writeln(sprintf('%d organisation(s) purgée(s).', $count));

        return Command::SUCCESS;
    }
}
