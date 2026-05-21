<?php

namespace App\Tests\Functional\Controller;

use App\Entity\PasswordResetToken;
use App\Entity\User;
use App\Tests\Functional\ApiTestCase;

class PasswordResetControllerTest extends ApiTestCase
{
    public function testRequestWithExistingEmailReturnsGenericSuccessMessage(): void
    {
        $this->createUser('user@test.fr', 'TestPass1!');

        $data = $this->jsonRequest('POST', '/api/password-reset/request', [
            'email' => 'user@test.fr',
        ]);

        $this->assertResponseStatusCode(200);
        $this->assertArrayHasKey('message', $data);
        $this->assertStringContainsString('Si cette adresse existe', $data['message']);
    }

    public function testRequestWithNonExistingEmailReturnsIdenticalSuccessMessage(): void
    {
        $data = $this->jsonRequest('POST', '/api/password-reset/request', [
            'email' => 'nobody@test.fr',
        ]);

        $this->assertResponseStatusCode(200);
        $this->assertArrayHasKey('message', $data);
        $this->assertStringContainsString('Si cette adresse existe', $data['message']);
    }

    public function testRequestWithEmptyEmailReturnsBadRequest(): void
    {
        $data = $this->jsonRequest('POST', '/api/password-reset/request', [
            'email' => '',
        ]);

        $this->assertResponseStatusCode(400);
        $this->assertArrayHasKey('error', $data);
    }

    public function testRequestCreatesTokenForExistingUser(): void
    {
        $user = $this->createUser('user@test.fr', 'TestPass1!');

        $this->jsonRequest('POST', '/api/password-reset/request', [
            'email' => 'user@test.fr',
        ]);

        $token = $this->em->getRepository(PasswordResetToken::class)->findOneBy(['user' => $user]);
        $this->assertNotNull($token);
        $this->assertFalse($token->isUsed());
        $this->assertGreaterThan(new \DateTimeImmutable(), $token->getExpiresAt());
    }

    public function testRequestDoesNotCreateTokenForNonExistingUser(): void
    {
        $this->jsonRequest('POST', '/api/password-reset/request', [
            'email' => 'nobody@test.fr',
        ]);

        $count = $this->em->getRepository(PasswordResetToken::class)->count([]);
        $this->assertSame(0, $count);
    }

    public function testRequestReplacesExistingTokenForSameUser(): void
    {
        $user = $this->createUser('user@test.fr', 'TestPass1!');

        $this->jsonRequest('POST', '/api/password-reset/request', ['email' => 'user@test.fr']);
        $firstToken = $this->em->getRepository(PasswordResetToken::class)->findOneBy(['user' => $user]);

        $this->jsonRequest('POST', '/api/password-reset/request', ['email' => 'user@test.fr']);
        $this->em->clear();

        $tokens = $this->em->getRepository(PasswordResetToken::class)->findBy(['user' => $user]);
        $this->assertCount(1, $tokens);
        $this->assertNotSame($firstToken->getToken(), $tokens[0]->getToken());
    }

    public function testConfirmWithValidTokenResetsPassword(): void
    {
        $user = $this->createUser('user@test.fr', 'OldPass1!');
        $tokenValue = bin2hex(random_bytes(32));
        $token = new PasswordResetToken($user, $tokenValue, new \DateTimeImmutable('+1 hour'));
        $this->em->persist($token);
        $this->em->flush();

        $data = $this->jsonRequest('POST', '/api/password-reset/confirm', [
            'token' => $tokenValue,
            'password' => 'NewPass1!',
            'confirmPassword' => 'NewPass1!',
        ]);

        $this->assertResponseStatusCode(200);
        $this->assertTrue($data['success']);
    }

    public function testConfirmAllowsLoginWithNewPassword(): void
    {
        $user = $this->createUser('user@test.fr', 'OldPass1!');
        $tokenValue = bin2hex(random_bytes(32));
        $token = new PasswordResetToken($user, $tokenValue, new \DateTimeImmutable('+1 hour'));
        $this->em->persist($token);
        $this->em->flush();

        $this->jsonRequest('POST', '/api/password-reset/confirm', [
            'token' => $tokenValue,
            'password' => 'NewPass1!',
            'confirmPassword' => 'NewPass1!',
        ]);

        $data = $this->jsonRequest('POST', '/api/login', [
            'email' => 'user@test.fr',
            'password' => 'NewPass1!',
        ]);

        $this->assertResponseStatusCode(200);
        $this->assertSame('user@test.fr', $data['email']);
    }

    public function testConfirmClearsMustChangePasswordFlag(): void
    {
        $user = $this->createUser('user@test.fr', 'OldPass1!', mustChangePassword: true);
        $tokenValue = bin2hex(random_bytes(32));
        $token = new PasswordResetToken($user, $tokenValue, new \DateTimeImmutable('+1 hour'));
        $this->em->persist($token);
        $this->em->flush();

        $this->jsonRequest('POST', '/api/password-reset/confirm', [
            'token' => $tokenValue,
            'password' => 'NewPass1!',
            'confirmPassword' => 'NewPass1!',
        ]);

        $freshUser = $this->em->getRepository(User::class)->find($user->getId());
        $this->assertFalse($freshUser->isMustChangePassword());
    }

    public function testConfirmMarksTokenAsUsed(): void
    {
        $user = $this->createUser('user@test.fr', 'OldPass1!');
        $tokenValue = bin2hex(random_bytes(32));
        $token = new PasswordResetToken($user, $tokenValue, new \DateTimeImmutable('+1 hour'));
        $this->em->persist($token);
        $this->em->flush();
        $tokenId = $token->getId();

        $this->jsonRequest('POST', '/api/password-reset/confirm', [
            'token' => $tokenValue,
            'password' => 'NewPass1!',
            'confirmPassword' => 'NewPass1!',
        ]);

        $this->em->clear();
        $freshToken = $this->em->getRepository(PasswordResetToken::class)->find($tokenId);
        $this->assertTrue($freshToken->isUsed());
    }

    public function testConfirmWithExpiredTokenReturnsBadRequest(): void
    {
        $user = $this->createUser('user@test.fr', 'OldPass1!');
        $tokenValue = bin2hex(random_bytes(32));
        $token = new PasswordResetToken($user, $tokenValue, new \DateTimeImmutable('-1 second'));
        $this->em->persist($token);
        $this->em->flush();

        $data = $this->jsonRequest('POST', '/api/password-reset/confirm', [
            'token' => $tokenValue,
            'password' => 'NewPass1!',
            'confirmPassword' => 'NewPass1!',
        ]);

        $this->assertResponseStatusCode(400);
        $this->assertArrayHasKey('error', $data);
    }

    public function testConfirmWithUsedTokenReturnsBadRequest(): void
    {
        $user = $this->createUser('user@test.fr', 'OldPass1!');
        $tokenValue = bin2hex(random_bytes(32));
        $token = new PasswordResetToken($user, $tokenValue, new \DateTimeImmutable('+1 hour'));
        $token->markAsUsed();
        $this->em->persist($token);
        $this->em->flush();

        $data = $this->jsonRequest('POST', '/api/password-reset/confirm', [
            'token' => $tokenValue,
            'password' => 'NewPass1!',
            'confirmPassword' => 'NewPass1!',
        ]);

        $this->assertResponseStatusCode(400);
        $this->assertArrayHasKey('error', $data);
    }

    public function testConfirmWithUnknownTokenReturnsBadRequest(): void
    {
        $data = $this->jsonRequest('POST', '/api/password-reset/confirm', [
            'token' => str_repeat('a', 64),
            'password' => 'NewPass1!',
            'confirmPassword' => 'NewPass1!',
        ]);

        $this->assertResponseStatusCode(400);
        $this->assertArrayHasKey('error', $data);
    }

    public function testConfirmWithMismatchedPasswordsReturnsBadRequest(): void
    {
        $user = $this->createUser('user@test.fr', 'OldPass1!');
        $tokenValue = bin2hex(random_bytes(32));
        $token = new PasswordResetToken($user, $tokenValue, new \DateTimeImmutable('+1 hour'));
        $this->em->persist($token);
        $this->em->flush();

        $data = $this->jsonRequest('POST', '/api/password-reset/confirm', [
            'token' => $tokenValue,
            'password' => 'NewPass1!',
            'confirmPassword' => 'Different1!',
        ]);

        $this->assertResponseStatusCode(400);
        $this->assertSame('Les mots de passe ne correspondent pas.', $data['error']);
    }

    public function testConfirmWithWeakPasswordReturnsBadRequest(): void
    {
        $user = $this->createUser('user@test.fr', 'OldPass1!');
        $tokenValue = bin2hex(random_bytes(32));
        $token = new PasswordResetToken($user, $tokenValue, new \DateTimeImmutable('+1 hour'));
        $this->em->persist($token);
        $this->em->flush();

        $data = $this->jsonRequest('POST', '/api/password-reset/confirm', [
            'token' => $tokenValue,
            'password' => 'weak',
            'confirmPassword' => 'weak',
        ]);

        $this->assertResponseStatusCode(400);
        $this->assertStringContainsString('au moins 8 caractères', $data['error']);
    }

    public function testConfirmWithMissingFieldsReturnsBadRequest(): void
    {
        $data = $this->jsonRequest('POST', '/api/password-reset/confirm', [
            'token' => '',
            'password' => '',
            'confirmPassword' => '',
        ]);

        $this->assertResponseStatusCode(400);
        $this->assertArrayHasKey('error', $data);
    }
}
