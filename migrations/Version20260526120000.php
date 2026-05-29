<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260526120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'reference_name sur menu ; locale et reference_name sur section (footer par langue).';
    }

    public function up(Schema $schema): void
    {
        $menu = $schema->getTable('menu');
        if (!$menu->hasColumn('reference_name')) {
            $this->addSql("ALTER TABLE menu ADD COLUMN reference_name VARCHAR(100) DEFAULT 'ref' NOT NULL");
        }

        $section = $schema->getTable('section');
        if (!$section->hasColumn('locale')) {
            $this->addSql('ALTER TABLE section ADD COLUMN locale VARCHAR(10) DEFAULT NULL');
        }
        if (!$section->hasColumn('reference_name')) {
            $this->addSql('ALTER TABLE section ADD COLUMN reference_name VARCHAR(100) DEFAULT NULL');
        }

        $this->addSql("UPDATE menu SET reference_name = LOWER(TRIM(COALESCE(NULLIF(code, ''), slug, name, 'menu-' || id))) WHERE reference_name = 'ref' OR reference_name = ''");
        $this->addSql('UPDATE section SET locale = (SELECT m.locale FROM menu m WHERE m.id = section.menu_id) WHERE section.menu_id IS NOT NULL');
        $this->addSql("UPDATE section SET locale = COALESCE((SELECT p.locale FROM post p WHERE p.section_id = section.id ORDER BY p.position ASC LIMIT 1), 'fr') WHERE section.menu_id IS NULL AND section.locale IS NULL");
        $this->addSql("UPDATE section SET reference_name = 'footer-' || section.id WHERE section.menu_id IS NULL AND (reference_name IS NULL OR reference_name = '')");
        $this->addSql('CREATE UNIQUE INDEX uniq_menu_locale_reference ON menu (locale, reference_name)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_menu_locale_reference');

        $menu = $schema->getTable('menu');
        if ($menu->hasColumn('reference_name')) {
            $this->addSql('ALTER TABLE menu DROP COLUMN reference_name');
        }

        $section = $schema->getTable('section');
        if ($section->hasColumn('reference_name')) {
            $this->addSql('ALTER TABLE section DROP COLUMN reference_name');
        }
        if ($section->hasColumn('locale')) {
            $this->addSql('ALTER TABLE section DROP COLUMN locale');
        }
    }
}
