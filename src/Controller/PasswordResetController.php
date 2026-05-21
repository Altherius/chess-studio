<?php

namespace App\Controller;

use App\Entity\PasswordResetToken;
use App\Entity\User;
use App\Repository\PasswordResetTokenRepository;
use App\Service\PasswordStrengthService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class PasswordResetController extends AbstractController
{
    private const SUCCESS_MESSAGE = 'Si cette adresse existe dans notre base de données, un e-mail permettant de réinitialiser votre mot de passe a été envoyé.';
    private const TOKEN_TTL_SECONDS = 3600;

    #[Route('/api/password-reset/request', methods: ['POST'])]
    public function request(
        Request $request,
        EntityManagerInterface $em,
        MailerInterface $mailer,
        PasswordResetTokenRepository $tokenRepository,
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $emailAddress = trim($data['email'] ?? '');

        if (!$emailAddress) {
            return $this->json(['error' => 'L\'adresse email est requise.'], Response::HTTP_BAD_REQUEST);
        }

        $user = $em->getRepository(User::class)->findOneBy(['email' => $emailAddress]);

        if ($user) {
            $tokenRepository->deleteForUser($user);

            $tokenValue = bin2hex(random_bytes(32));
            $expiresAt = new \DateTimeImmutable(sprintf('+%d seconds', self::TOKEN_TTL_SECONDS));
            $token = new PasswordResetToken($user, $tokenValue, $expiresAt);

            $em->persist($token);
            $em->flush();

            $resetUrl = $request->getSchemeAndHttpHost() . '/reset/' . $tokenValue;
            $this->sendResetEmail($mailer, $user, $resetUrl);
        }

        return $this->json(['message' => self::SUCCESS_MESSAGE]);
    }

    #[Route('/api/password-reset/confirm', methods: ['POST'])]
    public function confirm(
        Request $request,
        EntityManagerInterface $em,
        PasswordResetTokenRepository $tokenRepository,
        UserPasswordHasherInterface $passwordHasher,
        PasswordStrengthService $passwordStrength,
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $tokenValue = $data['token'] ?? '';
        $password = $data['password'] ?? '';
        $confirmPassword = $data['confirmPassword'] ?? '';

        if (!$tokenValue || !$password) {
            return $this->json(['error' => 'Tous les champs sont requis.'], Response::HTTP_BAD_REQUEST);
        }

        $token = $tokenRepository->findValidToken($tokenValue);
        if (!$token) {
            return $this->json(['error' => 'Ce lien de réinitialisation est invalide ou a expiré.'], Response::HTTP_BAD_REQUEST);
        }

        if ($password !== $confirmPassword) {
            return $this->json(['error' => 'Les mots de passe ne correspondent pas.'], Response::HTTP_BAD_REQUEST);
        }

        $strengthError = $passwordStrength->validate($password);
        if ($strengthError) {
            return $this->json(['error' => $strengthError], Response::HTTP_BAD_REQUEST);
        }

        $user = $token->getUser();
        $user->setPassword($passwordHasher->hashPassword($user, $password));
        $user->setMustChangePassword(false);
        $token->markAsUsed();

        $em->flush();

        return $this->json(['success' => true]);
    }

    private function sendResetEmail(MailerInterface $mailer, User $user, string $resetUrl): void
    {
        $email = (new TemplatedEmail())
            ->from('noreply@chess-studio.org')
            ->to(new Address($user->getEmail()))
            ->subject('Réinitialisation de votre mot de passe')
            ->htmlTemplate('email/password_reset.html.twig')
            ->context([
                'user' => $user,
                'resetUrl' => $resetUrl,
            ]);

        $mailer->send($email);
    }
}
