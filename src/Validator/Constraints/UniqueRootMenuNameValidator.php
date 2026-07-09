<?php

declare(strict_types=1);

namespace App\Validator\Constraints;

use App\Entity\Menu;
use App\Repository\MenuRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class UniqueRootMenuNameValidator extends ConstraintValidator
{
    public function __construct(
        private readonly MenuRepository $menuRepository,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof UniqueRootMenuName) {
            throw new UnexpectedTypeException($constraint, UniqueRootMenuName::class);
        }

        if (!$value instanceof Menu || !$value->isRoot()) {
            return;
        }

        $name = $value->getName();
        if ($name === null || trim($name) === '') {
            return;
        }

        $locale = $value->getLocale() ?? 'fr';
        $existing = $this->menuRepository->findRootByLocaleAndName(
            $locale,
            $name,
            $value->getId(),
        );

        if ($existing !== null) {
            $this->context->buildViolation($constraint->message)
                ->atPath('name')
                ->addViolation();
        }
    }
}
