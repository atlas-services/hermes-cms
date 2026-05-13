<?php

declare(strict_types=1);

namespace App\Tests\Twig;

use App\Entity\Menu;
use App\Entity\Post;
use App\Entity\Section;
use App\Entity\Template;
use App\Twig\HermesExtension;
use PHPUnit\Framework\TestCase;

final class HermesExtensionTest extends TestCase
{
    public function testColImgsReturnsEmptyForSectionWithoutPosts(): void
    {
        $ext = new HermesExtension();
        $section = new Section();
        $this->assertSame([], $ext->colImgs($section));
    }

    public function testColImgsChunksPosts(): void
    {
        $ext = new HermesExtension();
        $menu = new Menu();
        $menu->setName('M');
        $template = new Template();
        $template->setName('T');
        $template->setCode('folio1');
        $template->setType('liste');
        $template->setSummary('s');

        $section = new Section();
        $section->setMenu($menu);
        $section->setTemplate($template);

        for ($i = 1; $i <= 5; ++$i) {
            $p = new Post();
            $p->setName('P'.$i);
            $p->setTemplateNbCol(3);
            $section->addPost($p);
        }

        $groups = $ext->colImgs($section);
        $this->assertNotEmpty($groups);
        $flat = array_merge(...$groups);
        $this->assertCount(5, $flat);
    }
}
