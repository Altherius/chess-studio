<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:test:seed', description: 'Seed the test database with fixtures')]
class TestSeedCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $connection = $this->em->getConnection();
        $connection->executeStatement('TRUNCATE "user", game, analysis RESTART IDENTITY CASCADE');

        $this->createTestUser(
            'test@chess-studio.fr',
            'TestPass1!',
            [],
            true,
            false,
        );

        $this->createTestUser(
            'admin@chess-studio.fr',
            'AdminPass1!',
            ['ROLE_USER_MANAGER'],
            true,
            false,
        );

        $this->createTestUser(
            'newuser@chess-studio.fr',
            'TempPass1!',
            [],
            true,
            true,
        );

        $this->em->flush();
        $output->writeln('Test data seeded successfully.');

        return Command::SUCCESS;
    }

    private function createTestUser(
        string $email,
        string $password,
        array $roles,
        bool $active,
        bool $mustChangePassword,
    ): void {
        $user = new User();
        $user->setEmail($email);
        $user->setRoles($roles);
        $user->setActive($active);
        $user->setMustChangePassword($mustChangePassword);
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));
        $this->em->persist($user);
    }
}
