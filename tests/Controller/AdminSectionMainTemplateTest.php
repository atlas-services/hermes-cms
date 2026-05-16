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

    public function testUpdateSectionListeTemplateListeToListe(): void
    {
        $this->login();

        $section = $this->em->getRepository(Section::class)->findOneBy(['position' => 1]);
        $this->assertNotNull($section);

        $folio1 = $this->ensureListeTemplate('folio1', 'Folio 1');
        $folio2 = $this->ensureListeTemplate('folio2', 'Folio 2');

        $section->setTemplate($folio1);
        $this->em->flush();
        $sid = (int) $section->getId();
        $folio2Id = (int) $folio2->getId();

        $this->client->request(
            'POST',
            '/fr/admin/update-section-liste-template',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: (string) json_encode([
                'type' => 'section',
                'id' => $sid,
                'template_id' => $folio2Id,
            ], JSON_THROW_ON_ERROR)
        );

        $this->assertResponseIsSuccessful();
        $this->em->clear();
        $reloaded = $this->em->find(Section::class, $sid);
        $this->assertInstanceOf(Section::class, $reloaded);
        $this->assertSame('folio2', $reloaded->getTemplate()?->getCode());
    }

    public function testUpdateSectionListeTemplateRejectsNonListeSection(): void
    {
        $this->login();

        $section = $this->em->getRepository(Section::class)->findOneBy(['position' => 1]);
        $this->assertNotNull($section);
        $this->assertSame('libre', $section->getTemplate()?->getCode());

        $folio1 = $this->ensureListeTemplate('folio1', 'Folio 1');

        $this->client->request(
            'POST',
            '/fr/admin/update-section-liste-template',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: (string) json_encode([
                'type' => 'section',
                'id' => (int) $section->getId(),
                'template_id' => (int) $folio1->getId(),
            ], JSON_THROW_ON_ERROR)
        );

        $this->assertSame(400, $this->client->getResponse()->getStatusCode());
    }

    private function ensureListeTemplate(string $code, string $name): Template
    {
        $t = $this->em->getRepository(Template::class)->findOneBy(['code' => $code]);
        if ($t instanceof Template) {
            return $t;
        }

        $t = (new Template())
            ->setName($name)
            ->setCode($code)
            ->setType('liste')
            ->setSummary($name)
            ->setActive(true);
        $this->em->persist($t);
        $this->em->flush();

        return $t;
    }
}
