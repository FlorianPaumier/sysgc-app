<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260916110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds safety log lifecycle and immutable completed snapshot fields.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE safety_log ADD status VARCHAR(20) NOT NULL DEFAULT 'draft'");
        $this->addSql('ALTER TABLE safety_log ADD original_checklist_items JSONB DEFAULT NULL');
        $this->addSql('ALTER TABLE safety_log ADD original_hazard_photos JSONB DEFAULT NULL');
        $this->addSql('ALTER TABLE safety_log ADD original_worker_signatures JSONB DEFAULT NULL');
        $this->addSql('ALTER TABLE safety_log ADD original_weather VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE safety_log ADD original_incident_report TEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE safety_log DROP original_incident_report');
        $this->addSql('ALTER TABLE safety_log DROP original_weather');
        $this->addSql('ALTER TABLE safety_log DROP original_worker_signatures');
        $this->addSql('ALTER TABLE safety_log DROP original_hazard_photos');
        $this->addSql('ALTER TABLE safety_log DROP original_checklist_items');
        $this->addSql('ALTER TABLE safety_log DROP status');
    }
}
