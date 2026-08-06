<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260806173147 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add CCQDeclaration entity, Invoice status, make project start_date nullable';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE ccqdeclaration (id UUID NOT NULL, period_month INT NOT NULL, period_year INT NOT NULL, project_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_3CF5C310166D1F9C ON ccqdeclaration (project_id)');
        $this->addSql('ALTER TABLE ccqdeclaration ADD CONSTRAINT FK_3CF5C310166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) NOT DEFERRABLE');
        $this->addSql("ALTER TABLE invoice ADD status VARCHAR(255) DEFAULT 'draft' NOT NULL");
        $this->addSql('ALTER TABLE project ALTER start_date DROP NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE ccqdeclaration DROP CONSTRAINT FK_3CF5C310166D1F9C');
        $this->addSql('DROP TABLE ccqdeclaration');
        $this->addSql('ALTER TABLE invoice DROP status');
        $this->addSql('ALTER TABLE project ALTER start_date SET NOT NULL');
    }
}
