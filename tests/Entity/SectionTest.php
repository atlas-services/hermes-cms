<?php

namespace App\Tests\Entity;

use App\Entity\Post;
use App\Entity\Section;
use App\Entity\Menu;
use App\Entity\Template;
use PHPUnit\Framework\TestCase;

class SectionTest extends TestCase
{
    public function testAddAndRemovePostKeepsBidirectionalRelation(): void
    {
        $section = new Section();
        $post = new Post();

        $section->addPost($post);

        $this->assertCount(1, $section->getPosts());
        $this->assertSame($section, $post->getSection());

        $section->removePost($post);

        $this->assertCount(0, $section->getPosts());
        $this->assertNull($post->getSection());
    }

    public function testSetMenuAndTemplate(): void
    {
        $section = new Section();
        $menu = new Menu();
        $template = new Template();

        $template->setName('Test');
        $template->setCode('test');
        $template->setType('test');
        $template->setSummary('Test template');

        $section->setMenu($menu);
        $section->setTemplate($template);

        $this->assertSame($menu, $section->getMenu());
        $this->assertSame($template, $section->getTemplate());
    }

    public function testTemplateWidth(): void
    {
        $section = new Section();

        $this->assertNull($section->getTemplateWidth());

        $section->setTemplateWidth(8);

        $this->assertSame(8, $section->getTemplateWidth());
    }

    public function testTemplate2(): void
    {
        $section = new Section();
        $t2 = new Template();
        $t2->setName('Modale');
        $t2->setCode('modale1');
        $t2->setType('modale');
        $t2->setSummary('Modale slide');

        $section->setTemplate2($t2);
        $section->setTemplate2Width(6);

        $this->assertSame($t2, $section->getTemplate2());
        $this->assertSame(6, $section->getTemplate2Width());
    }

    public function testDefaultTransparency(): void
    {
        $section = new Section();

        $this->assertFalse($section->isTransparent());
        $this->assertSame('transparent', $section->getTemplateBgcolor());
    }

    public function testTemplateBgcolorWhenTransparent(): void
    {
        $section = new Section();
        $section->setTemplateBgcolor('#ff0000');
        $section->setTransparent(true);

        $this->assertTrue($section->isTransparent());
        $this->assertSame('transparent', $section->getTemplateBgcolor());
        $this->assertSame('#ff0000', $section->getRawTemplateBgcolor());

        $section->setTransparent(false);

        $this->assertFalse($section->isTransparent());
        $this->assertSame('#ff0000', $section->getTemplateBgcolor());
    }

    public function testSetTemplateBgcolorUnchecksTransparent(): void
    {
        $section = new Section();
        $section->setTransparent(true);
        $section->setTemplateBgcolor('#00ff00');

        $this->assertFalse($section->isTransparent());
        $this->assertSame('#00ff00', $section->getTemplateBgcolor());
    }

    public function testTemplateNbColAndImageFilterDefaults(): void
    {
        $section = new Section();

        $this->assertSame(4, $section->getTemplateNbCol());
        $this->assertSame('bd_154', $section->getTemplateImageFilter());
    }
}
