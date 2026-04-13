<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Adds user.supervisor_id so approval step 1 can be routed to the
 * requester's actual supervisor (replacing the previous global-first
 * supervisor lookup).
 */
final class Version20260413000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add user.supervisor_id for requester-specific supervisor routing';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` ADD supervisor_id INT DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_user_supervisor ON `user` (supervisor_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IDX_user_supervisor ON `user`');
        $this->addSql('ALTER TABLE `user` DROP supervisor_id');
    }
}
