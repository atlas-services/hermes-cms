<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Enum\FormTemplateKind;
use App\Service\FormPresentationResolver;
use PHPUnit\Framework\TestCase;

final class FormPresentationResolverTest extends TestCase
{
    public function testResolvesContactFieldColumnWidths(): void
    {
        $resolver = new FormPresentationResolver();

        $presentation = $resolver->resolve(FormTemplateKind::Contact, [
            'contact_width_firstname' => '6',
            'contact_width_lastname' => '6',
            'contact_width_email' => '4',
            'contact_width_telephone' => '8',
            'contact_width_message' => '12',
            'contact_rounded_input' => '5',
            'contact_rounded_message' => '4',
        ]);

        self::assertSame('6', $presentation['width_firstname']);
        self::assertSame('6', $presentation['width_lastname']);
        self::assertSame('4', $presentation['width_email']);
        self::assertSame('8', $presentation['width_telephone']);
        self::assertSame('12', $presentation['width_message']);
        self::assertSame('5', $presentation['rounded_input']);
        self::assertSame('4', $presentation['rounded_message']);
    }

    public function testClampsInvalidContactColumnWidths(): void
    {
        $resolver = new FormPresentationResolver();

        $presentation = $resolver->resolve(FormTemplateKind::Contact, [
            'contact_width_firstname' => '0',
            'contact_width_message' => '99',
        ]);

        self::assertSame('1', $presentation['width_firstname']);
        self::assertSame('12', $presentation['width_message']);
    }
}
