<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

class AuthController extends AbstractController
{
    #[Route('/api/login', methods: ['POST'])]
    public function login(
        Request $request,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
        TokenStorageInterface $tokenStorage,
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $email = trim($data['email'] ?? '');
        $password = $data['password'] ?? '';

        if (!$email || !$password) {
            return $this->json(['error' => 'Email et mot de passe requis.'], Response::HTTP_BAD_REQUEST);
        }

        $user = $userRepository->findOneBy(['email' => $email]);

        if (!$user || !$passwordHasher->isPasswordValid($user, $password)) {
            return $this->json(['error' => 'Identifiants incorrects.'], Response::HTTP_UNAUTHORIZED);
        }

        if (!$user->isActive()) {
            return $this->json(['error' => 'Compte désactivé.'], Response::HTTP_FORBIDDEN);
        }

        $token = new UsernamePasswordToken($user, 'main', $user->getRoles());
        $tokenStorage->setToken($token);
        $request->getSession()->set('_security_main', serialize($token));

        return $this->json($this->serializeUser($user));
    }

    #[Route('/api/me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->json(null, Response::HTTP_UNAUTHORIZED);
        }

        return $this->json($this->serializeUser($user));
    }

    #[Route('/api/logout', methods: ['POST'])]
    public function logout(): void {}

    private function serializeUser(User $user): array
    {
        return [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'firstName' => $user->getFirstName(),
            'lastName' => $user->getLastName(),
            'roles' => $user->getRoles(),
            'mustChangePassword' => $user->isMustChangePassword(),
            'active' => $user->isActive(),
        ];
    }
}
