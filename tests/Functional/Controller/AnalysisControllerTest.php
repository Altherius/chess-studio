<?php

namespace App\Tests\Functional\Controller;

use App\Entity\Analysis;
use App\Tests\Functional\ApiTestCase;

class AnalysisControllerTest extends ApiTestCase
{
    private const SAMPLE_PGN = '[Event "Test"]
[White "White"]
[Black "Black"]
[Result "1-0"]

1. e4 e5 2. Nf3 Nc6 1-0';

    private function importGame(): int
    {
        $user = $this->createUser();
        $this->loginAs($user);

        $data = $this->jsonRequest('POST', '/api/games/import', [
            'pgn' => self::SAMPLE_PGN,
        ]);

        return $data['id'];
    }

    public function testTriggerAnalysis(): void
    {
        $gameId = $this->importGame();

        $data = $this->jsonRequest('POST', '/api/analysis/game/' . $gameId, [
            'depth' => 15,
        ]);

        $this->assertResponseStatusCode(202);
        $this->assertSame('Analyse commencée', $data['message']);
        $this->assertSame($gameId, $data['gameId']);
        $this->assertSame(15, $data['depth']);
    }

    public function testResultsWithAnalyses(): void
    {
        $gameId = $this->importGame();

        $game = $this->em->getRepository(\App\Entity\Game::class)->find($gameId);
        $analysis = new Analysis();
        $analysis->setGame($game);
        $analysis->setDepth(20);
        $analysis->setStatus(Analysis::STATUS_COMPLETED);
        $analysis->setEvaluation(['0.35', '0.20']);
        $analysis->setBestMoves(['e2e4', 'e7e5']);
        $this->em->persist($analysis);
        $this->em->flush();

        $data = $this->jsonRequest('GET', '/api/analysis/game/' . $gameId);

        $this->assertResponseStatusCode(200);
        $this->assertCount(1, $data);
        $this->assertSame(Analysis::STATUS_COMPLETED, $data[0]['status']);
        $this->assertSame(['0.35', '0.20'], $data[0]['evaluation']);
    }

    public function testResultsEmpty(): void
    {
        $gameId = $this->importGame();

        $data = $this->jsonRequest('GET', '/api/analysis/game/' . $gameId);

        $this->assertResponseStatusCode(200);
        $this->assertCount(0, $data);
    }

    public function testAnalysisRequiresAuth(): void
    {
        $this->jsonRequest('POST', '/api/analysis/game/1');

        $this->assertResponseStatusCode(401);
    }
}
