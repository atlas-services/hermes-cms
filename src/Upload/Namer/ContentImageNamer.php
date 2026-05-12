<?php

declare(strict_types=1);

namespace App\Upload\Namer;

use Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Vich\UploaderBundle\Mapping\PropertyMapping;
use Vich\UploaderBundle\Naming\ConfigurableInterface;
use Vich\UploaderBundle\Naming\NamerInterface;
use Vich\UploaderBundle\Naming\Polyfill\FileExtensionTrait;
use Vich\UploaderBundle\Util\Transliterator;

/**
 * Noms de fichiers pour le mapping Vich « content_images » :
 * - entités avec une propriété {@see CodeTrait::$code} non vide : nom basé sur le code (comportement historique) ;
 * - sinon (ex. {@see Post}) : nom à partir du nom du post + suffixe unique, ou identifiant unique.
 */
final class ContentImageNamer implements ConfigurableInterface, NamerInterface
{
    use FileExtensionTrait;

    public function __construct(
        private readonly Transliterator $transliterator,
    ) {
    }

    public function configure(array $options): void
    {
    }

    public function name(object $object, PropertyMapping $mapping): string
    {
        $file = $mapping->getFile($object);
        $accessor = PropertyAccess::createPropertyAccessor();

        try {
            $code = $accessor->getValue($object, 'code');
            if (\is_string($code) && '' !== $code) {
                $name = $this->transliterator->transliterate($code);
                if ($extension = $this->getExtensionWithOption($file, false)) {
                    return \sprintf('%s.%s', $name, $extension);
                }

                return $name;
            }
        } catch (NoSuchPropertyException) {
        }

        try {
            $label = $accessor->getValue($object, 'name');
            if (\is_string($label) && '' !== $label) {
                $base = $this->transliterator->transliterate($label);
                $base = (string) preg_replace('/[^a-zA-Z0-9_-]+/', '-', $base);
                $base = trim($base, '-');
                if ('' === $base) {
                    $base = 'post';
                }
                $base .= str_replace('.', '', uniqid('-', true));
                if ($extension = $this->getExtensionWithOption($file, false)) {
                    return \sprintf('%s.%s', $base, $extension);
                }

                return $base;
            }
        } catch (NoSuchPropertyException) {
        }

        $fallback = 'img'.str_replace('.', '', uniqid('-', true));
        if ($extension = $this->getExtensionWithOption($file, false)) {
            return \sprintf('%s.%s', $fallback, $extension);
        }

        return $fallback;
    }
}
