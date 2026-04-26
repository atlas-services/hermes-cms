<?php

namespace App\EventSubscriber;

use FM\ElfinderBundle\Event\ElFinderPostExecutionEvent;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Filesystem\Filesystem ;
use Symfony\Component\Finder\Finder;

class ElfinderSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private Filesystem $filesystem,
        private ParameterBagInterface $parameterBag
    ) {}

    public function onElFinderPostExecutionEvent(ElFinderPostExecutionEvent $event): void
    {
        $request = $event->getRequest();
        $cmd = $request->query->get('cmd');

        if ($cmd !== 'ls') {
            return;
        }

        $projectDir = $this->parameterBag->get('kernel.project_dir');
        $dir = $projectDir . '/public/' . $this->parameterBag->get('hermes_path_content_image_post') . '/';

        $finder = new Finder();
        $files = $finder->files()->in($dir)->size('> 2M');

        foreach ($files as $file) {
            $realpath = $file->getRealPath();
            if (!$realpath) {
                continue;
            }

            $size = @getimagesize($realpath);
            if ($size === false) {
                continue;
            }

            [$width, $height] = $size;

            $newWidth = $width > 0 ? 2000 : $width;
            $ratio = $width > 0 ? $newWidth / $width : 1;
            $newHeight = (int) ($height * $ratio);

            $thumb = imagecreatetruecolor($newWidth, $newHeight);
            $source = @imagecreatefromjpeg($realpath) ?: @imagecreatefromstring(file_get_contents($realpath));

            if (!$source) {
                continue;
            }

            imagecopyresampled($thumb, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

            $tmp = dirname($realpath) . '/new_' . basename($realpath);
            imagejpeg($thumb, $tmp);

            $this->filesystem->rename($tmp, $realpath, true);
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ElFinderPostExecutionEvent::class => 'onElFinderPostExecutionEvent',
        ];
    }
}
