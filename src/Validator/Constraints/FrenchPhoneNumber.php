<?php

declare(strict_types=1);

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD)]
final class FrenchPhoneNumber extends Constraint
{
    public string $message = 'form.contact.telephone_invalid';

    public int $minDigits = 10;

    public int $maxDigits = 15;
}
