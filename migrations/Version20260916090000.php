<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260916090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add RBQ verification state, last-success evidence, and manual verification metadata.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE subcontractor ADD verification_status VARCHAR(32) DEFAULT 'unverified' NOT NULL");
        $this->addSql('ALTER TABLE subcontractor ADD last_verification_attempt_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE subcontractor ADD last_verification_error VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE subcontractor ADD verification_source VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE subcontractor ADD verified_business_name VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE subcontractor ADD manual_verification_notes TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE subcontractor ADD evidence_original_name VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE subcontractor ADD evidence_path VARCHAR(500) DEFAULT NULL');
        $this->addSql('ALTER TABLE subcontractor ADD verified_by_id UUID DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_SUBCONTRACTOR_VERIFIED_BY ON subcontractor (verified_by_id)');
        $this->addSql('ALTER TABLE subcontractor ADD CONSTRAINT FK_SUBCONTRACTOR_VERIFIED_BY FOREIGN KEY (verified_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql("UPDATE subcontractor SET verification_status = 'verified', verification_source = 'Registre RBQ', last_verification_attempt_at = last_verified_at WHERE is_verified = TRUE");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE subcontractor DROP CONSTRAINT FK_SUBCONTRACTOR_VERIFIED_BY');
        $this->addSql('DROP INDEX IDX_SUBCONTRACTOR_VERIFIED_BY');
        $this->addSql('ALTER TABLE subcontractor DROP verified_by_id');
        $this->addSql('ALTER TABLE subcontractor DROP evidence_path');
        $this->addSql('ALTER TABLE subcontractor DROP evidence_original_name');
        $this->addSql('ALTER TABLE subcontractor DROP manual_verification_notes');
        $this->addSql('ALTER TABLE subcontractor DROP verified_business_name');
        $this->addSql('ALTER TABLE subcontractor DROP verification_source');
        $this->addSql('ALTER TABLE subcontractor DROP last_verification_error');
        $this->addSql('ALTER TABLE subcontractor DROP last_verification_attempt_at');
        $this->addSql('ALTER TABLE subcontractor DROP verification_status');
    }
}
