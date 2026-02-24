<?php

namespace App\Tests\Functional\Controller;

use App\Tests\Functional\ApiTestCase;

class ProfileControllerTest extends ApiTestCase
{
    public function testForceChangePasswordSuccess(): void
    {
        $user = $this->createUser(mustChangePassword: true);
        $this->loginAs($user);

        $data = $this->jsonRequest('POST', '/api/change-password', [
            'password' => 'NewStrong1!',
            'confirmPassword' => 'NewStrong1!',
        ]);

        $this->assertResponseStatusCode(200);
        $this->assertTrue($data['success']);

        $freshUser = $this->em->getRepository(\App\Entity\User::class)->find($user->getId());
        $this->assertFalse($freshUser->isMustChangePassword());
    }

    public function testForceChangePasswordMismatch(): void
    {
        $user = $this->createUser(mustChangePassword: true);
        $this->loginAs($user);

        $data = $this->jsonRequest('POST', '/api/change-password', [
            'password' => 'NewStrong1!',
            'confirmPassword' => 'Different1!',
        ]);

        $this->assertResponseStatusCode(400);
        $this->assertSame('Les mots de passe ne correspondent pas.', $data['error']);
    }

    public function testForceChangePasswordWeak(): void
    {
        $user = $this->createUser(mustChangePassword: true);
        $this->loginAs($user);

        $data = $this->jsonRequest('POST', '/api/change-password', [
            'password' => 'weak',
            'confirmPassword' => 'weak',
        ]);

        $this->assertResponseStatusCode(400);
        $this->assertStringContainsString('au moins 8 caractères', $data['error']);
    }

    public function testForceChangePasswordRequiresAuth(): void
    {
        $this->jsonRequest('POST', '/api/change-password', [
            'password' => 'NewStrong1!',
            'confirmPassword' => 'NewStrong1!',
        ]);

        $this->assertResponseStatusCode(401);
    }

    public function testUpdatePasswordSuccess(): void
    {
        $user = $this->createUser();
        $this->loginAs($user);

        $data = $this->jsonRequest('POST', '/api/profile/password', [
            'currentPassword' => 'TestPass1!',
            'newPassword' => 'NewStrong1!',
            'confirmPassword' => 'NewStrong1!',
        ]);

        $this->assertResponseStatusCode(200);
        $this->assertTrue($data['success']);
    }

    public function testUpdatePasswordWrongCurrent(): void
    {
        $user = $this->createUser();
        $this->loginAs($user);

        $data = $this->jsonRequest('POST', '/api/profile/password', [
            'currentPassword' => 'WrongPass1!',
            'newPassword' => 'NewStrong1!',
            'confirmPassword' => 'NewStrong1!',
        ]);

        $this->assertResponseStatusCode(400);
        $this->assertSame('Le mot de passe actuel est incorrect.', $data['error']);
    }

    public function testUpdatePasswordMismatch(): void
    {
        $user = $this->createUser();
        $this->loginAs($user);

        $data = $this->jsonRequest('POST', '/api/profile/password', [
            'currentPassword' => 'TestPass1!',
            'newPassword' => 'NewStrong1!',
            'confirmPassword' => 'Different1!',
        ]);

        $this->assertResponseStatusCode(400);
        $this->assertSame('Les mots de passe ne correspondent pas.', $data['error']);
    }

    public function testUpdatePasswordRequiresAuth(): void
    {
        $this->jsonRequest('POST', '/api/profile/password', [
            'currentPassword' => 'TestPass1!',
            'newPassword' => 'NewStrong1!',
            'confirmPassword' => 'NewStrong1!',
        ]);

        $this->assertResponseStatusCode(401);
    }
}
