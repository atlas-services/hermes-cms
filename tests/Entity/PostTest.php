<?php

namespace App\Tests\Entity;

use App\Entity\Post;
use App\Entity\Section;
use App\Entity\Template;
use PHPUnit\Framework\TestCase;

class PostTest extends TestCase
{
    public function testDefaultTemplateWidthAndTransparency(): void
    {
        $post = new Post();

        $this->assertEquals(10, $post->getTemplateWidth());
        $this->assertFalse($post->isTransparent());
        $this->assertEquals('transparent', $post->getTemplateBgcolor());
    }

    public function testTemplateBgcolorWhenTransparent(): void
    {
        $post = new Post();
        $post->setTransparent(true);

        $this->assertTrue($post->isTransparent());
        $this->assertEquals('transparent', $post->getTemplateBgcolor());
    }

    public function testSectionAssociationIsBidirectional(): void
    {
        $post = new Post();
        $section = new Section();
        $template = new Template();
        $template->setName('Test');
        $template->setCode('test');
        $template->setType('test');
        $template->setSummary('Test template');
        $section->setTemplate($template);

        $post->setSection($section);

        $this->assertSame($section, $post->getSection());
    }
}
