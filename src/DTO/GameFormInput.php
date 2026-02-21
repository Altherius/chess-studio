<?php

namespace App\DTO;

class GameFormInput
{
    public function __construct(
        public readonly string $moves,
        public readonly ?string $event = null,
        public readonly ?string $date = null,
        public readonly ?string $round = null,
        public readonly ?string $result = null,
        public readonly ?string $playerWhite = null,
        public readonly ?string $playerBlack = null,
        public readonly ?int $whiteElo = null,
        public readonly ?int $blackElo = null,
    ) {}
}
