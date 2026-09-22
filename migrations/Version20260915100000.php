<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260915100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add explicit foreman-to-project assignments.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE project_foreman_assignment (project_id UUID NOT NULL, user_id UUID NOT NULL, PRIMARY KEY(project_id, user_id))');
        $this->addSql('CREATE INDEX IDX_PROJECT_FOREMAN_PROJECT ON project_foreman_assignment (project_id)');
        $this->addSql('CREATE INDEX IDX_PROJECT_FOREMAN_USER ON project_foreman_assignment (user_id)');
        $this->addSql('ALTER TABLE project_foreman_assignment ADD CONSTRAINT FK_PROJECT_FOREMAN_PROJECT FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE project_foreman_assignment ADD CONSTRAINT FK_PROJECT_FOREMAN_USER FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE project_foreman_assignment');
    }
}
