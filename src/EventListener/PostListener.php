<?php

// src/EventListener/UserChangedNotifier.php
namespace App\EventListener;

use App\Entity\Post;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Events;

#[AsEntityListener(event: Events::postUpdate, method: 'postUpdate', entity: Post::class)]

class PostListener
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function postUpdate(Post $post, PostUpdateEventArgs $event): void
    {
        $content = $post->getContent() ?? '';

        if (str_contains($content, 'sandbox=""')) {
            $content = str_replace(
                'sandbox=""',
                'sandbox="allow-same-origin allow-scripts"',
                $content
            );

            $post->setContent($content);

            $this->em->persist($post);
            $this->em->flush();
        }
    }

}
