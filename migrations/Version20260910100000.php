<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260910100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds the complete daily CNESST safety-log payload and one log per project day constraint.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE safety_log ADD weather VARCHAR(100) NOT NULL DEFAULT ''");
        $this->addSql("ALTER TABLE safety_log ADD checklist_items JSONB NOT NULL DEFAULT '[]'");
        $this->addSql("ALTER TABLE safety_log ADD hazard_photos JSONB NOT NULL DEFAULT '[]'");
        $this->addSql("ALTER TABLE safety_log ADD worker_signatures JSONB NOT NULL DEFAULT '[]'");
        $this->addSql('ALTER TABLE safety_log ADD incident_report TEXT DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX uniq_safety_log_project_date ON safety_log (project_id, date)');
        $this->addSql('CREATE INDEX safety_log_checklist_items_gin ON safety_log USING GIN (checklist_items)');
        $this->addSql('CREATE INDEX safety_log_hazard_photos_gin ON safety_log USING GIN (hazard_photos)');
        $this->addSql('CREATE INDEX safety_log_worker_signatures_gin ON safety_log USING GIN (worker_signatures)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX safety_log_worker_signatures_gin');
        $this->addSql('DROP INDEX safety_log_hazard_photos_gin');
        $this->addSql('DROP INDEX safety_log_checklist_items_gin');
        $this->addSql('DROP INDEX uniq_safety_log_project_date');
        $this->addSql('ALTER TABLE safety_log DROP incident_report');
        $this->addSql('ALTER TABLE safety_log DROP worker_signatures');
        $this->addSql('ALTER TABLE safety_log DROP hazard_photos');
        $this->addSql('ALTER TABLE safety_log DROP checklist_items');
        $this->addSql('ALTER TABLE safety_log DROP weather');
    }
}
