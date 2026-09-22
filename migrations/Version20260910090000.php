<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260910090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Make CCQ declarations exportable and scope workforce hours to a project.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE hours_entry ADD project_id UUID DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_386693E166D1F9C ON hours_entry (project_id)');
        $this->addSql('ALTER TABLE hours_entry ADD CONSTRAINT FK_386693E166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) NOT DEFERRABLE');

        // Version20260810021559 already owns the CCQ declaration payload,
        // organization relation, and their foreign-key/index. Keep this
        // migration focused on the export/tenant constraints added here.
        $this->addSql('UPDATE ccqdeclaration SET organization_id = project.organization_id FROM project WHERE ccqdeclaration.project_id = project.id');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS UNIQ_CCQ_ORGANIZATION_PERIOD ON ccqdeclaration (organization_id, period_month, period_year)');
        $this->addSql('CREATE INDEX IF NOT EXISTS IDX_CCQ_DECLARATION_DATA ON ccqdeclaration USING GIN (declaration)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS IDX_CCQ_DECLARATION_DATA');
        $this->addSql('DROP INDEX IF EXISTS UNIQ_CCQ_ORGANIZATION_PERIOD');
        $this->addSql('ALTER TABLE hours_entry DROP CONSTRAINT FK_386693E166D1F9C');
        $this->addSql('DROP INDEX IDX_386693E166D1F9C');
        $this->addSql('ALTER TABLE hours_entry DROP project_id');
    }
}
