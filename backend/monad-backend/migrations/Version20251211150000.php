<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251211150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add featured_image column to quests table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE quests ADD featured_image VARCHAR(512) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE quests DROP featured_image');
    }
}
