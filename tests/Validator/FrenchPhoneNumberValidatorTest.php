<?php

declare(strict_types=1);

namespace App\Tests\Validator;

use App\Validator\Constraints\FrenchPhoneNumber;
use App\Validator\Constraints\FrenchPhoneNumberValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/** @extends ConstraintValidatorTestCase<FrenchPhoneNumberValidator> */
final class FrenchPhoneNumberValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): FrenchPhoneNumberValidator
    {
        return new FrenchPhoneNumberValidator();
    }

    #[DataProvider('validNumbersProvider')]
    public function testValidNumbers(string $number): void
    {
        $this->validator->validate($number, new FrenchPhoneNumber());
        $this->assertNoViolation();
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function validNumbersProvider(): iterable
    {
        yield 'compact' => ['0612345678'];
        yield 'spaces' => ['06 12 34 56 78'];
        yield 'slashes' => ['06/12/34/56/78'];
        yield 'parentheses' => ['(06) 12 34 56 78'];
        yield 'international' => ['+33 6 12 34 56 78'];
    }

    #[DataProvider('invalidNumbersProvider')]
    public function testInvalidNumbers(string $number): void
    {
        $this->validator->validate($number, new FrenchPhoneNumber());
        $this->buildViolation('form.contact.telephone_invalid')
            ->assertRaised();
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function invalidNumbersProvider(): iterable
    {
        yield 'too short' => ['0612345'];
        yield 'letters' => ['06abc567890'];
    }
}
