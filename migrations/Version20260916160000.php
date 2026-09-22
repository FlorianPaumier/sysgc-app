<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260916160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Persist the CCQ rule version used by each declaration and filed snapshot.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE ccqdeclaration ADD rule_version VARCHAR(30) DEFAULT 'CCQ-2026.1' NOT NULL");
        $this->addSql('ALTER TABLE ccqdeclaration ADD filed_rule_version VARCHAR(30) DEFAULT NULL');
        $this->addSql('UPDATE ccqdeclaration SET filed_rule_version = rule_version WHERE filed_snapshot IS NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ccqdeclaration DROP filed_rule_version');
        $this->addSql('ALTER TABLE ccqdeclaration DROP rule_version');
    }
}
