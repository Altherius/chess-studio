<?php

namespace App\Tests\Functional;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

abstract class ApiTestCase extends WebTestCase
{
    protected KernelBrowser $client;
    protected EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        $connection = $this->em->getConnection();
        $connection->executeStatement('TRUNCATE "user", game, analysis RESTART IDENTITY CASCADE');
    }

    protected function createUser(
        string $email = 'user@test.fr',
        string $password = 'TestPass1!',
        array $roles = [],
        bool $active = true,
        bool $mustChangePassword = false,
    ): User {
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $user = new User();
        $user->setEmail($email);
        $user->setRoles($roles);
        $user->setActive($active);
        $user->setMustChangePassword($mustChangePassword);
        $user->setPassword($hasher->hashPassword($user, $password));

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    protected function createAdmin(
        string $email = 'admin@test.fr',
        string $password = 'AdminPass1!',
    ): User {
        return $this->createUser($email, $password, ['ROLE_USER_MANAGER']);
    }

    protected function loginAs(User $user, string $password = 'TestPass1!'): void
    {
        $this->client->request('POST', '/api/login', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['email' => $user->getEmail(), 'password' => $password]));
    }

    protected function jsonRequest(string $method, string $url, ?array $body = null): array
    {
        $this->client->request($method, $url, [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], $body !== null ? json_encode($body) : null);

        $content = $this->client->getResponse()->getContent();

        return $content ? json_decode($content, true) ?? [] : [];
    }

    protected function assertResponseStatusCode(int $expected): void
    {
        $this->assertSame($expected, $this->client->getResponse()->getStatusCode());
    }
}
