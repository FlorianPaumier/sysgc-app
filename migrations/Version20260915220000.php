<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260915220000 extends AbstractMigration
{
    public function getDescription(): string { return 'Add organization-bound member invitations and invitation project assignments.'; }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE invitation (id UUID NOT NULL, organization_id UUID NOT NULL, email VARCHAR(180) NOT NULL, role VARCHAR(20) NOT NULL, token_hash VARCHAR(64) NOT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, accepted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, revoked_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_INVITATION_TOKEN ON invitation (token_hash)');
        $this->addSql('CREATE INDEX IDX_INVITATION_ORGANIZATION ON invitation (organization_id)');
        $this->addSql('ALTER TABLE invitation ADD CONSTRAINT FK_INVITATION_ORGANIZATION FOREIGN KEY (organization_id) REFERENCES organization (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE TABLE invitation_project_assignment (invitation_id UUID NOT NULL, project_id UUID NOT NULL, PRIMARY KEY(invitation_id, project_id))');
        $this->addSql('CREATE INDEX IDX_INVITATION_PROJECT_INVITATION ON invitation_project_assignment (invitation_id)');
        $this->addSql('CREATE INDEX IDX_INVITATION_PROJECT_PROJECT ON invitation_project_assignment (project_id)');
        $this->addSql('ALTER TABLE invitation_project_assignment ADD CONSTRAINT FK_INVITATION_PROJECT_INVITATION FOREIGN KEY (invitation_id) REFERENCES invitation (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE invitation_project_assignment ADD CONSTRAINT FK_INVITATION_PROJECT_PROJECT FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE invitation_project_assignment');
        $this->addSql('DROP TABLE invitation');
    }
}
