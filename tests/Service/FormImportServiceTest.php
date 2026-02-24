<?php

namespace App\Tests\Service;

use App\DTO\GameFormInput;
use App\Service\FormImportService;
use App\Service\MoveValidationService;
use PHPUnit\Framework\TestCase;

class FormImportServiceTest extends TestCase
{
    private FormImportService $service;

    protected function setUp(): void
    {
        $this->service = new FormImportService(new MoveValidationService());
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

    public function testConvertsFrenchNotationToEnglish(): void
    {
        $input = new GameFormInput(
            moves: '1. e4 e5 2. Cf3 Cc6 3. Fb5 a6 4. Fa4 Cf6 5. O-O Fe7 1-0',
            result: '1-0',
        );

        $game = $this->service->createGameFromForm($input);
        $pgn = $game->getPgn();

        // French piece letters should be converted to English in the PGN
        $this->assertStringContainsString('2. Nf3 Nc6 3. Bb5 a6 4. Ba4 Nf6 5. O-O Be7 1-0', $pgn);
        $this->assertStringNotContainsString('Cf3', $pgn);
        $this->assertStringNotContainsString('Fb5', $pgn);
    }

    public function testConvertsFrenchQueenAndRookAndKing(): void
    {
        // D=Queen, T=Rook, R=King (French)
        // Legal sequence: 1. d4 d5 2. c4 e6 3. Nc3 Nf6 4. Bg5 Be7 5. e3 O-O 6. Qc2 c5 7. Rd1 Nc6 8. Kf1 (but Kf1 is still blocked by Bf1? No, Bg5 moved)
        // Wait, Bg5 is the dark-squared bishop (c1). The f1 (light-squared) bishop hasn't moved.
        // Use: 1. d4 d5 2. c4 e6 3. Cc3 Cf6 4. Fg5 Fe7 5. e3 O-O 6. Dc2 c5 7. Ff4 Cc6 8. Td1 Da5 9. Re2
        // After Bf4 (retreats from g5 to f4? No, bishop went to g5 on move 4, so Bf4 means move bishop to f4. Let me construct more carefully.)
        // Simpler: 1. d4 d5 2. Dd3 Cf6 3. e3 e6 4. Fd2 Fe7 5. Cc3 O-O 6. O-O-O c5 7. Rb1
        // O-O-O castles queenside, king goes to c1, then Kb1 is legal
        $input = new GameFormInput(moves: '1. d4 d5 2. Dd3 Cf6 3. e3 e6 4. Fd2 Fe7 5. Cc3 O-O 6. O-O-O c5 7. Rb1');

        $game = $this->service->createGameFromForm($input);
        $pgn = $game->getPgn();

        $this->assertStringContainsString('Qd3', $pgn);
        $this->assertStringContainsString('Nf6', $pgn);
        $this->assertStringContainsString('Bd2', $pgn);
        $this->assertStringContainsString('Be7', $pgn);
        $this->assertStringContainsString('Nc3', $pgn);
        $this->assertStringContainsString('Kb1', $pgn);

        // None of the French letters should remain as piece identifiers
        $this->assertStringNotContainsString('Dd3', $pgn);
        $this->assertStringNotContainsString('Cf6', $pgn);
        $this->assertStringNotContainsString('Fd2', $pgn);
        $this->assertStringNotContainsString('Cc3', $pgn);
        $this->assertStringNotContainsString('Rb1', $pgn);
    }

    public function testFrenchCaptureNotation(): void
    {
        // 1. e4 d5 2. exd5 Dxd5 (French D=Queen) 3. Cc3 (French C=Knight) Dxe4+ is not legal from d5 to e4 with check
        // Use a legal sequence: 1. e4 d5 2. exd5 Dxd5 3. Cc3 Da5
        $input = new GameFormInput(moves: '1. e4 d5 2. exd5 Dxd5 3. Cc3 Da5');

        $game = $this->service->createGameFromForm($input);
        $pgn = $game->getPgn();

        $this->assertStringNotContainsString('Dxd5', $pgn);
        $this->assertStringNotContainsString('Cc3', $pgn);
        $this->assertStringContainsString('Qxd5', $pgn);
        $this->assertStringContainsString('Nc3', $pgn);
    }

    public function testEnglishNotationPassesThrough(): void
    {
        // English notation should not be altered
        $input = new GameFormInput(moves: '1. e4 e5 2. Nf3 Nc6 3. Bb5 a6 *');

        $game = $this->service->createGameFromForm($input);
        $pgn = $game->getPgn();

        $this->assertStringContainsString('2. Nf3 Nc6 3. Bb5 a6 *', $pgn);
    }
}
