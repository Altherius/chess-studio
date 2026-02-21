<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Process;

class StockfishService
{
    private string $stockfishPath;
    private ?LoggerInterface $logger;

    public function __construct(
        string $stockfishPath = '/usr/games/stockfish',
        ?LoggerInterface $logger = null,
    ) {
        $this->stockfishPath = $stockfishPath;
        $this->logger = $logger;
    }

    /**
     * Analyze a position given as FEN string.
     * Scores are normalized to white's perspective.
     *
     * @return array{score: string, bestMove: string, pv: string}
     */
    public function analyze(string $fen, int $depth = 20): array
    {
        // Send commands without quit — Stockfish will exit when stdin is closed (EOF).
        $commands = "uci\nposition fen $fen\ngo depth $depth\n";

        $process = new Process([$this->stockfishPath]);
        $process->setInput($commands);
        $process->setTimeout(120);

        $fullOutput = '';

        $process->start();

        // Wait until bestmove appears in output, then stop the process
        while ($process->isRunning()) {
            $fullOutput .= $process->getIncrementalOutput();
            if (str_contains($fullOutput, 'bestmove')) {
                $process->stop(2);
                break;
            }
            usleep(10_000); // 10ms
        }

        // Capture any remaining output if process ended on its own
        if (!str_contains($fullOutput, 'bestmove')) {
            $fullOutput .= $process->getOutput();
        }

        $this->logger?->debug('Stockfish raw output', ['fen' => $fen, 'output' => $fullOutput]);

        if (!str_contains($fullOutput, 'bestmove')) {
            $this->logger?->error('Stockfish produced no bestmove', [
                'fen' => $fen,
                'exitCode' => $process->getExitCode(),
                'stderr' => $process->getErrorOutput(),
                'output' => $fullOutput,
            ]);
            throw new \RuntimeException('Stockfish produced no bestmove for position: ' . $fen);
        }

        $blackToMove = str_contains($fen, ' b ');

        return $this->parseOutput($fullOutput, $blackToMove);
    }

    /**
     * Analyze all positions from a PGN's move list (as array of FEN strings).
     *
     * @param string[] $positions Array of FEN strings for each position
     * @return array<int, array{score: string, bestMove: string, pv: string}>
     */
    public function analyzeGame(array $positions, int $depth = 20): array
    {
        $results = [];

        foreach ($positions as $index => $fen) {
            $results[$index] = $this->analyze($fen, $depth);
        }

        return $results;
    }

    private function parseOutput(string $output, bool $blackToMove = false): array
    {
        $lines = explode("\n", $output);
        $score = '';
        $bestMove = '';
        $pv = '';

        foreach ($lines as $line) {
            if (str_contains($line, 'bestmove')) {
                $parts = explode(' ', $line);
                $bestMove = $parts[1] ?? '';
            }

            if (str_contains($line, 'score cp')) {
                preg_match('/score cp (-?\d+)/', $line, $matches);
                if ($matches) {
                    $raw = (int) $matches[1];
                    $score = (string) (($blackToMove ? -$raw : $raw) / 100);
                }
            } elseif (str_contains($line, 'score mate')) {
                preg_match('/score mate (-?\d+)/', $line, $matches);
                if ($matches) {
                    $raw = (int) $matches[1];
                    $score = 'M' . ($blackToMove ? -$raw : $raw);
                }
            }

            if (preg_match('/\bpv\b (.+)$/', $line, $matches)) {
                $pv = $matches[1];
            }
        }

        return [
            'score' => $score,
            'bestMove' => $bestMove,
            'pv' => $pv,
        ];
    }
}
