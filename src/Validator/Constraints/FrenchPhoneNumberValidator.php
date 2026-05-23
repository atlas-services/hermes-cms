<?php

declare(strict_types=1);

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

final class FrenchPhoneNumberValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof FrenchPhoneNumber) {
            throw new UnexpectedTypeException($constraint, FrenchPhoneNumber::class);
        }

        if ($value === null || $value === '') {
            return;
        }

        if (!\is_string($value)) {
            throw new UnexpectedValueException($value, 'string');
        }

        $digits = preg_replace('/\D+/', '', $value) ?? '';
        if ($digits === '') {
            $this->context->buildViolation($constraint->message)
                ->setTranslationDomain('messages')
                ->addViolation();

            return;
        }

        if (str_starts_with($digits, '33') && strlen($digits) >= 11) {
            $digits = '0'.substr($digits, 2);
        }

        $length = strlen($digits);
        if ($length < $constraint->minDigits || $length > $constraint->maxDigits) {
            $this->context->buildViolation($constraint->message)
                ->setTranslationDomain('messages')
                ->addViolation();
        }
    }
}
