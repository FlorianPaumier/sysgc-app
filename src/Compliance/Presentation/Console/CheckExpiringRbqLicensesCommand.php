<?php

declare(strict_types=1);

namespace App\Compliance\Presentation\Console;

use App\Compliance\Infrastructure\Doctrine\SubcontractorRepository;
use App\Compliance\Message\CheckSingleLicenseMessage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(name: 'rbq:check-expiring', description: 'Queues RBQ licence checks due within 30 days.')]
final class CheckExpiringRbqLicensesCommand extends Command
{
    public function __construct(private readonly SubcontractorRepository $subcontractors, private readonly MessageBusInterface $bus)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $subcontractors = $this->subcontractors->findExpiringOnOrBefore(new \DateTimeImmutable('+30 days'));
        foreach ($subcontractors as $subcontractor) {
            $this->bus->dispatch(new CheckSingleLicenseMessage((string) $subcontractor->getId()));
        }
        $output->writeln(sprintf('%d vérification(s) RBQ mise(s) en file.', count($subcontractors)));

        return Command::SUCCESS;
    }
}
