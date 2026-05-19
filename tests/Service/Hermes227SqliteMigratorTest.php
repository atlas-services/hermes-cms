<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\Migration\Hermes227SqliteMigrator;
use PDO;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class Hermes227SqliteMigratorTest extends KernelTestCase
{
    public function testMigratesMinimalHermes227Database(): void
    {
        self::bootKernel();

        $from = sys_get_temp_dir() . '/hermes227_mig_from_' . uniqid('', true) . '.sqlite';
        $to = sys_get_temp_dir() . '/hermes227_mig_to_' . uniqid('', true) . '.sqlite';
        @unlink($from);
        @unlink($to);

        $p = new PDO('sqlite:' . $from);
        $p->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $p->exec('
CREATE TABLE user (id INTEGER PRIMARY KEY, email TEXT, roles TEXT, password TEXT);
INSERT INTO user VALUES (1, "a@b.fr", \'["ROLE_ADMIN"]\', "hash");
CREATE TABLE sheet (id INTEGER PRIMARY KEY, active INTEGER, locale TEXT, reference_name TEXT, code TEXT, name TEXT, slug TEXT, position INTEGER, summary TEXT, updated_at TEXT);
INSERT INTO sheet VALUES (1,1,"fr","ref1","code1","Feuille","feuille",0,"s",NULL);
CREATE TABLE template (id INTEGER PRIMARY KEY, active INTEGER, type TEXT, code TEXT, name TEXT, summary TEXT);
INSERT INTO template VALUES (1,1,"liste","folio1","Folio","sum");
CREATE TABLE menu (id INTEGER PRIMARY KEY, active INTEGER, locale TEXT, reference_name TEXT, code TEXT, name TEXT, slug TEXT, position INTEGER, sheet_id INTEGER, updated_at TEXT);
INSERT INTO menu VALUES (10,1,"fr","mref","mcode","feuille","menu-a",0,1,NULL);
CREATE TABLE section (id INTEGER PRIMARY KEY, active INTEGER, position INTEGER, menu INTEGER, template_id INTEGER, template_width INTEGER, template2_id INTEGER, template2_width INTEGER, transparent INTEGER, template_bgcolor TEXT, template_nb_col INTEGER, template_image_filter TEXT);
INSERT INTO section VALUES (100,1,0,10,1,10,NULL,NULL,NULL,NULL,4,NULL);
INSERT INTO section VALUES (101,1,0,NULL,1,10,NULL,NULL,NULL,NULL,4,NULL);
CREATE TABLE post (id INTEGER PRIMARY KEY, active INTEGER, start_published_at TEXT, end_published_at TEXT, position INTEGER, name TEXT, locale TEXT, section_id INTEGER, content TEXT, file_name TEXT, updated_at TEXT);
INSERT INTO post VALUES (1000,1,NULL,NULL,0,"P","fr",100,"<p>x</p>",NULL,NULL);
INSERT INTO post VALUES (1001,1,NULL,NULL,0,"abcdefghijklmnopqrstuvwxyz0123456789","fr",100,"<p>long name</p>",NULL,NULL);
INSERT INTO post VALUES (1002,1,NULL,NULL,0,"Orphelin","fr",101,"<p>skip</p>",NULL,NULL);
');

        $migrator = static::getContainer()->get(Hermes227SqliteMigrator::class);
        $migrator->migrate($from, $to, false, static function (): void {});

        $t = new PDO('sqlite:' . $to);
        $t->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        self::assertSame(1, (int) $t->query('SELECT COUNT(*) FROM menu')->fetchColumn());
        self::assertSame(1, (int) $t->query('SELECT COUNT(*) FROM menu WHERE parent_id IS NULL')->fetchColumn());
        $rootMenuId = (int) $t->query('SELECT id FROM menu LIMIT 1')->fetchColumn();
        self::assertSame($rootMenuId, (int) $t->query('SELECT menu_id FROM section WHERE id = 100')->fetchColumn());
        self::assertSame(1, (int) $t->query('SELECT COUNT(*) FROM section')->fetchColumn());
        self::assertSame('<p>x</p>', $t->query('SELECT content FROM post WHERE id = 1000')->fetchColumn());
        self::assertSame('abcdefghijklmnopqrstuvwxy', $t->query('SELECT name FROM post WHERE id = 1001')->fetchColumn());
        self::assertSame(2, (int) $t->query('SELECT COUNT(*) FROM post')->fetchColumn());

        @unlink($from);
        @unlink($to);
    }

    public function testRewritesLegacyUploadUrlsUsingDbStemsFromCommand(): void
    {
        self::bootKernel();

        $dir = sys_get_temp_dir() . '/hermes_mig_' . uniqid('', true);
        mkdir($dir);
        $outDir = $dir . '/out';
        mkdir($outDir);
        $from = $dir . '/jazzenville.sqlite';
        $to = $outDir . '/jazzenville.sqlite';

        $p = new PDO('sqlite:' . $from);
        $p->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $p->exec('
CREATE TABLE user (id INTEGER PRIMARY KEY, email TEXT, roles TEXT, password TEXT);
INSERT INTO user VALUES (1, "a@b.fr", \'["ROLE_ADMIN"]\', "hash");
CREATE TABLE sheet (id INTEGER PRIMARY KEY, active INTEGER, locale TEXT, reference_name TEXT, code TEXT, name TEXT, slug TEXT, position INTEGER, summary TEXT, updated_at TEXT);
INSERT INTO sheet VALUES (1,1,"fr","ref1","code1","Feuille","feuille",0,"s",NULL);
CREATE TABLE template (id INTEGER PRIMARY KEY, active INTEGER, type TEXT, code TEXT, name TEXT, summary TEXT);
INSERT INTO template VALUES (1,1,"liste","folio1","Folio","sum");
CREATE TABLE menu (id INTEGER PRIMARY KEY, active INTEGER, locale TEXT, reference_name TEXT, code TEXT, name TEXT, slug TEXT, position INTEGER, sheet_id INTEGER, updated_at TEXT);
INSERT INTO menu VALUES (10,1,"fr","mref","mcode","feuille","menu-a",0,1,NULL);
CREATE TABLE section (id INTEGER PRIMARY KEY, active INTEGER, position INTEGER, menu INTEGER, template_id INTEGER, template_width INTEGER, template2_id INTEGER, template2_width INTEGER, transparent INTEGER, template_bgcolor TEXT, template_nb_col INTEGER, template_image_filter TEXT);
INSERT INTO section VALUES (100,1,0,10,1,10,NULL,NULL,NULL,NULL,4,NULL);
CREATE TABLE post (id INTEGER PRIMARY KEY, active INTEGER, start_published_at TEXT, end_published_at TEXT, position INTEGER, name TEXT, locale TEXT, section_id INTEGER, content TEXT, file_name TEXT, updated_at TEXT);
');
        $legacyContent = '<img src="/jazzenville/uploads/content/photo.jpg">';
        $p->prepare('INSERT INTO post VALUES (1000,1,NULL,NULL,0,"P","fr",100,?,NULL,NULL)')->execute([$legacyContent]);

        $migrator = static::getContainer()->get(Hermes227SqliteMigrator::class);
        $migrator->migrate($from, $to, false, static function (): void {});

        $t = new PDO('sqlite:' . $to);
        $t->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $content = (string) $t->query('SELECT content FROM post WHERE id = 1000')->fetchColumn();
        self::assertStringContainsString('/uploads/jazzenville/content/photo.jpg', $content);
        self::assertStringNotContainsString('/jazzenville/uploads/', $content);

        @unlink($from);
        @unlink($to);
        @rmdir($outDir);
        @rmdir($dir);
    }

    public function testRewritesUploadUrlsWhenSourceAndTargetDbStemsDiffer(): void
    {
        self::bootKernel();

        $from = sys_get_temp_dir() . '/atlas.sqlite';
        $to = sys_get_temp_dir() . '/envolarchi.sqlite';
        @unlink($from);
        @unlink($to);

        $p = new PDO('sqlite:' . $from);
        $p->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $p->exec('
CREATE TABLE user (id INTEGER PRIMARY KEY, email TEXT, roles TEXT, password TEXT);
INSERT INTO user VALUES (1, "a@b.fr", \'["ROLE_ADMIN"]\', "hash");
CREATE TABLE sheet (id INTEGER PRIMARY KEY, active INTEGER, locale TEXT, reference_name TEXT, code TEXT, name TEXT, slug TEXT, position INTEGER, summary TEXT, updated_at TEXT);
INSERT INTO sheet VALUES (1,1,"fr","ref1","code1","Feuille","feuille",0,"s",NULL);
CREATE TABLE template (id INTEGER PRIMARY KEY, active INTEGER, type TEXT, code TEXT, name TEXT, summary TEXT);
INSERT INTO template VALUES (1,1,"liste","folio1","Folio","sum");
CREATE TABLE menu (id INTEGER PRIMARY KEY, active INTEGER, locale TEXT, reference_name TEXT, code TEXT, name TEXT, slug TEXT, position INTEGER, sheet_id INTEGER, updated_at TEXT);
INSERT INTO menu VALUES (10,1,"fr","mref","mcode","feuille","menu-a",0,1,NULL);
CREATE TABLE section (id INTEGER PRIMARY KEY, active INTEGER, position INTEGER, menu INTEGER, template_id INTEGER, template_width INTEGER, template2_id INTEGER, template2_width INTEGER, transparent INTEGER, template_bgcolor TEXT, template_nb_col INTEGER, template_image_filter TEXT);
INSERT INTO section VALUES (100,1,0,10,1,10,NULL,NULL,NULL,NULL,4,NULL);
CREATE TABLE post (id INTEGER PRIMARY KEY, active INTEGER, start_published_at TEXT, end_published_at TEXT, position INTEGER, name TEXT, locale TEXT, section_id INTEGER, content TEXT, file_name TEXT, updated_at TEXT);
');
        $legacyContent = '<img src="/atlas/uploads/content/photo.jpg">';
        $p->prepare('INSERT INTO post VALUES (1000,1,NULL,NULL,0,"P","fr",100,?,NULL,NULL)')->execute([$legacyContent]);

        $migrator = static::getContainer()->get(Hermes227SqliteMigrator::class);
        $migrator->migrate($from, $to, false, static function (): void {});

        $t = new PDO('sqlite:' . $to);
        $t->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $content = (string) $t->query('SELECT content FROM post WHERE id = 1000')->fetchColumn();
        self::assertStringContainsString('/uploads/envolarchi/content/photo.jpg', $content);
        self::assertStringNotContainsString('/atlas/uploads/', $content);

        @unlink($from);
        @unlink($to);
    }

    public function testMigratesConfigFromCompanionSqlite(): void
    {
        self::bootKernel();

        $stem = 'hermes227_cfg_' . uniqid('', true);
        $from = sys_get_temp_dir() . '/' . $stem . '.sqlite';
        $configDir = sys_get_temp_dir() . '/config';
        $configCompanion = $configDir . '/' . $stem . '.sqlite';
        $to = sys_get_temp_dir() . '/' . $stem . '_to.sqlite';
        @unlink($from);
        @unlink($configCompanion);
        @unlink($to);
        if (is_dir($configDir)) {
            @rmdir($configDir);
        }

        $p = new PDO('sqlite:' . $from);
        $p->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $p->exec('
CREATE TABLE user (id INTEGER PRIMARY KEY, email TEXT, roles TEXT, password TEXT);
INSERT INTO user VALUES (1, "a@b.fr", \'["ROLE_ADMIN"]\', "hash");
CREATE TABLE sheet (id INTEGER PRIMARY KEY, active INTEGER, locale TEXT, reference_name TEXT, code TEXT, name TEXT, slug TEXT, position INTEGER, summary TEXT, updated_at TEXT);
INSERT INTO sheet VALUES (1,1,"fr","ref1","code1","Feuille","feuille",0,"s",NULL);
CREATE TABLE template (id INTEGER PRIMARY KEY, active INTEGER, type TEXT, code TEXT, name TEXT, summary TEXT);
INSERT INTO template VALUES (1,1,"liste","folio1","Folio","sum");
CREATE TABLE menu (id INTEGER PRIMARY KEY, active INTEGER, locale TEXT, reference_name TEXT, code TEXT, name TEXT, slug TEXT, position INTEGER, sheet_id INTEGER, updated_at TEXT);
INSERT INTO menu VALUES (10,1,"fr","mref","mcode","feuille","menu-a",0,1,NULL);
CREATE TABLE section (id INTEGER PRIMARY KEY, active INTEGER, position INTEGER, menu INTEGER, template_id INTEGER, template_width INTEGER, template2_id INTEGER, template2_width INTEGER, transparent INTEGER, template_bgcolor TEXT, template_nb_col INTEGER, template_image_filter TEXT);
INSERT INTO section VALUES (100,1,0,10,1,10,NULL,NULL,NULL,NULL,4,NULL);
CREATE TABLE post (id INTEGER PRIMARY KEY, active INTEGER, start_published_at TEXT, end_published_at TEXT, position INTEGER, name TEXT, locale TEXT, section_id INTEGER, content TEXT, file_name TEXT, updated_at TEXT);
INSERT INTO post VALUES (1000,1,NULL,NULL,0,"P","fr",100,"<p>x</p>",NULL,NULL);
');

        if (!is_dir($configDir)) {
            mkdir($configDir, 0775, true);
        }

        $c = new PDO('sqlite:' . $configCompanion);
        $c->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $c->exec('
CREATE TABLE config (
  id INTEGER PRIMARY KEY,
  published_at TEXT,
  code TEXT,
  type TEXT,
  value TEXT,
  summary TEXT,
  file_name TEXT,
  updated_at TEXT,
  active INTEGER,
  start_published_at TEXT,
  end_published_at TEXT,
  position INTEGER
);
INSERT INTO config VALUES (1,"2020-01-01 00:00:00","site_title","general","Mon site","Titre",NULL,NULL,1,NULL,NULL,0);
');

        $migrator = static::getContainer()->get(Hermes227SqliteMigrator::class);
        $migrator->migrate($from, $to, false, static function (): void {});

        $t = new PDO('sqlite:' . $to);
        $t->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        self::assertSame(1, (int) $t->query('SELECT COUNT(*) FROM config')->fetchColumn());
        self::assertSame('Mon site', $t->query('SELECT value FROM config WHERE id = 1')->fetchColumn());
        self::assertNull($t->query('SELECT transparent FROM config WHERE id = 1')->fetchColumn());

        @unlink($from);
        @unlink($configCompanion);
        @unlink($to);
        if (is_dir($configDir)) {
            @rmdir($configDir);
        }
    }

    public function testKeepsSubmenuWhenNameDiffersFromSheet(): void
    {
        self::bootKernel();

        $from = sys_get_temp_dir() . '/hermes227_mig_from_' . uniqid('', true) . '.sqlite';
        $to = sys_get_temp_dir() . '/hermes227_mig_to_' . uniqid('', true) . '.sqlite';
        @unlink($from);
        @unlink($to);

        $p = new PDO('sqlite:' . $from);
        $p->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $p->exec('
CREATE TABLE user (id INTEGER PRIMARY KEY, email TEXT, roles TEXT, password TEXT);
INSERT INTO user VALUES (1, "a@b.fr", \'["ROLE_ADMIN"]\', "hash");
CREATE TABLE sheet (id INTEGER PRIMARY KEY, active INTEGER, locale TEXT, reference_name TEXT, code TEXT, name TEXT, slug TEXT, position INTEGER, summary TEXT, updated_at TEXT);
INSERT INTO sheet VALUES (1,1,"fr","ref1","code1","Feuille","feuille",0,"s",NULL);
CREATE TABLE template (id INTEGER PRIMARY KEY, active INTEGER, type TEXT, code TEXT, name TEXT, summary TEXT);
INSERT INTO template VALUES (1,1,"liste","folio1","Folio","sum");
CREATE TABLE menu (id INTEGER PRIMARY KEY, active INTEGER, locale TEXT, reference_name TEXT, code TEXT, name TEXT, slug TEXT, position INTEGER, sheet_id INTEGER, updated_at TEXT);
INSERT INTO menu VALUES (10,1,"fr","mref","mcode","Menu A","menu-a",0,1,NULL);
CREATE TABLE section (id INTEGER PRIMARY KEY, active INTEGER, position INTEGER, menu INTEGER, template_id INTEGER, template_width INTEGER, template2_id INTEGER, template2_width INTEGER, transparent INTEGER, template_bgcolor TEXT, template_nb_col INTEGER, template_image_filter TEXT);
INSERT INTO section VALUES (100,1,0,10,1,10,NULL,NULL,NULL,NULL,4,NULL);
CREATE TABLE post (id INTEGER PRIMARY KEY, active INTEGER, start_published_at TEXT, end_published_at TEXT, position INTEGER, name TEXT, locale TEXT, section_id INTEGER, content TEXT, file_name TEXT, updated_at TEXT);
INSERT INTO post VALUES (1000,1,NULL,NULL,0,"P","fr",100,"<p>x</p>",NULL,NULL);
');

        $migrator = static::getContainer()->get(Hermes227SqliteMigrator::class);
        $migrator->migrate($from, $to, false, static function (): void {});

        $t = new PDO('sqlite:' . $to);
        $t->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        self::assertSame(2, (int) $t->query('SELECT COUNT(*) FROM menu')->fetchColumn());

        @unlink($from);
        @unlink($to);
    }

    public function testMergesWhenSubmenuNameEqualsParentMenuCaseInsensitive(): void
    {
        self::bootKernel();

        $from = sys_get_temp_dir() . '/hermes227_mig_from_' . uniqid('', true) . '.sqlite';
        $to = sys_get_temp_dir() . '/hermes227_mig_to_' . uniqid('', true) . '.sqlite';
        @unlink($from);
        @unlink($to);

        $p = new PDO('sqlite:' . $from);
        $p->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $p->exec('
CREATE TABLE user (id INTEGER PRIMARY KEY, email TEXT, roles TEXT, password TEXT);
INSERT INTO user VALUES (1, "a@b.fr", \'["ROLE_ADMIN"]\', "hash");
CREATE TABLE sheet (id INTEGER PRIMARY KEY, active INTEGER, locale TEXT, reference_name TEXT, code TEXT, name TEXT, slug TEXT, position INTEGER, summary TEXT, updated_at TEXT);
INSERT INTO sheet VALUES (1,1,"fr","ref1","code1","Feuille","feuille",0,"s",NULL);
CREATE TABLE template (id INTEGER PRIMARY KEY, active INTEGER, type TEXT, code TEXT, name TEXT, summary TEXT);
INSERT INTO template VALUES (1,1,"liste","folio1","Folio","sum");
CREATE TABLE menu (id INTEGER PRIMARY KEY, active INTEGER, locale TEXT, reference_name TEXT, code TEXT, name TEXT, slug TEXT, position INTEGER, sheet_id INTEGER, parent_id INTEGER, updated_at TEXT);
INSERT INTO menu VALUES (10,1,"fr","mref","mcode","Rubrique","rubrique",0,1,NULL,NULL);
INSERT INTO menu VALUES (11,1,"fr","mref2","mcode2","RUBRIQUE","rubrique-2",0,1,10,NULL);
CREATE TABLE section (id INTEGER PRIMARY KEY, active INTEGER, position INTEGER, menu INTEGER, template_id INTEGER, template_width INTEGER, template2_id INTEGER, template2_width INTEGER, transparent INTEGER, template_bgcolor TEXT, template_nb_col INTEGER, template_image_filter TEXT);
INSERT INTO section VALUES (100,1,0,11,1,10,NULL,NULL,NULL,NULL,4,NULL);
CREATE TABLE post (id INTEGER PRIMARY KEY, active INTEGER, start_published_at TEXT, end_published_at TEXT, position INTEGER, name TEXT, locale TEXT, section_id INTEGER, content TEXT, file_name TEXT, updated_at TEXT);
INSERT INTO post VALUES (1000,1,NULL,NULL,0,"P","fr",100,"<p>x</p>",NULL,NULL);
');

        $migrator = static::getContainer()->get(Hermes227SqliteMigrator::class);
        $migrator->migrate($from, $to, false, static function (): void {});

        $t = new PDO('sqlite:' . $to);
        $t->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        self::assertSame(2, (int) $t->query('SELECT COUNT(*) FROM menu')->fetchColumn());
        $childMenuId = (int) $t->query('SELECT id FROM menu WHERE parent_id IS NOT NULL')->fetchColumn();
        self::assertGreaterThan(0, $childMenuId);
        self::assertSame($childMenuId, (int) $t->query('SELECT menu_id FROM section WHERE id = 100')->fetchColumn());

        @unlink($from);
        @unlink($to);
    }
}
