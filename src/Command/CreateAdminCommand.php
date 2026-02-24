<?php

namespace App\Command;

use App\Entity\User;
use App\Service\PasswordStrengthService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-admin',
    description: 'Crée un utilisateur avec le rôle ROLE_USER_MANAGER',
)]
class CreateAdminCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $passwordHasher,
        private PasswordStrengthService $passwordStrength,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $email = $io->ask('Email');
        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $io->error('Adresse email invalide.');
            return Command::FAILURE;
        }

        $existing = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($existing) {
            $io->error('Un compte existe déjà avec cette adresse email.');
            return Command::FAILURE;
        }

        $firstName = $io->ask('Prénom (optionnel)') ?: null;
        $lastName = $io->ask('Nom (optionnel)') ?: null;

        $password = $io->askHidden('Mot de passe');
        if (!$password) {
            $io->error('Le mot de passe est requis.');
            return Command::FAILURE;
        }

        $strengthError = $this->passwordStrength->validate($password);
        if ($strengthError) {
            $io->error($strengthError);
            return Command::FAILURE;
        }

        $confirm = $io->askHidden('Confirmer le mot de passe');
        if ($password !== $confirm) {
            $io->error('Les mots de passe ne correspondent pas.');
            return Command::FAILURE;
        }

        $user = new User();
        $user->setEmail($email);
        $user->setFirstName($firstName);
        $user->setLastName($lastName);
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));
        $user->setRoles(['ROLE_USER_MANAGER']);

        $this->em->persist($user);
        $this->em->flush();

        $io->success("Administrateur {$email} créé.");

        return Command::SUCCESS;
    }
}
