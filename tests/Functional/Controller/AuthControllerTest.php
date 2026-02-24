<?php

namespace App\Tests\Functional\Controller;

use App\Tests\Functional\ApiTestCase;

class AuthControllerTest extends ApiTestCase
{
    public function testLoginWithValidCredentials(): void
    {
        $user = $this->createUser();

        $data = $this->jsonRequest('POST', '/api/login', [
            'email' => 'user@test.fr',
            'password' => 'TestPass1!',
        ]);

        $this->assertResponseStatusCode(200);
        $this->assertSame('user@test.fr', $data['email']);
        $this->assertContains('ROLE_USER', $data['roles']);
    }

    public function testLoginWithInvalidPassword(): void
    {
        $this->createUser();

        $data = $this->jsonRequest('POST', '/api/login', [
            'email' => 'user@test.fr',
            'password' => 'WrongPass1!',
        ]);

        $this->assertResponseStatusCode(401);
        $this->assertSame('Identifiants incorrects.', $data['error']);
    }

    public function testLoginWithNonexistentEmail(): void
    {
        $data = $this->jsonRequest('POST', '/api/login', [
            'email' => 'nobody@test.fr',
            'password' => 'TestPass1!',
        ]);

        $this->assertResponseStatusCode(401);
        $this->assertSame('Identifiants incorrects.', $data['error']);
    }

    public function testLoginWithEmptyFields(): void
    {
        $data = $this->jsonRequest('POST', '/api/login', [
            'email' => '',
            'password' => '',
        ]);

        $this->assertResponseStatusCode(400);
        $this->assertSame('Email et mot de passe requis.', $data['error']);
    }

    public function testLoginWithInactiveAccount(): void
    {
        $this->createUser(active: false);

        $data = $this->jsonRequest('POST', '/api/login', [
            'email' => 'user@test.fr',
            'password' => 'TestPass1!',
        ]);

        $this->assertResponseStatusCode(403);
        $this->assertSame('Compte désactivé.', $data['error']);
    }

    public function testMeAuthenticated(): void
    {
        $user = $this->createUser();
        $this->loginAs($user);

        $data = $this->jsonRequest('GET', '/api/me');

        $this->assertResponseStatusCode(200);
        $this->assertSame('user@test.fr', $data['email']);
    }

    public function testMeUnauthenticated(): void
    {
        $this->jsonRequest('GET', '/api/me');

        $this->assertResponseStatusCode(401);
    }

    public function testLogoutInvalidatesSession(): void
    {
        $user = $this->createUser();
        $this->loginAs($user);

        $this->client->request('POST', '/api/logout');

        $this->jsonRequest('GET', '/api/me');
        $this->assertResponseStatusCode(401);
    }

    public function testLoginReturnsMustChangePasswordFlag(): void
    {
        $this->createUser(mustChangePassword: true);

        $data = $this->jsonRequest('POST', '/api/login', [
            'email' => 'user@test.fr',
            'password' => 'TestPass1!',
        ]);

        $this->assertResponseStatusCode(200);
        $this->assertTrue($data['mustChangePassword']);
    }
}
