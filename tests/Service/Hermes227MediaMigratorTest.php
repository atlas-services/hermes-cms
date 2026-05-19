<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\Migration\Hermes227MediaMigrator;
use PDO;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class Hermes227MediaMigratorTest extends KernelTestCase
{
    public function testRestructuresPostImagesFromHermes227Layout(): void
    {
        self::bootKernel();

        $tmp = static::getContainer()->getParameter('kernel.project_dir') . '/var/test_media_' . uniqid('', true);
        $from = $tmp . '/from';
        $to = $tmp . '/to';
        $db = $tmp . '/db.sqlite';
        mkdir($tmp, 0775, true);

        mkdir($from . '/entity/section10/mon-menu', 0775, true);
        mkdir($from . '/entity/Config', 0775, true);
        mkdir($from . '/content/sub', 0775, true);
        file_put_contents($from . '/entity/section10/mon-menu/photo.jpg', 'jpg');
        file_put_contents($from . '/entity/Config/logo.png', 'png');
        file_put_contents($from . '/content/sub/bulk.jpg', 'bulk');

        $p = new PDO('sqlite:' . $db);
        $p->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $p->exec('
CREATE TABLE menu (id INTEGER PRIMARY KEY, code TEXT);
CREATE TABLE section (id INTEGER PRIMARY KEY, menu_id INTEGER);
CREATE TABLE post (id INTEGER PRIMARY KEY, section_id INTEGER, file_name TEXT);
INSERT INTO menu VALUES (6, "mon-menu");
INSERT INTO section VALUES (10, 6);
INSERT INTO post VALUES (1, 10, "photo.jpg");
');

        $migrator = static::getContainer()->get(Hermes227MediaMigrator::class);
        $stats = $migrator->migrate($db, $from, $to, false, true, static function (): void {});

        self::assertSame(1, $stats['postsCopied']);
        self::assertGreaterThanOrEqual(1, $stats['configFiles']);
        self::assertFileExists($to . '/entity/menu6/mon-menu/section10/post/photo.jpg');
        self::assertFileExists($to . '/entity/Config/logo.png');
        self::assertFileExists($to . '/content/sub/bulk.jpg');

        $this->removeTree($tmp);
    }

    private function removeTree(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($it as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($dir);
    }
}
