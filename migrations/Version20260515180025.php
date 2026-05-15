<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Déplace transparent, template_bgcolor, template_nb_col et template_image_filter de post vers section.
 */
final class Version20260515180025 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Move folio template options from post to section';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE section ADD COLUMN transparent BOOLEAN DEFAULT NULL');
        $this->addSql('ALTER TABLE section ADD COLUMN template_bgcolor VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE section ADD COLUMN template_nb_col INTEGER DEFAULT NULL');
        $this->addSql('ALTER TABLE section ADD COLUMN template_image_filter VARCHAR(255) DEFAULT NULL');

        $this->addSql(<<<'SQL'
            UPDATE section
            SET
                transparent = (
                    SELECT p.transparent FROM post p
                    WHERE p.section_id = section.id
                    ORDER BY p.position ASC, p.id ASC
                    LIMIT 1
                ),
                template_bgcolor = (
                    SELECT p.template_bgcolor FROM post p
                    WHERE p.section_id = section.id
                    ORDER BY p.position ASC, p.id ASC
                    LIMIT 1
                ),
                template_nb_col = (
                    SELECT p.template_nb_col FROM post p
                    WHERE p.section_id = section.id
                    ORDER BY p.position ASC, p.id ASC
                    LIMIT 1
                ),
                template_image_filter = (
                    SELECT p.template_image_filter FROM post p
                    WHERE p.section_id = section.id
                    ORDER BY p.position ASC, p.id ASC
                    LIMIT 1
                )
            WHERE EXISTS (SELECT 1 FROM post p WHERE p.section_id = section.id)
            SQL);

        $this->addSql('CREATE TEMPORARY TABLE __temp__post AS SELECT id, name, content, file_name, updated_at, active, start_published_at, end_published_at, position, locale, section_id FROM post');
        $this->addSql('DROP TABLE post');
        $this->addSql('CREATE TABLE post (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(25) NOT NULL, content CLOB DEFAULT NULL, file_name VARCHAR(255) DEFAULT NULL, updated_at DATETIME DEFAULT NULL, active BOOLEAN NOT NULL, start_published_at DATETIME DEFAULT NULL, end_published_at DATETIME DEFAULT NULL, position INTEGER DEFAULT NULL, locale VARCHAR(255) DEFAULT \'fr\', section_id INTEGER NOT NULL, CONSTRAINT FK_5A8A6C8DD823E37A FOREIGN KEY (section_id) REFERENCES section (id) ON UPDATE NO ACTION ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO post (id, name, content, file_name, updated_at, active, start_published_at, end_published_at, position, locale, section_id) SELECT id, name, content, file_name, updated_at, active, start_published_at, end_published_at, position, locale, section_id FROM __temp__post');
        $this->addSql('DROP TABLE __temp__post');
        $this->addSql('CREATE INDEX IDX_5A8A6C8DD823E37A ON post (section_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE post ADD COLUMN transparent BOOLEAN DEFAULT NULL');
        $this->addSql('ALTER TABLE post ADD COLUMN template_bgcolor VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE post ADD COLUMN template_nb_col INTEGER DEFAULT NULL');
        $this->addSql('ALTER TABLE post ADD COLUMN template_image_filter VARCHAR(255) DEFAULT NULL');

        $this->addSql(<<<'SQL'
            UPDATE post
            SET
                transparent = (SELECT s.transparent FROM section s WHERE s.id = post.section_id),
                template_bgcolor = (SELECT s.template_bgcolor FROM section s WHERE s.id = post.section_id),
                template_nb_col = (SELECT s.template_nb_col FROM section s WHERE s.id = post.section_id),
                template_image_filter = (SELECT s.template_image_filter FROM section s WHERE s.id = post.section_id)
            SQL);

        $this->addSql('CREATE TEMPORARY TABLE __temp__section AS SELECT template_width, template2_width, id, position, active, menu_id, template_id, template2_id FROM section');
        $this->addSql('DROP TABLE section');
        $this->addSql('CREATE TABLE section (template_width INTEGER DEFAULT NULL, template2_width INTEGER DEFAULT NULL, id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, position INTEGER DEFAULT NULL, active BOOLEAN NOT NULL, menu_id INTEGER NOT NULL, template_id INTEGER DEFAULT NULL, template2_id INTEGER DEFAULT NULL, CONSTRAINT FK_2D737AEFCCD7E912 FOREIGN KEY (menu_id) REFERENCES menu (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_2D737AEF5DA0FB8 FOREIGN KEY (template_id) REFERENCES template (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_2D737AEF16663CC FOREIGN KEY (template2_id) REFERENCES template (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO section (template_width, template2_width, id, position, active, menu_id, template_id, template2_id) SELECT template_width, template2_width, id, position, active, menu_id, template_id, template2_id FROM __temp__section');
        $this->addSql('DROP TABLE __temp__section');
        $this->addSql('CREATE INDEX IDX_2D737AEFCCD7E912 ON section (menu_id)');
        $this->addSql('CREATE INDEX IDX_2D737AEF5DA0FB8 ON section (template_id)');
        $this->addSql('CREATE INDEX IDX_2D737AEF16663CC ON section (template2_id)');
    }
}
