<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class StrongPassword extends Constraint
{
    public string $message = 'Le mot de passe doit contenir au moins 8 caractères et combiner au moins 3 des critères suivants : majuscule, minuscule, chiffre, caractère spécial.';
}
