<?php

namespace App\Service;

use Onspli\Chess\PGN;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class OpeningDetectionService
{
    private const MAX_HALFMOVES = 40;

    private ?array $database = null;

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {}

    public function detectFromPgn(string $pgn): ?string
    {
        $this->loadDatabase();

        try {
            $game = new PGN($pgn);
            $game->validate_moves();
        } catch (\Exception) {
            return null;
        }

        $lastHalfmove = $game->get_last_halfmove_number();
        $firstHalfmove = $game->get_initial_halfmove_number();
        $maxHalfmove = min($lastHalfmove, $firstHalfmove + self::MAX_HALFMOVES - 1);

        $deepestMatch = null;

        for ($i = $firstHalfmove; $i <= $maxHalfmove; $i++) {
            $fen = $game->get_fen_after_halfmove($i);
            $epd = $this->fenToEpd($fen);

            if (isset($this->database['openings'][$epd])) {
                $deepestMatch = $this->database['openings'][$epd]['name'];
            }
        }

        if ($deepestMatch === null) {
            return null;
        }

        return $this->translateToFrench($deepestMatch);
    }

    private function fenToEpd(string $fen): string
    {
        $parts = explode(' ', $fen);

        return implode(' ', array_slice($parts, 0, 4));
    }

    private function loadDatabase(): void
    {
        if ($this->database !== null) {
            return;
        }

        $path = $this->projectDir . '/data/openings.json';
        $content = file_get_contents($path);
        $this->database = json_decode($content, true);
    }

    private function translateToFrench(string $name): string
    {
        $rootNames = $this->database['translations']['root_names'];
        $structuralTerms = $this->database['translations']['structural_terms'];

        $parts = explode(': ', $name, 2);
        $rootName = $parts[0];
        $variant = $parts[1] ?? null;

        if (!isset($rootNames[$rootName]) && $variant === null && str_contains($rootName, ', ')) {
            $commaParts = explode(', ', $rootName, 2);
            if (isset($rootNames[$commaParts[0]])) {
                $rootName = $commaParts[0];
                $variant = $commaParts[1];
            }
        }

        $translatedRoot = $rootNames[$rootName] ?? $this->translateSubPart($rootName, $structuralTerms);

        if ($variant === null) {
            return $translatedRoot;
        }

        $translatedVariant = $this->translateVariantParts($variant, $structuralTerms);

        return $translatedRoot . ', ' . $translatedVariant;
    }

    private function translateVariantParts(string $variant, array $structuralTerms): string
    {
        $subParts = explode(', ', $variant);
        $translated = [];

        foreach ($subParts as $subPart) {
            $translated[] = $this->translateSubPart($subPart, $structuralTerms);
        }

        return implode(', ', $translated);
    }

    private function translateSubPart(string $text, array $structuralTerms): string
    {
        foreach ($structuralTerms as $english => $french) {
            if (str_contains($text, $english)) {
                $remainder = trim(str_replace($english, '', $text));
                if ($remainder !== '') {
                    return $french . ' ' . $remainder;
                }
                return $french;
            }
        }

        return $text;
    }
}
