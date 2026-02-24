<?php

namespace App\Validator;

use App\Service\PasswordStrengthService;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class StrongPasswordValidator extends ConstraintValidator
{
    public function __construct(private PasswordStrengthService $passwordStrength) {}

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof StrongPassword) {
            throw new UnexpectedTypeException($constraint, StrongPassword::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        if ($this->passwordStrength->validate((string) $value) !== null) {
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }
}
