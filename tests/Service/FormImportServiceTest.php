<?php

namespace App\Tests\Service;

use App\DTO\GameFormInput;
use App\Service\FormImportService;
use PHPUnit\Framework\TestCase;

class FormImportServiceTest extends TestCase
{
    private FormImportService $service;

    protected function setUp(): void
    {
        $this->service = new FormImportService();
    }

    public function testCreatesGameFromCompleteForm(): void
    {
        $input = new GameFormInput(
            moves: '1. e4 e5 2. Nf3 Nc6 1-0',
            event: 'Tournoi de Rennes',
            date: '2025-06-15',
            round: '3',
            result: '1-0',
            playerWhite: 'Dupont, Jean',
            playerBlack: 'Martin, Paul',
            whiteElo: 1800,
            blackElo: 1650,
        );

        $game = $this->service->createGameFromForm($input);

        $this->assertSame('Dupont, Jean', $game->getPlayerWhite());
        $this->assertSame('Martin, Paul', $game->getPlayerBlack());
        $this->assertSame('1-0', $game->getResult());
        $this->assertSame('Tournoi de Rennes', $game->getEvent());
        $this->assertSame('2025-06-15', $game->getDate()->format('Y-m-d'));

        $pgn = $game->getPgn();
        $this->assertStringContainsString('[White "Dupont, Jean"]', $pgn);
        $this->assertStringContainsString('[Black "Martin, Paul"]', $pgn);
        $this->assertStringContainsString('[Result "1-0"]', $pgn);
        $this->assertStringContainsString('[Event "Tournoi de Rennes"]', $pgn);
        $this->assertStringContainsString('[Date "2025.06.15"]', $pgn);
        $this->assertStringContainsString('[Round "3"]', $pgn);
        $this->assertStringContainsString('[WhiteElo "1800"]', $pgn);
        $this->assertStringContainsString('[BlackElo "1650"]', $pgn);
        $this->assertStringContainsString('1. e4 e5 2. Nf3 Nc6 1-0', $pgn);
    }

    public function testCreatesGameFromMinimalForm(): void
    {
        $input = new GameFormInput(moves: '1. d4 d5 *');

        $game = $this->service->createGameFromForm($input);

        $this->assertNull($game->getPlayerWhite());
        $this->assertNull($game->getPlayerBlack());
        $this->assertNull($game->getResult());
        $this->assertNull($game->getDate());

        $pgn = $game->getPgn();
        $this->assertStringContainsString('[White "?"]', $pgn);
        $this->assertStringContainsString('[Result "*"]', $pgn);
        $this->assertStringContainsString('1. d4 d5 *', $pgn);
        $this->assertStringNotContainsString('WhiteElo', $pgn);
    }

    public function testThrowsOnEmptyMoves(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $input = new GameFormInput(moves: '  ');
        $this->service->createGameFromForm($input);
    }
}
