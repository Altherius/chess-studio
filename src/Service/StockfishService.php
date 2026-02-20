<?php

namespace App\Service;

use Symfony\Component\Process\Process;

class StockfishService
{
    private string $stockfishPath;

    public function __construct(string $stockfishPath = '/usr/games/stockfish')
    {
        $this->stockfishPath = $stockfishPath;
    }

    /**
     * Analyze a position given as FEN string.
     *
     * @return array{score: string, bestMove: string, pv: string}
     */
    public function analyze(string $fen, int $depth = 20): array
    {
        $commands = implode("\n", [
            'uci',
            'isready',
            "position fen $fen",
            "go depth $depth",
            'quit',
        ]);

        $process = new Process([$this->stockfishPath]);
        $process->setInput($commands);
        $process->setTimeout(60);
        $process->run();

        $output = $process->getOutput();

        return $this->parseOutput($output);
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

    private function parseOutput(string $output): array
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
                    $score = (string) ((int) $matches[1] / 100);
                }
            } elseif (str_contains($line, 'score mate')) {
                preg_match('/score mate (-?\d+)/', $line, $matches);
                if ($matches) {
                    $score = 'M' . $matches[1];
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
