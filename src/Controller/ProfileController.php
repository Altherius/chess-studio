<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\PasswordStrengthService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class ProfileController extends AbstractController
{
    #[Route('/api/change-password', methods: ['POST'])]
    public function changePassword(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $em,
        PasswordStrengthService $passwordStrength,
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);
        $password = $data['password'] ?? '';
        $confirmPassword = $data['confirmPassword'] ?? '';

        if (!$password) {
            return $this->json(['error' => 'Le mot de passe est requis.'], Response::HTTP_BAD_REQUEST);
        }

        if ($password !== $confirmPassword) {
            return $this->json(['error' => 'Les mots de passe ne correspondent pas.'], Response::HTTP_BAD_REQUEST);
        }

        $strengthError = $passwordStrength->validate($password);
        if ($strengthError) {
            return $this->json(['error' => $strengthError], Response::HTTP_BAD_REQUEST);
        }

        $user->setPassword($passwordHasher->hashPassword($user, $password));
        $user->setMustChangePassword(false);
        $em->flush();

        return $this->json(['success' => true]);
    }

    #[Route('/api/profile/password', methods: ['POST'])]
    public function updatePassword(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $em,
        PasswordStrengthService $passwordStrength,
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);
        $currentPassword = $data['currentPassword'] ?? '';
        $newPassword = $data['newPassword'] ?? '';
        $confirmPassword = $data['confirmPassword'] ?? '';

        if (!$currentPassword || !$newPassword) {
            return $this->json(['error' => 'Tous les champs sont requis.'], Response::HTTP_BAD_REQUEST);
        }

        if (!$passwordHasher->isPasswordValid($user, $currentPassword)) {
            return $this->json(['error' => 'Le mot de passe actuel est incorrect.'], Response::HTTP_BAD_REQUEST);
        }

        if ($newPassword !== $confirmPassword) {
            return $this->json(['error' => 'Les mots de passe ne correspondent pas.'], Response::HTTP_BAD_REQUEST);
        }

        $strengthError = $passwordStrength->validate($newPassword);
        if ($strengthError) {
            return $this->json(['error' => $strengthError], Response::HTTP_BAD_REQUEST);
        }

        $user->setPassword($passwordHasher->hashPassword($user, $newPassword));
        $em->flush();

        return $this->json(['success' => true]);
    }
}
