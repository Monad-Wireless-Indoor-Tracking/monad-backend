<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251111174124 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE "news" (id UUID NOT NULL, created_by_id UUID NOT NULL, title VARCHAR(255) NOT NULL, content TEXT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_1DD39950B03A8386 ON "news" (created_by_id)');
        $this->addSql('COMMENT ON COLUMN "news".id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN "news".created_by_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN "news".created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN "news".updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE "qr_codes" (id UUID NOT NULL, created_by_id UUID NOT NULL, name VARCHAR(255) NOT NULL, position TEXT NOT NULL, value VARCHAR(255) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_A07803171D775834 ON "qr_codes" (value)');
        $this->addSql('CREATE INDEX IDX_A0780317B03A8386 ON "qr_codes" (created_by_id)');
        $this->addSql('COMMENT ON COLUMN "qr_codes".id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN "qr_codes".created_by_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN "qr_codes".created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN "qr_codes".updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE quest_enrollments (id UUID NOT NULL, user_id UUID NOT NULL, quest_id UUID NOT NULL, status VARCHAR(255) NOT NULL, data_path VARCHAR(512) DEFAULT NULL, completed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_FB8854C5A76ED395 ON quest_enrollments (user_id)');
        $this->addSql('CREATE INDEX IDX_FB8854C5209E9EF4 ON quest_enrollments (quest_id)');
        $this->addSql('CREATE INDEX enrollment_status_idx ON quest_enrollments (status)');
        $this->addSql('CREATE UNIQUE INDEX user_quest_enrollment_idx ON quest_enrollments (user_id, quest_id)');
        $this->addSql('COMMENT ON COLUMN quest_enrollments.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN quest_enrollments.user_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN quest_enrollments.quest_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN quest_enrollments.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE quest_step_completions (id UUID NOT NULL, enrollment_id UUID NOT NULL, step_id UUID NOT NULL, status VARCHAR(255) NOT NULL, started_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, completed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, step_data JSON NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_216D4C788F7DB25B ON quest_step_completions (enrollment_id)');
        $this->addSql('CREATE INDEX IDX_216D4C7873B21E9C ON quest_step_completions (step_id)');
        $this->addSql('CREATE UNIQUE INDEX enrollment_step_idx ON quest_step_completions (enrollment_id, step_id)');
        $this->addSql('COMMENT ON COLUMN quest_step_completions.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN quest_step_completions.enrollment_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN quest_step_completions.step_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN quest_step_completions.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE quest_step_skip_records (id UUID NOT NULL, step_completion_id UUID NOT NULL, message TEXT NOT NULL, error_code VARCHAR(100) DEFAULT NULL, metadata JSON NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX skip_record_completion_idx ON quest_step_skip_records (step_completion_id)');
        $this->addSql('COMMENT ON COLUMN quest_step_skip_records.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN quest_step_skip_records.step_completion_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN quest_step_skip_records.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE quest_steps (id UUID NOT NULL, quest_id UUID NOT NULL, name VARCHAR(255) NOT NULL, type VARCHAR(255) NOT NULL, "order" INT NOT NULL, config JSON NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_679F93EE209E9EF4 ON quest_steps (quest_id)');
        $this->addSql('CREATE UNIQUE INDEX quest_step_order_idx ON quest_steps (quest_id, "order")');
        $this->addSql('COMMENT ON COLUMN quest_steps.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN quest_steps.quest_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN quest_steps.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN quest_steps.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE quests (id UUID NOT NULL, created_by_id UUID NOT NULL, name VARCHAR(255) NOT NULL, description TEXT NOT NULL, available_from TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, available_to TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, points DOUBLE PRECISION NOT NULL, estimated_duration INT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_989E5D34B03A8386 ON quests (created_by_id)');
        $this->addSql('COMMENT ON COLUMN quests.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN quests.created_by_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN quests.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN quests.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE "news" ADD CONSTRAINT FK_1DD39950B03A8386 FOREIGN KEY (created_by_id) REFERENCES "users" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE "qr_codes" ADD CONSTRAINT FK_A0780317B03A8386 FOREIGN KEY (created_by_id) REFERENCES "users" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE quest_enrollments ADD CONSTRAINT FK_FB8854C5A76ED395 FOREIGN KEY (user_id) REFERENCES "users" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE quest_enrollments ADD CONSTRAINT FK_FB8854C5209E9EF4 FOREIGN KEY (quest_id) REFERENCES quests (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE quest_step_completions ADD CONSTRAINT FK_216D4C788F7DB25B FOREIGN KEY (enrollment_id) REFERENCES quest_enrollments (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE quest_step_completions ADD CONSTRAINT FK_216D4C7873B21E9C FOREIGN KEY (step_id) REFERENCES quest_steps (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE quest_step_skip_records ADD CONSTRAINT FK_CB65848EAF7F8B88 FOREIGN KEY (step_completion_id) REFERENCES quest_step_completions (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE quest_steps ADD CONSTRAINT FK_679F93EE209E9EF4 FOREIGN KEY (quest_id) REFERENCES quests (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE quests ADD CONSTRAINT FK_989E5D34B03A8386 FOREIGN KEY (created_by_id) REFERENCES "users" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE users ADD status VARCHAR(255) DEFAULT NULL');
        $this->addSql('UPDATE users SET status = \'active\' WHERE status IS NULL');
        $this->addSql('ALTER TABLE users ALTER COLUMN status SET NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE "news" DROP CONSTRAINT FK_1DD39950B03A8386');
        $this->addSql('ALTER TABLE "qr_codes" DROP CONSTRAINT FK_A0780317B03A8386');
        $this->addSql('ALTER TABLE quest_enrollments DROP CONSTRAINT FK_FB8854C5A76ED395');
        $this->addSql('ALTER TABLE quest_enrollments DROP CONSTRAINT FK_FB8854C5209E9EF4');
        $this->addSql('ALTER TABLE quest_step_completions DROP CONSTRAINT FK_216D4C788F7DB25B');
        $this->addSql('ALTER TABLE quest_step_completions DROP CONSTRAINT FK_216D4C7873B21E9C');
        $this->addSql('ALTER TABLE quest_step_skip_records DROP CONSTRAINT FK_CB65848EAF7F8B88');
        $this->addSql('ALTER TABLE quest_steps DROP CONSTRAINT FK_679F93EE209E9EF4');
        $this->addSql('ALTER TABLE quests DROP CONSTRAINT FK_989E5D34B03A8386');
        $this->addSql('DROP TABLE "news"');
        $this->addSql('DROP TABLE "qr_codes"');
        $this->addSql('DROP TABLE quest_enrollments');
        $this->addSql('DROP TABLE quest_step_completions');
        $this->addSql('DROP TABLE quest_step_skip_records');
        $this->addSql('DROP TABLE quest_steps');
        $this->addSql('DROP TABLE quests');
        $this->addSql('ALTER TABLE "users" DROP status');
    }
}
