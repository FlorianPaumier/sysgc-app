<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260907160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Align Worker.ccqTrade with the specification: one trade per worker.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE worker SET ccq_trade = split_part(ccq_trade, ',', 1) WHERE ccq_trade LIKE '%,%'");
        $this->addSql('ALTER TABLE worker ALTER ccq_trade TYPE VARCHAR(255)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE worker ALTER ccq_trade TYPE TEXT');
    }
}
