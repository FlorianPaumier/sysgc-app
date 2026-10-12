<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261012230000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Prevent duplicate organization-month CCQ declarations without deleting existing filings.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf((bool) $this->connection->fetchOne('SELECT EXISTS (SELECT 1 FROM ccqdeclaration GROUP BY organization_id, period_year, period_month HAVING COUNT(*) > 1)'), 'Duplicate CCQ periods exist. Reconcile them before adding the unique constraint; no declarations were deleted.');
        $this->addSql('CREATE TABLE IF NOT EXISTS cache_items (item_id VARCHAR(255) NOT NULL PRIMARY KEY, item_data BYTEA NOT NULL, item_lifetime INT DEFAULT NULL, item_time INT NOT NULL)');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uniq_ccq_organization_period ON ccqdeclaration (organization_id, period_month, period_year)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_ccq_organization_period');
    }
}
