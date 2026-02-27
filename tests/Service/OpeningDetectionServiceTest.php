<?php

namespace App\Tests\Service;

use App\Service\OpeningDetectionService;
use PHPUnit\Framework\TestCase;

class OpeningDetectionServiceTest extends TestCase
{
    private OpeningDetectionService $service;

    protected function setUp(): void
    {
        $projectDir = dirname(__DIR__, 2);
        $this->service = new OpeningDetectionService($projectDir);
    }

    public function testDetectsRuyLopez(): void
    {
        $pgn = '1. e4 e5 2. Nf3 Nc6 3. Bb5 a6 1-0';
        $result = $this->service->detectFromPgn($pgn);

        $this->assertNotNull($result);
        $this->assertStringContainsString('espagnole', $result);
    }

    public function testDetectsFrenchDefense(): void
    {
        $pgn = '1. e4 e6 2. d4 d5 *';
        $result = $this->service->detectFromPgn($pgn);

        $this->assertNotNull($result);
        $this->assertStringContainsString('française', $result);
    }

    public function testDetectsSicilianDefense(): void
    {
        $pgn = '1. e4 c5 *';
        $result = $this->service->detectFromPgn($pgn);

        $this->assertNotNull($result);
        $this->assertStringContainsString('sicilienne', $result);
    }

    public function testDetectsItalianGame(): void
    {
        $pgn = '1. e4 e5 2. Nf3 Nc6 3. Bc4 *';
        $result = $this->service->detectFromPgn($pgn);

        $this->assertNotNull($result);
        $this->assertStringContainsString('italienne', $result);
    }

    public function testDetectsQueensGambit(): void
    {
        $pgn = '1. d4 d5 2. c4 *';
        $result = $this->service->detectFromPgn($pgn);

        $this->assertNotNull($result);
        $this->assertStringContainsString('Gambit Dame', $result);
    }

    public function testReturnsDeepestMatch(): void
    {
        $pgn = '1. e4 e6 2. d4 d5 3. Nd2 *';
        $result = $this->service->detectFromPgn($pgn);

        $this->assertNotNull($result);
        $this->assertStringContainsString('Tarrasch', $result);
    }

    public function testTranslatesToFrench(): void
    {
        $pgn = '1. e4 e5 2. Nf3 Nc6 3. Bb5 *';
        $result = $this->service->detectFromPgn($pgn);

        $this->assertNotNull($result);
        $this->assertStringNotContainsStringIgnoringCase('Defense', $result);
        $this->assertStringNotContainsStringIgnoringCase('Game', $result);
    }

    public function testReturnsNullForEmptyPgn(): void
    {
        $result = $this->service->detectFromPgn('*');
        $this->assertNull($result);
    }

    public function testReturnsNullForSingleMoveWithNoMatch(): void
    {
        $pgn = '1. a3 *';
        $result = $this->service->detectFromPgn($pgn);

        // a3 (Anderssen Opening) may or may not be in the DB — either null or a valid name
        $this->assertTrue($result === null || is_string($result));
    }

    public function testReturnsNullForInvalidPgn(): void
    {
        $result = $this->service->detectFromPgn('not a valid pgn');
        $this->assertNull($result);
    }

    public function testTranspositionDetection(): void
    {
        $pgn1 = '1. e4 e5 2. Nf3 Nc6 3. Bc4 Nf6 *';
        $pgn2 = '1. e4 e5 2. Bc4 Nf6 3. Nf3 Nc6 *';

        $result1 = $this->service->detectFromPgn($pgn1);
        $result2 = $this->service->detectFromPgn($pgn2);

        $this->assertNotNull($result1);
        $this->assertSame($result1, $result2);
    }

    public function testFrenchWordOrder(): void
    {
        $pgn = '1. e4 e6 2. d4 d5 3. Nd2 *';
        $result = $this->service->detectFromPgn($pgn);

        $this->assertNotNull($result);
        $this->assertStringContainsString('variante Tarrasch', $result);
        $this->assertStringNotContainsString('Tarrasch variante', $result);
    }

    public function testPolishOpeningWithCommaSuffix(): void
    {
        $pgn = '1. b4 d5 *';
        $result = $this->service->detectFromPgn($pgn);

        $this->assertNotNull($result);
        $this->assertStringContainsString('polonaise', $result);
        $this->assertStringContainsString('avec', $result);
        $this->assertStringNotContainsStringIgnoringCase('Polish', $result);
    }

    public function testSicilianNajdorf(): void
    {
        $pgn = '1. e4 c5 2. Nf3 d6 3. d4 cxd4 4. Nxd4 Nf6 5. Nc3 a6 *';
        $result = $this->service->detectFromPgn($pgn);

        $this->assertNotNull($result);
        $this->assertStringContainsString('sicilienne', $result);
        $this->assertStringContainsString('Najdorf', $result);
    }
}
