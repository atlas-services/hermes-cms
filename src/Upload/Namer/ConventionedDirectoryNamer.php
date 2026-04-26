<?php
/**
 * Created by PhpStorm.
 * User: atlas
 * Date: 09/03/20
 * Time: 15:46
 */

namespace App\Upload\Namer;

use App\Entity\Config ;
use App\Entity\Post;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Vich\UploaderBundle\Mapping\PropertyMapping;
use Vich\UploaderBundle\Naming\DirectoryNamerInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @implements DirectoryNamerInterface<object>
 */
class ConventionedDirectoryNamer implements DirectoryNamerInterface
{

    public function __construct(protected LoggerInterface $directoryNamerLogger,protected Filesystem $filesystem, protected ParameterBagInterface $params)
    {
    }

    public function directoryName($object, PropertyMapping $mapping): string
    {
        $notification = 'Upload image';

        try {
            $className = (new \ReflectionClass($object))->getShortName();

            // -------------------------
            // MENU
            // -------------------------
            if ($object instanceof \App\Entity\Menu) {
                return $object->getCode() . '/';
            }

            // -------------------------
            // SECTION
            // -------------------------
            if ($object instanceof \App\Entity\Section) {
                if ($object->getMenu()) {
                    return $object->getMenu()->getCode() . '/section/';
                }

                return 'section/';
            }

            // -------------------------
            // POST (via SECTION → MENU)
            // -------------------------
            if ($object instanceof Post) {
                $section = $object->getSection();

                if ($section && $section->getMenu()) {
                    $menu = $section->getMenu();

                    return sprintf(
                        'menu%d/%s/section%d/post/',
                        $menu->getId(),
                        $menu->getCode(),
                        $section->getId()
                    );
                }

                return 'post/';
            }

            // -------------------------
            // CONFIG
            // -------------------------
            if ($object instanceof Config) {
                return $className . '/';
            }

            // -------------------------
            // FALLBACK
            // -------------------------
            return $className . '/' . $object->getId() . '/';

        } catch (\Exception $e) {
            $this->directoryNamerLogger->alert($notification, [
                'statut' => 'ko',
                'exception' => $e->getMessage(),
            ]);

            return 'fallback/';
        }
    }
}
