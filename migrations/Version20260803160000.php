<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Rename magic_link.magic_link_token → token_hash (stores sha256 hex, 64 chars).
 */
final class Version20260803160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename magic_link.magic_link_token to token_hash (sha256, length 64).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE magic_link RENAME COLUMN magic_link_token TO token_hash');
        $this->addSql('ALTER TABLE magic_link ALTER COLUMN token_hash TYPE VARCHAR(64)');
        $this->addSql('ALTER TABLE magic_link ALTER COLUMN token_hash SET NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE magic_link ALTER COLUMN token_hash TYPE VARCHAR(255)');
        $this->addSql('ALTER TABLE magic_link RENAME COLUMN token_hash TO magic_link_token');
    }
}
