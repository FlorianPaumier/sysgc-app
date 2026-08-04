<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260803152056 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename organization.rbq_licence to rbq_license to match spec naming.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE organization RENAME COLUMN rbq_licence TO rbq_license');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE organization RENAME COLUMN rbq_license TO rbq_licence');
    }
}
