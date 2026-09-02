<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260826190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Section : ajoute template_color (couleur de texte / accent de présentation).';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('section')) {
            return;
        }

        $table = $schema->getTable('section');

        if (!$table->hasColumn('template_color')) {
            $this->addSql('ALTER TABLE section ADD COLUMN template_color VARCHAR(255) DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        if (!$schema->hasTable('section')) {
            return;
        }

        $table = $schema->getTable('section');

        if ($table->hasColumn('template_color')) {
            $this->addSql('ALTER TABLE section DROP COLUMN template_color');
        }
    }
}
