<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260910100002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store RBQ category, expiry date, and manual-verification status for subcontractors.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE subcontractor ADD category VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE subcontractor ADD expiry_date DATE DEFAULT NULL');
        $this->addSql('ALTER TABLE subcontractor ADD is_manually_verified BOOLEAN DEFAULT FALSE NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE subcontractor DROP category');
        $this->addSql('ALTER TABLE subcontractor DROP expiry_date');
        $this->addSql('ALTER TABLE subcontractor DROP is_manually_verified');
    }
}
