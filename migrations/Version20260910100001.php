<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260910100001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add change-order audit fields and invoice milestone/payment fields.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE change_order ADD title VARCHAR(255) DEFAULT '' NOT NULL");
        $this->addSql('ALTER TABLE change_order ADD description TEXT DEFAULT NULL');
        $this->addSql("ALTER TABLE change_order ADD cost_impact NUMERIC(12, 2) DEFAULT '0.00' NOT NULL");
        $this->addSql('ALTER TABLE change_order ADD schedule_impact_days INT DEFAULT 0 NOT NULL');
        $this->addSql("ALTER TABLE change_order ADD status VARCHAR(255) DEFAULT 'pending' NOT NULL");
        $this->addSql("ALTER TABLE change_order ADD audit_trail JSONB DEFAULT '[]' NOT NULL");
        $this->addSql('ALTER TABLE change_order ADD requested_by VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE change_order ADD approved_by VARCHAR(255) DEFAULT NULL');
        $this->addSql("ALTER TABLE invoice ADD milestone_name VARCHAR(255) DEFAULT '' NOT NULL");
        $this->addSql('ALTER TABLE invoice ADD percentage INT DEFAULT 0 NOT NULL');
        $this->addSql("ALTER TABLE invoice ADD amount NUMERIC(12, 2) DEFAULT '0.00' NOT NULL");
        $this->addSql('ALTER TABLE invoice ADD sent_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE invoice ADD paid_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE invoice ADD stripe_invoice_id VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE change_order DROP title, DROP description, DROP cost_impact, DROP schedule_impact_days, DROP status, DROP audit_trail, DROP requested_by, DROP approved_by');
        $this->addSql('ALTER TABLE invoice DROP milestone_name, DROP percentage, DROP amount, DROP sent_at, DROP paid_at, DROP stripe_invoice_id');
    }
}
