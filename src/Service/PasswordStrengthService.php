<?php

namespace App\Service;

class PasswordStrengthService
{
    private const MESSAGE = 'Le mot de passe doit contenir au moins 8 caractères et combiner au moins 3 des critères suivants : majuscule, minuscule, chiffre, caractère spécial.';

    public function validate(string $password): ?string
    {
        if (strlen($password) < 8) {
            return self::MESSAGE;
        }

        $criteria = 0;
        if (preg_match('/[A-Z]/', $password)) $criteria++;
        if (preg_match('/[a-z]/', $password)) $criteria++;
        if (preg_match('/[0-9]/', $password)) $criteria++;
        if (preg_match('/[^a-zA-Z0-9]/', $password)) $criteria++;

        if ($criteria < 3) {
            return self::MESSAGE;
        }

        return null;
    }
}
