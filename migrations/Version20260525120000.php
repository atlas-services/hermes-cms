<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260525120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Sections footer : menu_id nullable et détachement des sections footer_template.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE section SET menu_id = NULL WHERE template_id IN (SELECT id FROM template WHERE code = 'footer_template')");

        $table = $schema->getTable('section');
        foreach ($table->getForeignKeys() as $foreignKey) {
            if ($foreignKey->getLocalColumns() === ['menu_id']) {
                $table->removeForeignKey($foreignKey->getName());
            }
        }
        $table->getColumn('menu_id')->setNotnull(false);
        $table->addForeignKeyConstraint(
            'menu',
            ['menu_id'],
            ['id'],
            ['onDelete' => 'CASCADE'],
        );
    }

    public function down(Schema $schema): void
    {
        $table = $schema->getTable('section');
        foreach ($table->getForeignKeys() as $foreignKey) {
            if ($foreignKey->getLocalColumns() === ['menu_id']) {
                $table->removeForeignKey($foreignKey->getName());
            }
        }
        $table->getColumn('menu_id')->setNotnull(true);
        $table->addForeignKeyConstraint(
            'menu',
            ['menu_id'],
            ['id'],
            ['onDelete' => 'CASCADE'],
        );
    }
}
