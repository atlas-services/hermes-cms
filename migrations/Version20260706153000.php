<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260706153000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Booking : prénom et nom (first_name, last_name) à la place de customer_name.';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('booking_reservation')) {
            return;
        }

        $table = $schema->getTable('booking_reservation');

        if (!$table->hasColumn('first_name')) {
            $this->addSql("ALTER TABLE booking_reservation ADD COLUMN first_name VARCHAR(80) NOT NULL DEFAULT ''");
        }

        if (!$table->hasColumn('last_name')) {
            $this->addSql("ALTER TABLE booking_reservation ADD COLUMN last_name VARCHAR(80) NOT NULL DEFAULT ''");
        }
    }

    public function postUp(Schema $schema): void
    {
        if (!$schema->hasTable('booking_reservation')) {
            return;
        }

        $table = $schema->getTable('booking_reservation');

        if (!$table->hasColumn('customer_name')) {
            return;
        }

        $rows = $this->connection->fetchAllAssociative('SELECT id, customer_name FROM booking_reservation');

        foreach ($rows as $row) {
            $name = trim((string) ($row['customer_name'] ?? ''));
            $first = '';
            $last = '';

            if ($name !== '') {
                $parts = preg_split('/\s+/', $name, 2) ?: [];
                $first = $parts[0] ?? '';
                $last = $parts[1] ?? '';
            }

            $this->connection->update('booking_reservation', [
                'first_name' => $first,
                'last_name' => $last,
            ], ['id' => $row['id']]);
        }

        $this->connection->executeStatement('ALTER TABLE booking_reservation DROP COLUMN customer_name');
    }

    public function down(Schema $schema): void
    {
        if (!$schema->hasTable('booking_reservation')) {
            return;
        }

        $table = $schema->getTable('booking_reservation');

        if (!$table->hasColumn('customer_name')) {
            $this->addSql('ALTER TABLE booking_reservation ADD COLUMN customer_name VARCHAR(120) NOT NULL DEFAULT \'\'');
        }

        if ($table->hasColumn('first_name') && $table->hasColumn('last_name')) {
            $rows = $this->connection->fetchAllAssociative('SELECT id, first_name, last_name FROM booking_reservation');

            foreach ($rows as $row) {
                $customerName = trim(trim((string) ($row['first_name'] ?? '')).' '.trim((string) ($row['last_name'] ?? '')));
                $this->connection->update('booking_reservation', [
                    'customer_name' => $customerName,
                ], ['id' => $row['id']]);
            }
        }

        if ($table->hasColumn('first_name')) {
            $this->addSql('ALTER TABLE booking_reservation DROP COLUMN first_name');
        }

        if ($table->hasColumn('last_name')) {
            $this->addSql('ALTER TABLE booking_reservation DROP COLUMN last_name');
        }
    }
}
