<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260916150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds organization checklist defaults, deletion recovery deadline, and member activation state.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE organization ADD checklist_defaults JSON NOT NULL DEFAULT '{}'");
        $this->addSql('ALTER TABLE organization ADD deletion_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD active BOOLEAN NOT NULL DEFAULT TRUE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" DROP active');
        $this->addSql('ALTER TABLE organization DROP deletion_at');
        $this->addSql('ALTER TABLE organization DROP checklist_defaults');
    }
}
