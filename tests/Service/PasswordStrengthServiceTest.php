<?php

namespace App\Tests\Service;

use App\Service\PasswordStrengthService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PasswordStrengthServiceTest extends TestCase
{
    private PasswordStrengthService $service;

    protected function setUp(): void
    {
        $this->service = new PasswordStrengthService();
    }

    #[DataProvider('validPasswordsProvider')]
    public function testValidPasswords(string $password): void
    {
        $this->assertNull($this->service->validate($password));
    }

    public static function validPasswordsProvider(): iterable
    {
        yield 'upper + lower + digit' => ['Abcdefg1'];
        yield 'upper + lower + special' => ['Abcdefg!'];
        yield 'upper + digit + special' => ['ABCDEF1!'];
        yield 'lower + digit + special' => ['abcdef1!'];
        yield 'all 4 criteria' => ['Abcdef1!'];
        yield 'long password' => ['MyStr0ng!Password'];
    }

    #[DataProvider('invalidPasswordsProvider')]
    public function testInvalidPasswords(string $password): void
    {
        $result = $this->service->validate($password);
        $this->assertNotNull($result);
        $this->assertStringContainsString('au moins 8 caractères', $result);
    }

    public static function invalidPasswordsProvider(): iterable
    {
        yield 'too short with 3 criteria' => ['Ab1!'];
        yield 'too short 7 chars' => ['Abcde1!'];
        yield 'only lowercase + digit' => ['abcdefg1'];
        yield 'only uppercase + lowercase' => ['Abcdefgh'];
        yield 'only digits' => ['12345678'];
        yield 'only lowercase' => ['abcdefgh'];
        yield 'only uppercase' => ['ABCDEFGH'];
        yield 'only special chars' => ['!@#$%^&*'];
    }
}
