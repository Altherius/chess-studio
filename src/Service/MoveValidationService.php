<?php

namespace App\Service;

use Onspli\Chess\PGN;

class MoveValidationService
{
    private const ENGLISH_TO_FRENCH = [
        'N' => 'C',
        'B' => 'F',
        'R' => 'T',
        'Q' => 'D',
        'K' => 'R',
    ];

    /**
     * Validate that all moves in the PGN are legal chess moves.
     *
     * @throws \InvalidArgumentException with a French error message on illegal move
     */
    public function validateMoves(string $pgn, bool $useFrenchNotation = false): void
    {
        try {
            $parsed = new PGN($pgn);
            $parsed->validate_moves();
        } catch (\Exception $e) {
            $message = $e->getMessage();

            // Match: "Move 3. Nf3 is invalid. FEN ..."
            // or:    "Move 1... d4 is invalid. FEN ..."
            if (preg_match('/^Move (\d+)(\.{3}|\.) (.+?) is invalid/', $message, $m)) {
                $moveNumber = $m[1];
                $isBlack = $m[2] === '...';
                $move = $m[3];

                if ($useFrenchNotation) {
                    $move = $this->englishToFrenchNotation($move);
                }

                if ($isBlack) {
                    $formatted = sprintf('Séquence de coups illégale. %s. ... %s n\'est pas un coup légal', $moveNumber, $move);
                } else {
                    $formatted = sprintf('Séquence de coups illégale. %s. %s n\'est pas un coup légal', $moveNumber, $move);
                }

                throw new \InvalidArgumentException($formatted, 0, $e);
            }

            throw new \InvalidArgumentException('PGN invalide : ' . $message, 0, $e);
        }
    }

    private function englishToFrenchNotation(string $san): string
    {
        return preg_replace_callback(
            '/[NBRQK]/',
            fn(array $m) => self::ENGLISH_TO_FRENCH[$m[0]] ?? $m[0],
            $san,
        );
    }
}
