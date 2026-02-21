<?php

namespace App\Service;

use App\DTO\GameFormInput;
use App\Entity\Game;

class FormImportService
{
    public function createGameFromForm(GameFormInput $input): Game
    {
        $moves = trim($input->moves);

        if ($moves === '') {
            throw new \InvalidArgumentException('Moves are required.');
        }

        $pgn = $this->buildPgn($input, $moves);

        $game = new Game();
        $game->setPgn($pgn);
        $game->setPlayerWhite($input->playerWhite);
        $game->setPlayerBlack($input->playerBlack);
        $game->setResult($input->result);
        $game->setEvent($input->event);

        if ($input->date !== null && $input->date !== '') {
            $game->setDate(new \DateTime($input->date));
        }

        return $game;
    }

    private function buildPgn(GameFormInput $input, string $moves): string
    {
        $headers = [];

        $headers[] = sprintf('[Event "%s"]', $this->escape($input->event ?? '?'));
        $headers[] = sprintf('[Site "?"]');
        $headers[] = sprintf('[Date "%s"]', $input->date ? str_replace('-', '.', $input->date) : '????.??.??');
        $headers[] = sprintf('[Round "%s"]', $this->escape($input->round ?? '?'));
        $headers[] = sprintf('[White "%s"]', $this->escape($input->playerWhite ?? '?'));
        $headers[] = sprintf('[Black "%s"]', $this->escape($input->playerBlack ?? '?'));
        $headers[] = sprintf('[Result "%s"]', $this->escape($input->result ?? '*'));

        if ($input->whiteElo !== null) {
            $headers[] = sprintf('[WhiteElo "%d"]', $input->whiteElo);
        }
        if ($input->blackElo !== null) {
            $headers[] = sprintf('[BlackElo "%d"]', $input->blackElo);
        }

        return implode("\n", $headers) . "\n\n" . $moves;
    }

    private function escape(string $value): string
    {
        return str_replace(['\\', '"'], ['\\\\', '\\"'], $value);
    }
}
