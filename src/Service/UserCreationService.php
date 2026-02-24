<?php

namespace App\Service;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserCreationService
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $passwordHasher,
        private MailerInterface $mailer,
    ) {}

    /**
     * @throws \InvalidArgumentException if email is missing or invalid
     * @throws \DomainException if a user with this email already exists
     */
    public function createUser(string $email, ?string $firstName, ?string $lastName): User
    {
        $email = trim($email);
        if (!$email) {
            throw new \InvalidArgumentException('L\'adresse email est requise.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Adresse email invalide.');
        }

        $existing = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($existing) {
            throw new \DomainException('Un compte existe déjà avec cette adresse email.');
        }

        $tempPassword = bin2hex(random_bytes(8));

        $user = new User();
        $user->setEmail($email);
        $user->setFirstName($firstName ?: null);
        $user->setLastName($lastName ?: null);
        $user->setPassword($this->passwordHasher->hashPassword($user, $tempPassword));
        $user->setMustChangePassword(true);

        $this->em->persist($user);
        $this->em->flush();

        $this->sendWelcomeEmail($user, $tempPassword);

        return $user;
    }

    private function sendWelcomeEmail(User $user, string $tempPassword): void
    {
        $email = (new TemplatedEmail())
            ->from('noreply@chess-studio.org')
            ->to(new Address($user->getEmail()))
            ->subject('Bienvenue sur Breizh Chess Studio')
            ->htmlTemplate('email/welcome.html.twig')
            ->context([
                'user' => $user,
                'tempPassword' => $tempPassword,
            ]);

        $this->mailer->send($email);
    }
}
