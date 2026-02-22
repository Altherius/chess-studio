<?php

namespace App\Service;

use App\Entity\Game;

class PgnImportService
{
    public function createGameFromPgn(string $pgn): Game
    {
        $pgn = trim($pgn);

        if ($pgn === '') {
            throw new \InvalidArgumentException('PGN content is empty.');
        }

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
}
