<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Adds user-initiated data deletion request columns.
 *
 * The anonymization workflow itself is unchanged (AdminController::deleteUserData
 * still performs retention-safe anonymization). These columns let end users
 * explicitly lodge a deletion request, giving admins a filterable list and
 * preserving a clear audit trail of "requested" vs "executed" deletion.
 */
final class Version20260413000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add user-initiated deletion request columns';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "ALTER TABLE `user`"
            . " ADD deletion_requested_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',"
            . " ADD deletion_request_reason LONGTEXT DEFAULT NULL"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` DROP deletion_requested_at, DROP deletion_request_reason');
    }
}
