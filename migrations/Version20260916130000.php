<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260916130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds version and client-event idempotency fields to safety logs.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE safety_log ADD version INT NOT NULL DEFAULT 1');
        $this->addSql('ALTER TABLE safety_log ADD applied_client_events JSONB NOT NULL DEFAULT \'[]\'::jsonb');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE safety_log DROP applied_client_events');
        $this->addSql('ALTER TABLE safety_log DROP version');
    }
}
