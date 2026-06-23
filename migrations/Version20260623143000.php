<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260623143000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create accounts and transfer ledger tables.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE accounts (id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', currency VARCHAR(3) NOT NULL, balance_cents BIGINT UNSIGNED NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX idx_accounts_currency (currency), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE transfers (id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', from_account_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', to_account_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', idempotency_key VARCHAR(128) NOT NULL, request_hash VARCHAR(64) NOT NULL, amount_cents BIGINT UNSIGNED NOT NULL, currency VARCHAR(3) NOT NULL, status VARCHAR(32) NOT NULL, created_at DATETIME NOT NULL, completed_at DATETIME DEFAULT NULL, UNIQUE INDEX uniq_transfers_idempotency_key (idempotency_key), INDEX idx_transfers_from_account (from_account_id), INDEX idx_transfers_to_account (to_account_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE transfers ADD CONSTRAINT FK_6B688F1F44F5D008 FOREIGN KEY (from_account_id) REFERENCES accounts (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE transfers ADD CONSTRAINT FK_6B688F1F1087005F FOREIGN KEY (to_account_id) REFERENCES accounts (id) ON DELETE RESTRICT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE transfers DROP FOREIGN KEY FK_6B688F1F44F5D008');
        $this->addSql('ALTER TABLE transfers DROP FOREIGN KEY FK_6B688F1F1087005F');
        $this->addSql('DROP TABLE transfers');
        $this->addSql('DROP TABLE accounts');
    }
}
