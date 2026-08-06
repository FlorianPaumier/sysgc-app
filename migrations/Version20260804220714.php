<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260804220714 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Creates Project, Subcontractor, SafetyLog, ChangeOrder, Invoice entities with relations';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE change_order (id UUID NOT NULL, project_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_A9F92ED5166D1F9C ON change_order (project_id)');
        $this->addSql('CREATE TABLE invoice (id UUID NOT NULL, project_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_90651744166D1F9C ON invoice (project_id)');
        $this->addSql('CREATE TABLE project (id UUID NOT NULL, name VARCHAR(255) DEFAULT NULL, address VARCHAR(255) NOT NULL, client_name VARCHAR(255) NOT NULL, start_date DATE DEFAULT NULL, end_date DATE DEFAULT NULL, status VARCHAR(255) NOT NULL, budget NUMERIC(12, 2) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, organization_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_2FB3D0EE32C8A3DE ON project (organization_id)');
        $this->addSql('CREATE TABLE project_subcontractor (project_id UUID NOT NULL, subcontractor_id UUID NOT NULL, PRIMARY KEY (project_id, subcontractor_id))');
        $this->addSql('CREATE INDEX IDX_C87BA24A166D1F9C ON project_subcontractor (project_id)');
        $this->addSql('CREATE INDEX IDX_C87BA24AFD2F7858 ON project_subcontractor (subcontractor_id)');
        $this->addSql('CREATE TABLE safety_log (id UUID NOT NULL, date DATE NOT NULL, project_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_FD876A3E166D1F9C ON safety_log (project_id)');
        $this->addSql('CREATE TABLE subcontractor (id UUID NOT NULL, name VARCHAR(255) NOT NULL, rbq_license_number VARCHAR(255) DEFAULT NULL, is_verified BOOLEAN DEFAULT false NOT NULL, last_verified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('ALTER TABLE change_order ADD CONSTRAINT FK_A9F92ED5166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE invoice ADD CONSTRAINT FK_90651744166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE project ADD CONSTRAINT FK_2FB3D0EE32C8A3DE FOREIGN KEY (organization_id) REFERENCES organization (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE project_subcontractor ADD CONSTRAINT FK_C87BA24A166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE project_subcontractor ADD CONSTRAINT FK_C87BA24AFD2F7858 FOREIGN KEY (subcontractor_id) REFERENCES subcontractor (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE safety_log ADD CONSTRAINT FK_FD876A3E166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE change_order DROP CONSTRAINT FK_A9F92ED5166D1F9C');
        $this->addSql('ALTER TABLE invoice DROP CONSTRAINT FK_90651744166D1F9C');
        $this->addSql('ALTER TABLE project DROP CONSTRAINT FK_2FB3D0EE32C8A3DE');
        $this->addSql('ALTER TABLE project_subcontractor DROP CONSTRAINT FK_C87BA24A166D1F9C');
        $this->addSql('ALTER TABLE project_subcontractor DROP CONSTRAINT FK_C87BA24AFD2F7858');
        $this->addSql('ALTER TABLE safety_log DROP CONSTRAINT FK_FD876A3E166D1F9C');
        $this->addSql('DROP TABLE change_order');
        $this->addSql('DROP TABLE invoice');
        $this->addSql('DROP TABLE project');
        $this->addSql('DROP TABLE project_subcontractor');
        $this->addSql('DROP TABLE safety_log');
        $this->addSql('DROP TABLE subcontractor');
    }
}
