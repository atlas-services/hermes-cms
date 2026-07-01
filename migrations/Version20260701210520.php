<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260701210520 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute template_color sur section (couleur de texte par section).';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE section ADD COLUMN template_color VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__section AS SELECT template_width, template2_width, transparent, template_bgcolor, template_nb_col, template_image_filter, template_gsap_image_effect, locale, reference_name, id, position, active, menu_id, template_id, template2_id FROM section');
        $this->addSql('DROP TABLE section');
        $this->addSql('CREATE TABLE section (template_width INTEGER DEFAULT NULL, template2_width INTEGER DEFAULT NULL, transparent BOOLEAN DEFAULT NULL, template_bgcolor VARCHAR(255) DEFAULT NULL, template_nb_col INTEGER DEFAULT NULL, template_image_filter VARCHAR(255) DEFAULT NULL, template_gsap_image_effect VARCHAR(32) DEFAULT NULL, locale VARCHAR(10) DEFAULT NULL, reference_name VARCHAR(100) DEFAULT NULL, id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, position INTEGER DEFAULT NULL, active BOOLEAN NOT NULL, menu_id INTEGER DEFAULT NULL, template_id INTEGER DEFAULT NULL, template2_id INTEGER DEFAULT NULL, CONSTRAINT FK_2D737AEFCCD7E912 FOREIGN KEY (menu_id) REFERENCES menu (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_2D737AEF5DA0FB8 FOREIGN KEY (template_id) REFERENCES template (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_2D737AEF16663CC FOREIGN KEY (template2_id) REFERENCES template (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO section (template_width, template2_width, transparent, template_bgcolor, template_nb_col, template_image_filter, template_gsap_image_effect, locale, reference_name, id, position, active, menu_id, template_id, template2_id) SELECT template_width, template2_width, transparent, template_bgcolor, template_nb_col, template_image_filter, template_gsap_image_effect, locale, reference_name, id, position, active, menu_id, template_id, template2_id FROM __temp__section');
        $this->addSql('DROP TABLE __temp__section');
        $this->addSql('CREATE INDEX IDX_2D737AEFCCD7E912 ON section (menu_id)');
        $this->addSql('CREATE INDEX IDX_2D737AEF5DA0FB8 ON section (template_id)');
        $this->addSql('CREATE INDEX IDX_2D737AEF16663CC ON section (template2_id)');
    }
}
