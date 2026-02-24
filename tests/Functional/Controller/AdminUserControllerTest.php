<?php

namespace App\Tests\Functional\Controller;

use App\Tests\Functional\ApiTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class AdminUserControllerTest extends ApiTestCase
{
    public function testListAsAdmin(): void
    {
        $admin = $this->createAdmin();
        $this->loginAs($admin, 'AdminPass1!');
        $this->createUser('other@test.fr');

        $data = $this->jsonRequest('GET', '/api/admin/users');

        $this->assertResponseStatusCode(200);
        $this->assertSame(2, $data['total']);
        $this->assertCount(2, $data['items']);
    }

    public function testListWithSearch(): void
    {
        $admin = $this->createAdmin();
        $this->loginAs($admin, 'AdminPass1!');
        $this->createUser('alice@test.fr');
        $this->createUser('bob@test.fr');

        $data = $this->jsonRequest('GET', '/api/admin/users?search=alice');

        $this->assertResponseStatusCode(200);
        $this->assertSame(1, $data['total']);
        $this->assertSame('alice@test.fr', $data['items'][0]['email']);
    }

    public function testListForbiddenForRegularUser(): void
    {
        $user = $this->createUser();
        $this->loginAs($user);

        $this->jsonRequest('GET', '/api/admin/users');

        $this->assertResponseStatusCode(403);
    }

    public function testCreateUserSuccess(): void
    {
        $admin = $this->createAdmin();
        $this->loginAs($admin, 'AdminPass1!');

        $data = $this->jsonRequest('POST', '/api/admin/users', [
            'email' => 'newuser@test.fr',
            'firstName' => 'Jean',
            'lastName' => 'Dupont',
        ]);

        $this->assertResponseStatusCode(201);
        $this->assertSame('newuser@test.fr', $data['email']);
        $this->assertSame('Jean', $data['firstName']);
        $this->assertSame('Dupont', $data['lastName']);
    }

    public function testCreateUserInvalidEmail(): void
    {
        $admin = $this->createAdmin();
        $this->loginAs($admin, 'AdminPass1!');

        $data = $this->jsonRequest('POST', '/api/admin/users', [
            'email' => 'not-an-email',
            'firstName' => 'Jean',
            'lastName' => 'Dupont',
        ]);

        $this->assertResponseStatusCode(400);
        $this->assertSame('Adresse email invalide.', $data['error']);
    }

    public function testCreateUserDuplicate(): void
    {
        $admin = $this->createAdmin();
        $this->loginAs($admin, 'AdminPass1!');
        $this->createUser('existing@test.fr');

        $data = $this->jsonRequest('POST', '/api/admin/users', [
            'email' => 'existing@test.fr',
        ]);

        $this->assertResponseStatusCode(409);
        $this->assertStringContainsString('existe déjà', $data['error']);
    }

    public function testCreateUserForbiddenForRegularUser(): void
    {
        $user = $this->createUser();
        $this->loginAs($user);

        $this->jsonRequest('POST', '/api/admin/users', [
            'email' => 'new@test.fr',
        ]);

        $this->assertResponseStatusCode(403);
    }

    public function testCsvImportSuccess(): void
    {
        $admin = $this->createAdmin();
        $this->loginAs($admin, 'AdminPass1!');

        $csv = "email,lastName,firstName\nalice@test.fr,Dupont,Alice\nbob@test.fr,Martin,Bob";
        $tmpFile = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($tmpFile, $csv);
        $uploadedFile = new UploadedFile($tmpFile, 'users.csv', 'text/csv', null, true);

        $this->client->request('POST', '/api/admin/users/import', [], ['file' => $uploadedFile]);

        $this->assertResponseStatusCode(200);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame(2, $data['created']);
        $this->assertEmpty($data['errors']);
    }

    public function testCsvImportReportsErrors(): void
    {
        $admin = $this->createAdmin();
        $this->loginAs($admin, 'AdminPass1!');
        $this->createUser('existing@test.fr');

        $csv = "email,lastName,firstName\nexisting@test.fr,Dup,Alice\ninvalid-email,Martin,Bob";
        $tmpFile = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($tmpFile, $csv);
        $uploadedFile = new UploadedFile($tmpFile, 'users.csv', 'text/csv', null, true);

        $this->client->request('POST', '/api/admin/users/import', [], ['file' => $uploadedFile]);

        $this->assertResponseStatusCode(200);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame(0, $data['created']);
        $this->assertCount(2, $data['errors']);
    }

    public function testToggleActiveDeactivate(): void
    {
        $admin = $this->createAdmin();
        $this->loginAs($admin, 'AdminPass1!');
        $user = $this->createUser();

        $data = $this->jsonRequest('PATCH', '/api/admin/users/' . $user->getId() . '/toggle-active');

        $this->assertResponseStatusCode(200);
        $this->assertFalse($data['active']);
    }

    public function testToggleActiveReactivate(): void
    {
        $admin = $this->createAdmin();
        $this->loginAs($admin, 'AdminPass1!');
        $user = $this->createUser(active: false);

        $data = $this->jsonRequest('PATCH', '/api/admin/users/' . $user->getId() . '/toggle-active');

        $this->assertResponseStatusCode(200);
        $this->assertTrue($data['active']);
    }

    public function testToggleActiveForbiddenForRegularUser(): void
    {
        $user = $this->createUser();
        $this->loginAs($user);
        $other = $this->createUser('other@test.fr');

        $this->jsonRequest('PATCH', '/api/admin/users/' . $other->getId() . '/toggle-active');

        $this->assertResponseStatusCode(403);
    }
}
