<?php

namespace App\Service;

use App\Entity\Game;

class PgnImportService
{
    public function __construct(
        private readonly MoveValidationService $moveValidationService,
    ) {}

    public function createGameFromPgn(string $pgn): Game
    {
        $pgn = trim($pgn);

        if ($pgn === '') {
            throw new \InvalidArgumentException('PGN content is empty.');
        }

        $this->moveValidationService->validateMoves($pgn);

        $game = new Game();
        $game->setPgn($pgn);

        if (preg_match('/\[White "(.+?)"\]/', $pgn, $m)) {
            $game->setPlayerWhite($m[1]);
        }
        if (preg_match('/\[Black "(.+?)"\]/', $pgn, $m)) {
            $game->setPlayerBlack($m[1]);
        }
        if (preg_match('/\[Result "(.+?)"\]/', $pgn, $m)) {
            $game->setResult($m[1]);
        }
        if (preg_match('/\[Event "(.+?)"\]/', $pgn, $m)) {
            $game->setEvent($m[1]);
        }
        if (preg_match('/\[Date "(\d{4}\.\d{2}\.\d{2})"\]/', $pgn, $m)) {
            $dateStr = str_replace('.', '-', $m[1]);
            $game->setDate(new \DateTime($dateStr));
        }
        if (preg_match('/\[WhiteElo "(\d+)"\]/', $pgn, $m)) {
            $game->setWhiteElo((int) $m[1]);
        }
        if (preg_match('/\[BlackElo "(\d+)"\]/', $pgn, $m)) {
            $game->setBlackElo((int) $m[1]);
        }
        if (preg_match('/\[Round "(.+?)"\]/', $pgn, $m)) {
            $game->setRound($m[1] !== '?' ? $m[1] : null);
        }

        return $game;
    }

    /**
     * @param array<string, string|int|null> $headers Map of PGN tag names to new values
     */
    public function updatePgnHeaders(string $pgn, array $headers): string
    {
        $mandatoryDefaults = [
            'White' => '?',
            'Black' => '?',
            'Result' => '*',
            'Event' => '?',
            'Date' => '????.??.??',
        ];

        foreach ($headers as $tag => $value) {
            $pattern = '/\[' . preg_quote($tag, '/') . ' ".*?"\]\n?/';

            if ($value === null) {
                if (isset($mandatoryDefaults[$tag])) {
                    $replacement = sprintf('[%s "%s"]' . "\n", $tag, $mandatoryDefaults[$tag]);
                    $pgn = preg_replace($pattern, $replacement, $pgn);
                } else {
                    $pgn = preg_replace($pattern, '', $pgn);
                }
                continue;
            }

            $escaped = $this->escapePgnValue((string) $value);

            if (preg_match($pattern, $pgn)) {
                $replacement = sprintf('[%s "%s"]' . "\n", $tag, $escaped);
                $pgn = preg_replace($pattern, $replacement, $pgn);
            } else {
                $insertPos = $this->findHeaderInsertPosition($pgn);
                $newHeader = sprintf('[%s "%s"]' . "\n", $tag, $escaped);
                $pgn = substr($pgn, 0, $insertPos) . $newHeader . substr($pgn, $insertPos);
            }
        }

        return $pgn;
    }

    private function escapePgnValue(string $value): string
    {
        return str_replace(['\\', '"'], ['\\\\', '\\"'], $value);
    }

    private function findHeaderInsertPosition(string $pgn): int
    {
        if (preg_match_all('/\[[A-Za-z]+ ".*?"\]\s*\n/', $pgn, $matches, PREG_OFFSET_CAPTURE)) {
            $last = end($matches[0]);
            return $last[1] + strlen($last[0]);
        }

        return 0;
    }
}
