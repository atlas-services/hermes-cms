<?php

namespace App\Tests\Entity;

use App\Entity\Post;
use App\Entity\Section;
use App\Entity\Template;
use PHPUnit\Framework\TestCase;

class PostTest extends TestCase
{
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
