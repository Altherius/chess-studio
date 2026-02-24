<?php

namespace App\Tests\Service;

use App\Service\MoveValidationService;
use App\Service\PgnImportService;
use PHPUnit\Framework\TestCase;

class PgnImportServiceTest extends TestCase
{
    private PgnImportService $service;

    protected function setUp(): void
    {
        $this->service = new PgnImportService(new MoveValidationService());
    }

    public function testCreatesGameFromCompletePgn(): void
    {
        $pgn = <<<PGN
[Event "Tournoi de Rennes"]
[Site "Rennes"]
[Date "2025.06.15"]
[Round "3"]
[White "Dupont, Jean"]
[Black "Martin, Paul"]
[Result "1-0"]

1. e4 e5 2. Nf3 Nc6 3. Bb5 a6 1-0
PGN;

        $game = $this->service->createGameFromPgn($pgn);

        $this->assertSame($pgn, $game->getPgn());
        $this->assertSame('Dupont, Jean', $game->getPlayerWhite());
        $this->assertSame('Martin, Paul', $game->getPlayerBlack());
        $this->assertSame('1-0', $game->getResult());
        $this->assertSame('Tournoi de Rennes', $game->getEvent());
        $this->assertSame('2025-06-15', $game->getDate()->format('Y-m-d'));
    }

    public function testCreatesGameFromMinimalPgn(): void
    {
        $pgn = '1. e4 e5 2. Nf3 Nc6 *';

        $game = $this->service->createGameFromPgn($pgn);

        $this->assertSame($pgn, $game->getPgn());
        $this->assertNull($game->getPlayerWhite());
        $this->assertNull($game->getPlayerBlack());
        $this->assertNull($game->getResult());
        $this->assertNull($game->getEvent());
        $this->assertNull($game->getDate());
    }

    public function testThrowsOnEmptyPgn(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service->createGameFromPgn('   ');
    }
}
