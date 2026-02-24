<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class TestController extends AbstractController
{
    #[Route('/api/test/seed', methods: ['POST'])]
    public function seed(
        KernelInterface $kernel,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher,
    ): JsonResponse {
        if ($kernel->getEnvironment() === 'prod') {
            return $this->json(['error' => 'Not available in production'], Response::HTTP_FORBIDDEN);
        }

        $connection = $em->getConnection();
        $connection->executeStatement('TRUNCATE "user", game, analysis RESTART IDENTITY CASCADE');

        $users = [
            ['test@chess-studio.fr', 'TestPass1!', [], true, false],
            ['admin@chess-studio.fr', 'AdminPass1!', ['ROLE_USER_MANAGER'], true, false],
            ['newuser@chess-studio.fr', 'TempPass1!', [], true, true],
        ];

        foreach ($users as [$email, $password, $roles, $active, $mustChange]) {
            $user = new User();
            $user->setEmail($email);
            $user->setRoles($roles);
            $user->setActive($active);
            $user->setMustChangePassword($mustChange);
            $user->setPassword($passwordHasher->hashPassword($user, $password));
            $em->persist($user);
        }

        $em->flush();

        return $this->json(['status' => 'seeded']);
    }
}
