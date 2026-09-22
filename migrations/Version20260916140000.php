<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260916140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds subscription expiry tracking for grace and read-only states.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE organization ADD subscription_expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE organization DROP subscription_expires_at');
    }
}
