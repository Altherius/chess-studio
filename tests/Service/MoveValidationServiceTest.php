<?php

namespace App\Tests\Service;

use App\Service\MoveValidationService;
use PHPUnit\Framework\TestCase;

class MoveValidationServiceTest extends TestCase
{
    private MoveValidationService $service;

    protected function setUp(): void
    {
        $this->service = new MoveValidationService();
    }

    public function testLegalGamePassesValidation(): void
    {
        $pgn = '[Event "?"]' . "\n" . '[Result "*"]' . "\n\n" . '1. e4 e5 2. Nf3 Nc6 3. Bb5 a6 *';

        $this->service->validateMoves($pgn);
        $this->addToAssertionCount(1);
    }

    public function testIllegalBlackMoveThrows(): void
    {
        // d4 is not a legal reply to 1. e4 (d5 would be, but d4 is two squares forward and blocked)
        // Actually d4 IS a legal move for black (d7-d5 is legal, but d7-d4 is not — pawns can't move 3 squares)
        // Wait — "1. e4 d4" means black plays d7-d4? That's not legal (pawn can move 1 or 2 squares from start, d7-d5 is max)
        // Actually "d4" in SAN for black means pawn to d4. From d7, that's 3 squares — illegal. But wait,
        // the SAN "d4" is ambiguous: it could be d7-d4 or d6-d4 etc. But from the starting position d7,
        // no pawn can reach d4 in one move. So this should be invalid.
        // Let me use a clearer illegal move: 1. e4 e4 (e7-e4 is not possible)
        $pgn = '1. e4 e4';

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Séquence de coups illégale\. 1\. \.\.\. e4 n\'est pas un coup légal/');

        $this->service->validateMoves($pgn);
    }

    public function testIllegalWhiteMoveThrows(): void
    {
        // 1. d4 e6 2. e4 Bb4+ 3. Nf3 — Nf3 doesn't block the check from Bb4
        $pgn = '1. d4 e6 2. e4 Bb4+ 3. Nf3';

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Séquence de coups illégale\. 3\. Nf3 n\'est pas un coup légal/');

        $this->service->validateMoves($pgn);
    }

    public function testFrenchNotationInErrorMessage(): void
    {
        // Same illegal position but request French notation in error
        $pgn = '1. d4 e6 2. e4 Bb4+ 3. Nf3';

        try {
            $this->service->validateMoves($pgn, useFrenchNotation: true);
            $this->fail('Expected InvalidArgumentException');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('Cf3', $e->getMessage());
            $this->assertStringNotContainsString('Nf3', $e->getMessage());
        }
    }

    public function testInvalidPgnSyntaxThrows(): void
    {
        $pgn = '1. e4 e5 2. ??? Nc6';

        $this->expectException(\InvalidArgumentException::class);

        $this->service->validateMoves($pgn);
    }
}
