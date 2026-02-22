<?php

namespace App\Service;

use App\DTO\GameFormInput;
use App\Entity\Game;

class FormImportService
{
    private const FRENCH_TO_ENGLISH = [
        'C' => 'N',
        'F' => 'B',
        'T' => 'R',
        'D' => 'Q',
        'R' => 'K',
    ];

    public function createGameFromForm(GameFormInput $input): Game
    {
        $moves = trim($input->moves);

        if ($moves === '') {
            throw new \InvalidArgumentException('Moves are required.');
        }

        $moves = $this->frenchToEnglishNotation($moves);
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

        $game->setWhiteElo($input->whiteElo);
        $game->setBlackElo($input->blackElo);
        $game->setRound($input->round);

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

    /**
     * Convert French algebraic notation to English (standard PGN).
     *
     * Replaces piece letters C→N, F→B, T→R, D→Q, R→K only when they appear
     * as piece identifiers (uppercase letter followed by a file letter a-h,
     * a capture "x", or a rank digit for disambiguation).
     */
    private function frenchToEnglishNotation(string $moves): string
    {
        return preg_replace_callback(
            '/\b([CFTDR])([a-hx1-8])/',
            fn(array $m) => (self::FRENCH_TO_ENGLISH[$m[1]] ?? $m[1]) . $m[2],
            $moves,
        );
    }

    private function escape(string $value): string
    {
        return str_replace(['\\', '"'], ['\\\\', '\\"'], $value);
    }
}
