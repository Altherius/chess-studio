import React from 'react';

export const AlertError: React.FC<{ message: string }> = ({ message }) => (
    <p className="text-sm text-destructive-foreground bg-destructive/20 rounded-md px-3 py-2">
        {message}
    </p>
);

export const AlertSuccess: React.FC<{ message: string }> = ({ message }) => (
    <p className="text-sm text-green-700 bg-green-100 dark:text-green-300 dark:bg-green-900/30 rounded-md px-3 py-2">
        {message}
    </p>
);

export const PASSWORD_HINT = 'Le mot de passe doit contenir au moins 8 caractères et combiner au moins 3 des critères suivants : majuscule, minuscule, chiffre, caractère spécial.';

export const PasswordHint: React.FC = () => (
    <p className="text-xs text-muted-foreground">{PASSWORD_HINT}</p>
);
