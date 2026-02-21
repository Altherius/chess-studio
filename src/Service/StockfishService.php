<?php

namespace App\Service;

use Psr\Log\LoggerInterface;

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
        return $this->analyzeGame([$fen], $depth)[0];
    }

    /**
     * Analyze all positions from a PGN's move list (as array of FEN strings).
     * Uses a single Stockfish process for all positions.
     *
     * @param string[] $positions Array of FEN strings for each position
     * @return array<int, array{score: string, bestMove: string, pv: string}>
     */
    public function analyzeGame(array $positions, int $depth = 20): array
    {
        $descriptors = [
            0 => ['pipe', 'r'], // stdin
            1 => ['pipe', 'w'], // stdout
            2 => ['pipe', 'w'], // stderr
        ];

        $process = proc_open($this->stockfishPath, $descriptors, $pipes);

        if (!is_resource($process)) {
            throw new \RuntimeException('Failed to start Stockfish process');
        }

        stream_set_blocking($pipes[1], false);

        try {
            // UCI handshake
            $this->writeLine($pipes[0], 'uci');
            $this->readUntil($pipes[1], 'uciok');

            $this->writeLine($pipes[0], 'isready');
            $this->readUntil($pipes[1], 'readyok');

            $results = [];

            foreach ($positions as $index => $fen) {
                $this->writeLine($pipes[0], "position fen $fen");
                $this->writeLine($pipes[0], "go depth $depth");

                $output = $this->readUntil($pipes[1], 'bestmove');

                $this->logger?->debug('Stockfish output', ['fen' => $fen, 'output' => $output]);

                $blackToMove = str_contains($fen, ' b ');
                $results[$index] = $this->parseOutput($output, $blackToMove);
            }

            $this->writeLine($pipes[0], 'quit');
        } finally {
            fclose($pipes[0]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);
        }

        return $results;
    }

    private function writeLine($pipe, string $command): void
    {
        fwrite($pipe, $command . "\n");
        fflush($pipe);
    }

    /**
     * Read stdout until a line starting with $needle appears.
     * Returns all accumulated output including the matching line.
     */
    private function readUntil($pipe, string $needle, int $timeoutSeconds = 120): string
    {
        $output = '';
        $deadline = microtime(true) + $timeoutSeconds;

        while (microtime(true) < $deadline) {
            $line = fgets($pipe);

            if ($line === false) {
                usleep(5_000); // 5ms
                continue;
            }

            $output .= $line;

            if (str_starts_with(trim($line), $needle)) {
                return $output;
            }
        }

        $this->logger?->error('Stockfish read timeout', ['needle' => $needle, 'output' => $output]);
        throw new \RuntimeException("Stockfish timeout waiting for '$needle'");
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
