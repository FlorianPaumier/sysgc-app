<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260916120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds CCQ export, manual filing, outcome, and immutable snapshot fields.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ccqdeclaration ADD filed_snapshot JSONB DEFAULT NULL');
        $this->addSql('ALTER TABLE ccqdeclaration ADD exported_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE ccqdeclaration ADD filed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE ccqdeclaration ADD outcome VARCHAR(20) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ccqdeclaration DROP outcome');
        $this->addSql('ALTER TABLE ccqdeclaration DROP filed_at');
        $this->addSql('ALTER TABLE ccqdeclaration DROP exported_at');
        $this->addSql('ALTER TABLE ccqdeclaration DROP filed_snapshot');
    }
}
