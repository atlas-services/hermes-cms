<?php

declare(strict_types=1);

namespace App\Tests\Service\ContentTransfer;

use App\DataFixtures\PostFixtures;
use App\Service\ContentTransfer\ContentTransferTargetProvider;
use App\Tests\Base\BaseKernelTestCase;

final class ContentTransferTargetProviderTest extends BaseKernelTestCase
{
    private ContentTransferTargetProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->provider = static::getContainer()->get(ContentTransferTargetProvider::class);
    }

    protected function loadFixtures(): array
    {
        return [new PostFixtures()];
    }

    public function testGetPagesPayloadIncludesFixturePages(): void
    {
        $payload = $this->provider->getPagesPayload();

        self::assertNotEmpty($payload);

        $frPages = array_values(array_filter(
            $payload,
            static fn (array $row): bool => $row['locale'] === 'fr',
        ));
        self::assertNotEmpty($frPages);

        $labels = array_column($frPages, 'label');
        self::assertTrue(
            (bool) array_filter(
                $labels,
                static fn (string $label): bool => str_contains($label, 'Posts Menu') || str_contains($label, 'POSTS'),
            ),
            'Expected fixture page in FR payload, got: ' . implode(', ', $labels),
        );
    }
}
