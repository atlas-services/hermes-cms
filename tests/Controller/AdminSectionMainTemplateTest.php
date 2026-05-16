<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\DataFixtures\PostFixtures;
use App\Entity\Section;
use App\Entity\Template;
use App\Tests\Base\BaseControllerTest;
use Doctrine\ORM\EntityManagerInterface;

final class AdminSectionMainTemplateTest extends BaseControllerTest
{
    protected function loadFixtures(): void
    {
        parent::loadFixtures();

        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);
        (new PostFixtures())->load($em);
    }

    public function testUpdateSectionMainTemplateSameType(): void
    {
        $this->login();

        $section = $this->em->getRepository(Section::class)->findOneBy(['position' => 1]);
        $this->assertNotNull($section);
        $current = $section->getTemplate();
        $this->assertNotNull($current);
        $this->assertSame('libre', $current->getCode());

        $alt = (new Template())
            ->setName('Libre alt test')
            ->setCode('libre_alt_test')
            ->setType('libre')
            ->setSummary('Alt')
            ->setActive(true);
        $this->em->persist($alt);
        $this->em->flush();
        $altId = (int) $alt->getId();

        $this->client->request(
            'POST',
            '/fr/admin/update-section-main-template',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: (string) json_encode([
                'type' => 'section',
                'id' => $section->getId(),
                'template_id' => $altId,
            ], JSON_THROW_ON_ERROR)
        );

        $this->assertResponseIsSuccessful();
        $this->em->clear();
        $reloaded = $this->em->find(Section::class, $section->getId());
        $this->assertInstanceOf(Section::class, $reloaded);
        $this->assertSame('libre_alt_test', $reloaded->getTemplate()?->getCode());
    }

    public function testUpdateSectionMainTemplateTypeMismatch(): void
    {
        $this->login();

        $section = $this->em->getRepository(Section::class)->findOneBy(['position' => 1]);
        $this->assertNotNull($section);

        $liste = (new Template())
            ->setName('Liste test')
            ->setCode('folio_test_x')
            ->setType('liste')
            ->setSummary('T')
            ->setActive(true);
        $this->em->persist($liste);
        $this->em->flush();
        $listeId = (int) $liste->getId();

        $this->client->request(
            'POST',
            '/fr/admin/update-section-main-template',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: (string) json_encode([
                'type' => 'section',
                'id' => $section->getId(),
                'template_id' => $listeId,
            ], JSON_THROW_ON_ERROR)
        );

        $this->assertSame(400, $this->client->getResponse()->getStatusCode());
    }
}
