<?php

namespace App\Tests\Functional\Controller;

use App\Entity\Game;
use App\Tests\Functional\ApiTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class GameControllerTest extends ApiTestCase
{
    private const SAMPLE_PGN = '[Event "Test"]
[White "Kasparov"]
[Black "Karpov"]
[Result "1-0"]

1. e4 e5 2. Nf3 Nc6 1-0';

    private function loginAndImportGame(bool $isPublic = true): array
    {
        $user = $this->createUser();
        $this->loginAs($user);

        return $this->jsonRequest('POST', '/api/games/import', [
            'pgn' => self::SAMPLE_PGN,
            'isPublic' => $isPublic,
        ]);
    }

    public function testImportPgnSuccess(): void
    {
        $data = $this->loginAndImportGame();

        $this->assertResponseStatusCode(201);
        $this->assertArrayHasKey('id', $data);
        $this->assertSame('Kasparov', $data['playerWhite']);
        $this->assertSame('Karpov', $data['playerBlack']);
        $this->assertSame('1-0', $data['result']);
    }

    public function testImportPgnRequiresAuth(): void
    {
        $this->jsonRequest('POST', '/api/games/import', [
            'pgn' => self::SAMPLE_PGN,
        ]);

        $this->assertResponseStatusCode(401);
    }

    public function testImportPgnEmptyPgn(): void
    {
        $user = $this->createUser();
        $this->loginAs($user);

        $data = $this->jsonRequest('POST', '/api/games/import', ['pgn' => '']);

        $this->assertResponseStatusCode(400);
        $this->assertSame('PGN is required', $data['error']);
    }

    public function testImportPgnInvalidMoves(): void
    {
        $user = $this->createUser();
        $this->loginAs($user);

        $data = $this->jsonRequest('POST', '/api/games/import', [
            'pgn' => '1. e4 e5 2. Zz9 *',
        ]);

        $this->assertResponseStatusCode(400);
        $this->assertArrayHasKey('error', $data);
    }

    public function testImportFileSuccess(): void
    {
        $user = $this->createUser();
        $this->loginAs($user);

        $tmpFile = tempnam(sys_get_temp_dir(), 'pgn');
        file_put_contents($tmpFile, self::SAMPLE_PGN);
        $uploadedFile = new UploadedFile($tmpFile, 'game.pgn', 'text/plain', null, true);

        $this->client->request('POST', '/api/games/import/file', [], ['pgn' => $uploadedFile]);

        $this->assertResponseStatusCode(201);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('Kasparov', $data['playerWhite']);
    }

    public function testImportFileNoFile(): void
    {
        $user = $this->createUser();
        $this->loginAs($user);

        $this->client->request('POST', '/api/games/import/file');

        $this->assertResponseStatusCode(400);
    }

    public function testImportFileWrongExtension(): void
    {
        $user = $this->createUser();
        $this->loginAs($user);

        $tmpFile = tempnam(sys_get_temp_dir(), 'txt');
        file_put_contents($tmpFile, 'not a pgn');
        $uploadedFile = new UploadedFile($tmpFile, 'game.txt', 'text/plain', null, true);

        $this->client->request('POST', '/api/games/import/file', [], ['pgn' => $uploadedFile]);

        $this->assertResponseStatusCode(400);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('Only .pgn files are accepted', $data['error']);
    }

    public function testImportFormSuccess(): void
    {
        $user = $this->createUser();
        $this->loginAs($user);

        $data = $this->jsonRequest('POST', '/api/games/import/form', [
            'moves' => '1. e4 e5 2. Nf3 Nc6 *',
            'playerWhite' => 'White Player',
            'playerBlack' => 'Black Player',
        ]);

        $this->assertResponseStatusCode(201);
        $this->assertArrayHasKey('id', $data);
        $this->assertSame('White Player', $data['playerWhite']);
    }

    public function testImportFormEmptyMoves(): void
    {
        $user = $this->createUser();
        $this->loginAs($user);

        $data = $this->jsonRequest('POST', '/api/games/import/form', [
            'moves' => '',
        ]);

        $this->assertResponseStatusCode(400);
        $this->assertSame('Moves are required', $data['error']);
    }

    public function testListReturnsOnlyOwnGames(): void
    {
        $user1 = $this->createUser('user1@test.fr');
        $user2 = $this->createUser('user2@test.fr');

        $this->loginAs($user1, 'TestPass1!');
        $this->jsonRequest('POST', '/api/games/import', ['pgn' => self::SAMPLE_PGN]);

        $this->loginAs($user2, 'TestPass1!');
        $this->jsonRequest('POST', '/api/games/import', ['pgn' => self::SAMPLE_PGN]);

        $data = $this->jsonRequest('GET', '/api/games');

        $this->assertResponseStatusCode(200);
        $this->assertCount(1, $data['items']);
        $this->assertSame(1, $data['total']);
    }

    public function testListPagination(): void
    {
        $user = $this->createUser();
        $this->loginAs($user);

        for ($i = 0; $i < 3; $i++) {
            $this->jsonRequest('POST', '/api/games/import', ['pgn' => self::SAMPLE_PGN]);
        }

        $data = $this->jsonRequest('GET', '/api/games?offset=0&limit=2');

        $this->assertResponseStatusCode(200);
        $this->assertCount(2, $data['items']);
        $this->assertSame(3, $data['total']);
        $this->assertSame(0, $data['offset']);
        $this->assertSame(2, $data['limit']);
    }

    public function testListPlayerFilter(): void
    {
        $user = $this->createUser();
        $this->loginAs($user);

        $this->jsonRequest('POST', '/api/games/import', ['pgn' => self::SAMPLE_PGN]);
        $this->jsonRequest('POST', '/api/games/import', [
            'pgn' => "[White \"Fischer\"]\n[Black \"Spassky\"]\n[Result \"1-0\"]\n\n1. e4 e5 1-0",
        ]);

        $data = $this->jsonRequest('GET', '/api/games?player=Fischer');

        $this->assertResponseStatusCode(200);
        $this->assertSame(1, $data['total']);
    }

    public function testListRequiresAuth(): void
    {
        $this->jsonRequest('GET', '/api/games');
        $this->assertResponseStatusCode(401);
    }

    public function testPublicGamesReturnsOnlyPublic(): void
    {
        $user = $this->createUser();
        $this->loginAs($user);

        $this->jsonRequest('POST', '/api/games/import', [
            'pgn' => self::SAMPLE_PGN,
            'isPublic' => true,
        ]);
        $this->jsonRequest('POST', '/api/games/import', [
            'pgn' => self::SAMPLE_PGN,
            'isPublic' => false,
        ]);

        $data = $this->jsonRequest('GET', '/api/games/public');

        $this->assertResponseStatusCode(200);
        $this->assertSame(1, $data['total']);
    }

    public function testShowOwnGame(): void
    {
        $imported = $this->loginAndImportGame();

        $data = $this->jsonRequest('GET', '/api/games/' . $imported['id']);

        $this->assertResponseStatusCode(200);
        $this->assertSame($imported['id'], $data['id']);
        $this->assertArrayHasKey('pgn', $data);
        $this->assertArrayHasKey('analyses', $data);
    }

    public function testShowOtherPublicGame(): void
    {
        $user1 = $this->createUser('user1@test.fr');
        $this->loginAs($user1, 'TestPass1!');
        $imported = $this->jsonRequest('POST', '/api/games/import', [
            'pgn' => self::SAMPLE_PGN,
            'isPublic' => true,
        ]);

        $user2 = $this->createUser('user2@test.fr');
        $this->loginAs($user2, 'TestPass1!');

        $data = $this->jsonRequest('GET', '/api/games/' . $imported['id']);

        $this->assertResponseStatusCode(200);
        $this->assertSame($imported['id'], $data['id']);
    }

    public function testShowOtherPrivateGame(): void
    {
        $user1 = $this->createUser('user1@test.fr');
        $this->loginAs($user1, 'TestPass1!');
        $imported = $this->jsonRequest('POST', '/api/games/import', [
            'pgn' => self::SAMPLE_PGN,
            'isPublic' => false,
        ]);

        $user2 = $this->createUser('user2@test.fr');
        $this->loginAs($user2, 'TestPass1!');

        $this->jsonRequest('GET', '/api/games/' . $imported['id']);

        $this->assertResponseStatusCode(403);
    }

    public function testShowNonexistentGame(): void
    {
        $user = $this->createUser();
        $this->loginAs($user);

        $this->jsonRequest('GET', '/api/games/99999');

        $this->assertResponseStatusCode(404);
    }

    public function testDeleteOwnGame(): void
    {
        $imported = $this->loginAndImportGame();

        $this->jsonRequest('DELETE', '/api/games/' . $imported['id']);

        $this->assertResponseStatusCode(204);

        $this->assertNull($this->em->getRepository(Game::class)->find($imported['id']));
    }

    public function testDeleteOtherUserGame(): void
    {
        $user1 = $this->createUser('user1@test.fr');
        $this->loginAs($user1, 'TestPass1!');
        $imported = $this->jsonRequest('POST', '/api/games/import', ['pgn' => self::SAMPLE_PGN]);

        $user2 = $this->createUser('user2@test.fr');
        $this->loginAs($user2, 'TestPass1!');

        $this->jsonRequest('DELETE', '/api/games/' . $imported['id']);

        $this->assertResponseStatusCode(403);
    }

    public function testDeleteRequiresAuth(): void
    {
        $this->jsonRequest('DELETE', '/api/games/1');
        $this->assertResponseStatusCode(401);
    }
}
