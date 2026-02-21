<?php

namespace App\Tests\Service;

use App\Service\StockfishService;
use PHPUnit\Framework\TestCase;

class StockfishServiceTest extends TestCase
{
    public function testParseOutputExtractsScoreAndBestMove(): void
    {
        // Real Stockfish output for starting position at depth 10
        $output = <<<'OUTPUT'
Stockfish 16 by T. Romstad, M. Costalba, J. Kiiski, G. Linscott
id name Stockfish 16
id author T. Romstad, M. Costalba, J. Kiiski, G. Linscott
uciok
info string NNUE evaluation using nn-5af11540bbfe.nnue
info depth 1 seldepth 1 multipv 1 score cp 44 nodes 20 nps 10000 time 2 pv e2e4
info depth 2 seldepth 2 multipv 1 score cp 30 nodes 62 nps 31000 time 2 pv d2d4 d7d5
info depth 10 seldepth 13 multipv 1 score cp 29 nodes 10432 nps 521600 time 20 pv e2e4 e7e5 g1f3 b8c6 f1b5 a7a6 b5a4 g8f6
bestmove e2e4 ponder e7e5
OUTPUT;

        $service = new StockfishService('/nonexistent');

        // Use reflection to test private parseOutput
        $method = new \ReflectionMethod($service, 'parseOutput');

        $result = $method->invoke($service, $output, false);

        $this->assertSame('0.29', $result['score']);
        $this->assertSame('e2e4', $result['bestMove']);
        $this->assertStringContainsString('e2e4', $result['pv']);
    }

    public function testParseOutputNegatesScoreForBlack(): void
    {
        $output = <<<'OUTPUT'
info depth 10 seldepth 12 multipv 1 score cp 35 nodes 8000 nps 400000 time 20 pv e7e5 g1f3
bestmove e7e5 ponder g1f3
OUTPUT;

        $service = new StockfishService('/nonexistent');
        $method = new \ReflectionMethod($service, 'parseOutput');

        $result = $method->invoke($service, $output, true);

        // Black to move: score should be negated (35cp from black's POV = -0.35 from white's POV)
        $this->assertSame('-0.35', $result['score']);
        $this->assertSame('e7e5', $result['bestMove']);
    }

    public function testParseOutputHandlesMate(): void
    {
        $output = <<<'OUTPUT'
info depth 5 seldepth 3 multipv 1 score mate 2 nodes 500 nps 250000 time 2 pv d1h5 e8e7 h5f7
bestmove d1h5 ponder e8e7
OUTPUT;

        $service = new StockfishService('/nonexistent');
        $method = new \ReflectionMethod($service, 'parseOutput');

        $result = $method->invoke($service, $output, false);

        $this->assertSame('M2', $result['score']);
        $this->assertSame('d1h5', $result['bestMove']);
    }

    public function testParseOutputHandlesEmptyOutput(): void
    {
        $service = new StockfishService('/nonexistent');
        $method = new \ReflectionMethod($service, 'parseOutput');

        $result = $method->invoke($service, '', false);

        $this->assertSame('', $result['score']);
        $this->assertSame('', $result['bestMove']);
        $this->assertSame('', $result['pv']);
    }
}
