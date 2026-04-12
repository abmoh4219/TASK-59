<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20240101000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Initial schema — all entities for Workforce & Operations Hub';
    }

    public function up(Schema $schema): void
    {
        // Users table
        $this->addSql('CREATE TABLE `user` (
            id INT AUTO_INCREMENT NOT NULL,
            username VARCHAR(50) NOT NULL,
            email VARCHAR(180) NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            role VARCHAR(30) NOT NULL,
            first_name VARCHAR(100) NOT NULL,
            last_name VARCHAR(100) NOT NULL,
            phone_encrypted LONGTEXT DEFAULT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            backup_approver_id INT DEFAULT NULL,
            is_out TINYINT(1) NOT NULL DEFAULT 0,
            failed_login_count INT NOT NULL DEFAULT 0,
            locked_until DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            deleted_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            UNIQUE INDEX UNIQ_8D93D649F85E0677 (username),
            UNIQUE INDEX UNIQ_8D93D649E7927C74 (email),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Shift schedules
        $this->addSql('CREATE TABLE shift_schedule (
            id INT AUTO_INCREMENT NOT NULL,
            user_id INT NOT NULL,
            day_of_week INT NOT NULL,
            shift_start TIME NOT NULL COMMENT \'(DC2Type:time_immutable)\',
            shift_end TIME NOT NULL COMMENT \'(DC2Type:time_immutable)\',
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            INDEX IDX_shift_user (user_id),
            PRIMARY KEY(id),
            CONSTRAINT FK_shift_user FOREIGN KEY (user_id) REFERENCES `user` (id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Punch events
        $this->addSql('CREATE TABLE punch_event (
            id INT AUTO_INCREMENT NOT NULL,
            user_id INT NOT NULL,
            event_date DATE NOT NULL COMMENT \'(DC2Type:date_immutable)\',
            event_time TIME NOT NULL COMMENT \'(DC2Type:time_immutable)\',
            event_type VARCHAR(10) NOT NULL,
            source VARCHAR(20) NOT NULL,
            imported_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_punch_user (user_id),
            UNIQUE INDEX UNIQ_punch_unique (user_id, event_date, event_time, event_type),
            PRIMARY KEY(id),
            CONSTRAINT FK_punch_user FOREIGN KEY (user_id) REFERENCES `user` (id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Attendance records
        $this->addSql('CREATE TABLE attendance_record (
            id INT AUTO_INCREMENT NOT NULL,
            user_id INT NOT NULL,
            record_date DATE NOT NULL COMMENT \'(DC2Type:date_immutable)\',
            first_punch_in TIME DEFAULT NULL COMMENT \'(DC2Type:time_immutable)\',
            last_punch_out TIME DEFAULT NULL COMMENT \'(DC2Type:time_immutable)\',
            total_minutes INT NOT NULL DEFAULT 0,
            exceptions JSON NOT NULL,
            generated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_attendance_user (user_id),
            UNIQUE INDEX UNIQ_attendance_unique (user_id, record_date),
            PRIMARY KEY(id),
            CONSTRAINT FK_attendance_user FOREIGN KEY (user_id) REFERENCES `user` (id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Exception rules
        $this->addSql('CREATE TABLE exception_rule (
            id INT AUTO_INCREMENT NOT NULL,
            rule_type VARCHAR(50) NOT NULL,
            tolerance_minutes INT NOT NULL DEFAULT 5,
            missed_punch_window_minutes INT NOT NULL DEFAULT 30,
            filing_window_days INT NOT NULL DEFAULT 7,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            updated_by_id INT DEFAULT NULL,
            updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_rule_updater (updated_by_id),
            PRIMARY KEY(id),
            CONSTRAINT FK_rule_updater FOREIGN KEY (updated_by_id) REFERENCES `user` (id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Attendance exceptions
        $this->addSql('CREATE TABLE attendance_exception (
            id INT AUTO_INCREMENT NOT NULL,
            attendance_record_id INT NOT NULL,
            exception_type VARCHAR(30) NOT NULL,
            detected_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            resolved_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            resolved_by_id INT DEFAULT NULL,
            INDEX IDX_exc_record (attendance_record_id),
            INDEX IDX_exc_resolver (resolved_by_id),
            PRIMARY KEY(id),
            CONSTRAINT FK_exc_record FOREIGN KEY (attendance_record_id) REFERENCES attendance_record (id),
            CONSTRAINT FK_exc_resolver FOREIGN KEY (resolved_by_id) REFERENCES `user` (id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Exception requests
        $this->addSql('CREATE TABLE exception_request (
            id INT AUTO_INCREMENT NOT NULL,
            user_id INT NOT NULL,
            request_type VARCHAR(30) NOT NULL,
            start_date DATE NOT NULL COMMENT \'(DC2Type:date_immutable)\',
            end_date DATE NOT NULL COMMENT \'(DC2Type:date_immutable)\',
            start_time TIME DEFAULT NULL COMMENT \'(DC2Type:time_immutable)\',
            end_time TIME DEFAULT NULL COMMENT \'(DC2Type:time_immutable)\',
            reason LONGTEXT NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT \'PENDING\',
            current_approver_id INT DEFAULT NULL,
            step_number INT NOT NULL DEFAULT 1,
            client_key VARCHAR(100) DEFAULT NULL,
            filed_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_req_user (user_id),
            INDEX IDX_req_approver (current_approver_id),
            INDEX IDX_req_client_key (client_key),
            PRIMARY KEY(id),
            CONSTRAINT FK_req_user FOREIGN KEY (user_id) REFERENCES `user` (id),
            CONSTRAINT FK_req_approver FOREIGN KEY (current_approver_id) REFERENCES `user` (id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Approval steps
        $this->addSql('CREATE TABLE approval_step (
            id INT AUTO_INCREMENT NOT NULL,
            exception_request_id INT NOT NULL,
            step_number INT NOT NULL,
            approver_id INT NOT NULL,
            backup_approver_id INT DEFAULT NULL,
            status VARCHAR(20) NOT NULL DEFAULT \'PENDING\',
            sla_deadline DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            escalated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            acted_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_step_request (exception_request_id),
            INDEX IDX_step_approver (approver_id),
            PRIMARY KEY(id),
            CONSTRAINT FK_step_request FOREIGN KEY (exception_request_id) REFERENCES exception_request (id),
            CONSTRAINT FK_step_approver FOREIGN KEY (approver_id) REFERENCES `user` (id),
            CONSTRAINT FK_step_backup FOREIGN KEY (backup_approver_id) REFERENCES `user` (id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Approval actions
        $this->addSql('CREATE TABLE approval_action (
            id INT AUTO_INCREMENT NOT NULL,
            approval_step_id INT NOT NULL,
            actor_id INT NOT NULL,
            action VARCHAR(20) NOT NULL,
            comment LONGTEXT DEFAULT NULL,
            acted_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_action_step (approval_step_id),
            INDEX IDX_action_actor (actor_id),
            PRIMARY KEY(id),
            CONSTRAINT FK_action_step FOREIGN KEY (approval_step_id) REFERENCES approval_step (id),
            CONSTRAINT FK_action_actor FOREIGN KEY (actor_id) REFERENCES `user` (id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Work orders
        $this->addSql('CREATE TABLE work_order (
            id INT AUTO_INCREMENT NOT NULL,
            submitted_by_id INT NOT NULL,
            category VARCHAR(50) NOT NULL,
            priority VARCHAR(20) NOT NULL,
            description LONGTEXT NOT NULL,
            building VARCHAR(100) NOT NULL,
            room VARCHAR(50) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT \'submitted\',
            assigned_dispatcher_id INT DEFAULT NULL,
            assigned_technician_id INT DEFAULT NULL,
            dispatched_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            accepted_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            started_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            completed_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            rated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            rating INT DEFAULT NULL,
            completion_notes LONGTEXT DEFAULT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_wo_submitter (submitted_by_id),
            INDEX IDX_wo_dispatcher (assigned_dispatcher_id),
            INDEX IDX_wo_technician (assigned_technician_id),
            PRIMARY KEY(id),
            CONSTRAINT FK_wo_submitter FOREIGN KEY (submitted_by_id) REFERENCES `user` (id),
            CONSTRAINT FK_wo_dispatcher FOREIGN KEY (assigned_dispatcher_id) REFERENCES `user` (id),
            CONSTRAINT FK_wo_technician FOREIGN KEY (assigned_technician_id) REFERENCES `user` (id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Work order photos
        $this->addSql('CREATE TABLE work_order_photo (
            id INT AUTO_INCREMENT NOT NULL,
            work_order_id INT NOT NULL,
            original_filename VARCHAR(255) NOT NULL,
            stored_path VARCHAR(500) NOT NULL,
            mime_type VARCHAR(50) NOT NULL,
            size_bytes INT NOT NULL,
            sha256_hash VARCHAR(64) NOT NULL,
            uploaded_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_photo_wo (work_order_id),
            PRIMARY KEY(id),
            CONSTRAINT FK_photo_wo FOREIGN KEY (work_order_id) REFERENCES work_order (id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Bookable resources
        $this->addSql('CREATE TABLE bookable_resource (
            id INT AUTO_INCREMENT NOT NULL,
            name VARCHAR(100) NOT NULL,
            type VARCHAR(50) NOT NULL,
            cost_center VARCHAR(50) NOT NULL,
            capacity INT NOT NULL,
            is_available TINYINT(1) NOT NULL DEFAULT 1,
            description LONGTEXT DEFAULT NULL,
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Bookings
        $this->addSql('CREATE TABLE booking (
            id INT AUTO_INCREMENT NOT NULL,
            requester_id INT NOT NULL,
            resource_id INT NOT NULL,
            start_datetime DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            end_datetime DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            purpose LONGTEXT NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT \'active\',
            client_key VARCHAR(100) DEFAULT NULL,
            allocations JSON NOT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_booking_requester (requester_id),
            INDEX IDX_booking_resource (resource_id),
            PRIMARY KEY(id),
            CONSTRAINT FK_booking_requester FOREIGN KEY (requester_id) REFERENCES `user` (id),
            CONSTRAINT FK_booking_resource FOREIGN KEY (resource_id) REFERENCES bookable_resource (id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Booking allocations
        $this->addSql('CREATE TABLE booking_allocation (
            id INT AUTO_INCREMENT NOT NULL,
            booking_id INT NOT NULL,
            traveler_id INT NOT NULL,
            cost_center VARCHAR(50) NOT NULL,
            amount NUMERIC(10, 2) NOT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_alloc_booking (booking_id),
            INDEX IDX_alloc_traveler (traveler_id),
            PRIMARY KEY(id),
            CONSTRAINT FK_alloc_booking FOREIGN KEY (booking_id) REFERENCES booking (id),
            CONSTRAINT FK_alloc_traveler FOREIGN KEY (traveler_id) REFERENCES `user` (id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Idempotency keys
        $this->addSql('CREATE TABLE idempotency_key (
            id INT AUTO_INCREMENT NOT NULL,
            client_key VARCHAR(100) NOT NULL,
            entity_type VARCHAR(50) NOT NULL,
            entity_id INT NOT NULL,
            expires_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            UNIQUE INDEX UNIQ_idemp_key (client_key),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // File uploads
        $this->addSql('CREATE TABLE file_upload (
            id INT AUTO_INCREMENT NOT NULL,
            uploader_id INT NOT NULL,
            entity_type VARCHAR(50) NOT NULL,
            entity_id INT NOT NULL,
            original_filename VARCHAR(255) NOT NULL,
            stored_path VARCHAR(500) NOT NULL,
            mime_type VARCHAR(50) NOT NULL,
            size_bytes INT NOT NULL,
            sha256_hash VARCHAR(64) NOT NULL,
            uploaded_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_upload_uploader (uploader_id),
            PRIMARY KEY(id),
            CONSTRAINT FK_upload_uploader FOREIGN KEY (uploader_id) REFERENCES `user` (id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Audit log — APPEND-ONLY, no UPDATE or DELETE permitted
        $this->addSql('CREATE TABLE audit_log (
            id INT AUTO_INCREMENT NOT NULL,
            actor_id INT DEFAULT NULL,
            actor_username VARCHAR(50) NOT NULL,
            action VARCHAR(50) NOT NULL,
            entity_type VARCHAR(50) NOT NULL,
            entity_id INT DEFAULT NULL,
            old_value_masked JSON DEFAULT NULL,
            new_value_masked JSON DEFAULT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            user_agent VARCHAR(500) DEFAULT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_audit_created (created_at),
            INDEX IDX_audit_entity (entity_type),
            INDEX IDX_audit_actor (actor_username),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Failed login attempts
        $this->addSql('CREATE TABLE failed_login_attempt (
            id INT AUTO_INCREMENT NOT NULL,
            username VARCHAR(50) NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            attempted_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_failed_login_username (username),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Note: doctrine_migration_versions table is created automatically by Doctrine Migrations
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS failed_login_attempt');
        $this->addSql('DROP TABLE IF EXISTS audit_log');
        $this->addSql('DROP TABLE IF EXISTS file_upload');
        $this->addSql('DROP TABLE IF EXISTS idempotency_key');
        $this->addSql('DROP TABLE IF EXISTS booking_allocation');
        $this->addSql('DROP TABLE IF EXISTS booking');
        $this->addSql('DROP TABLE IF EXISTS bookable_resource');
        $this->addSql('DROP TABLE IF EXISTS work_order_photo');
        $this->addSql('DROP TABLE IF EXISTS work_order');
        $this->addSql('DROP TABLE IF EXISTS approval_action');
        $this->addSql('DROP TABLE IF EXISTS approval_step');
        $this->addSql('DROP TABLE IF EXISTS exception_request');
        $this->addSql('DROP TABLE IF EXISTS attendance_exception');
        $this->addSql('DROP TABLE IF EXISTS exception_rule');
        $this->addSql('DROP TABLE IF EXISTS attendance_record');
        $this->addSql('DROP TABLE IF EXISTS punch_event');
        $this->addSql('DROP TABLE IF EXISTS shift_schedule');
        $this->addSql('DROP TABLE IF EXISTS `user`');
    }
}
