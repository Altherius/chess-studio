<?php

namespace App\Message;

class AnalyzeGameMessage
{
    public function __construct(
        private int $gameId,
        private int $depth = 20,
    ) {}

    public function getGameId(): int
    {
        return $this->gameId;
    }

    public function getDepth(): int
    {
        return $this->depth;
    }
}
