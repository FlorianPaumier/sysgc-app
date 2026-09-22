<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260810021559 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ccqdeclaration ADD declaration JSONB NOT NULL');
        $this->addSql('ALTER TABLE ccqdeclaration ADD status VARCHAR(30) NOT NULL');
        $this->addSql('ALTER TABLE ccqdeclaration ADD submitted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE ccqdeclaration ADD receipt_path TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE ccqdeclaration ADD organization_id UUID NOT NULL');
        $this->addSql('ALTER TABLE ccqdeclaration ADD CONSTRAINT FK_3CF5C31032C8A3DE FOREIGN KEY (organization_id) REFERENCES organization (id) NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_3CF5C31032C8A3DE ON ccqdeclaration (organization_id)');
    }

    public function down(Schema $schema): void
    {

        $this->addSql('ALTER TABLE ccqdeclaration DROP CONSTRAINT FK_3CF5C31032C8A3DE');
        $this->addSql('DROP INDEX IDX_3CF5C31032C8A3DE');
        $this->addSql('ALTER TABLE ccqdeclaration DROP declaration');
        $this->addSql('ALTER TABLE ccqdeclaration DROP status');
        $this->addSql('ALTER TABLE ccqdeclaration DROP submitted_at');
        $this->addSql('ALTER TABLE ccqdeclaration DROP receipt_path');
        $this->addSql('ALTER TABLE ccqdeclaration DROP organization_id');
    }
}
