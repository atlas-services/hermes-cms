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
}
