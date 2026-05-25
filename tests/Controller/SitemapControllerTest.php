<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class SitemapControllerTest extends WebTestCase
{
    public function testSitemapXmlIsAccessible(): void
    {
        $client = static::createClient();
        $client->request('GET', '/fr/sitemap.xml');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'text/xml; charset=UTF-8');
        $content = $client->getResponse()->getContent() ?: '';
        self::assertStringContainsString('<urlset', $content);
        self::assertStringContainsString('<loc>', $content);
    }

    public function testSitemapRedirect(): void
    {
        $client = static::createClient();
        $client->request('GET', '/sitemap.xml');

        $defaultLocale = static::getContainer()->getParameter('app.default_locale');
        self::assertResponseRedirects('/'.$defaultLocale.'/sitemap.xml', Response::HTTP_MOVED_PERMANENTLY);
    }

    public function testRobotsTxtReferencesSitemap(): void
    {
        $client = static::createClient();
        $client->request('GET', '/robots.txt');

        self::assertResponseIsSuccessful();
        $content = $client->getResponse()->getContent() ?: '';
        self::assertStringContainsString('Sitemap:', $content);
        self::assertStringContainsString('/sitemap.xml', $content);
    }
}
