<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\AdminMediaStorage;
use PHPUnit\Framework\TestCase;

final class AdminMediaStorageSanitizeTest extends TestCase
{
    private AdminMediaStorage $storage;

    protected function setUp(): void
    {
        $this->storage = new AdminMediaStorage(
            sys_get_temp_dir() . '/hermes-media-test',
            'uploads/content',
        );
    }

    public function testSanitizeFileNameReplacesSpacesWithUnderscores(): void
    {
        self::assertSame('ma_photo.jpg', $this->storage->sanitizeFileName('ma photo.jpg'));
        self::assertSame('a_b_c.png', $this->storage->sanitizeFileName('a b c.png'));
    }

    public function testSanitizeFileNameStripsDirectoryComponents(): void
    {
        self::assertSame('image.jpg', $this->storage->sanitizeFileName('../subdir/image.jpg'));
    }

    public function testSanitizeFileNameReplacesNonBreakingSpace(): void
    {
        self::assertSame('photo_test.jpg', $this->storage->sanitizeFileName("photo\u{00A0}test.jpg"));
    }
}
