<?php

namespace App\Command;

use Onspli\Chess\PGN;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'app:generate-openings-data',
    description: 'Génère data/openings.json à partir des données lichess chess-openings',
)]
class GenerateOpeningsDataCommand extends Command
{
    private const TSV_BASE_URL = 'https://raw.githubusercontent.com/lichess-org/chess-openings/master/';
    private const TSV_FILES = ['a.tsv', 'b.tsv', 'c.tsv', 'd.tsv', 'e.tsv'];

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $openings = [];

        foreach (self::TSV_FILES as $file) {
            $io->info("Téléchargement de {$file}...");
            $content = file_get_contents(self::TSV_BASE_URL . $file);

            if ($content === false) {
                $io->error("Impossible de télécharger {$file}");
                return Command::FAILURE;
            }

            $lines = explode("\n", $content);
            array_shift($lines);

            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }

                $parts = explode("\t", $line);
                if (count($parts) < 3) {
                    continue;
                }

                [$eco, $name, $pgn] = $parts;

                try {
                    $epd = $this->computeEpd($pgn);
                } catch (\Exception $e) {
                    $io->warning("Impossible de traiter '{$name}': {$e->getMessage()}");
                    continue;
                }

                $openings[$epd] = [
                    'eco' => $eco,
                    'name' => $name,
                ];
            }
        }

        $io->info(sprintf('%d ouvertures chargées.', count($openings)));

        $data = [
            'openings' => $openings,
            'translations' => $this->getTranslations(),
        ];

        $outputPath = $this->projectDir . '/data/openings.json';
        file_put_contents($outputPath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $io->success("Fichier généré : {$outputPath}");

        return Command::SUCCESS;
    }

    private function computeEpd(string $pgn): string
    {
        $game = new PGN($pgn);
        $game->validate_moves();
        $lastHalfmove = $game->get_last_halfmove_number();
        $fen = $game->get_fen_after_halfmove($lastHalfmove);

        return $this->fenToEpd($fen);
    }

    private function fenToEpd(string $fen): string
    {
        $parts = explode(' ', $fen);

        return implode(' ', array_slice($parts, 0, 4));
    }

    private function getTranslations(): array
    {
        return [
            'root_names' => [
                "Alekhine's Defense" => "Défense Alekhine",
                "Benoni Defense" => "Défense Benoni",
                "Bird Opening" => "Ouverture Bird",
                "Bishop's Opening" => "Ouverture du Fou",
                "Blackmar-Diemer Gambit" => "Gambit Blackmar-Diemer",
                "Bogo-Indian Defense" => "Défense Bogo-indienne",
                "Budapest Defense" => "Défense Budapest",
                "Caro-Kann Defense" => "Défense Caro-Kann",
                "Catalan Opening" => "Ouverture catalane",
                "Dutch Defense" => "Défense hollandaise",
                "English Opening" => "Ouverture anglaise",
                "Englund Gambit" => "Gambit Englund",
                "Four Knights Game" => "Partie des quatre cavaliers",
                "French Defense" => "Défense française",
                "Giuoco Piano" => "Giuoco Piano",
                "Grob Opening" => "Ouverture Grob",
                "Gruenfeld Defense" => "Défense Grünfeld",
                "Grünfeld Defense" => "Défense Grünfeld",
                "Hungarian Opening" => "Ouverture hongroise",
                "Indian Defense" => "Défense indienne",
                "Indian Game" => "Partie indienne",
                "Italian Game" => "Partie italienne",
                "King's Gambit" => "Gambit du Roi",
                "King's Indian Attack" => "Attaque est-indienne",
                "King's Indian Defense" => "Défense est-indienne",
                "King's Pawn Game" => "Partie du pion Roi",
                "Larsen's Opening" => "Ouverture Larsen",
                "London System" => "Système de Londres",
                "Modern Defense" => "Défense moderne",
                "Nimzo-Indian Defense" => "Défense Nimzo-indienne",
                "Nimzowitsch Defense" => "Défense Nimzowitsch",
                "Old Benoni" => "Vieux Benoni",
                "Old Indian Defense" => "Vieille défense indienne",
                "Owen's Defense" => "Défense Owen",
                "Petrov's Defense" => "Défense Petrov",
                "Philidor Defense" => "Défense Philidor",
                "Pirc Defense" => "Défense Pirc",
                "Polish Opening" => "Ouverture polonaise",
                "Queen's Gambit Accepted" => "Gambit Dame accepté",
                "Queen's Gambit Declined" => "Gambit Dame décliné",
                "Queen's Gambit" => "Gambit Dame",
                "Queen's Indian Defense" => "Défense ouest-indienne",
                "Queen's Pawn Game" => "Partie du pion Dame",
                "Réti Opening" => "Ouverture Réti",
                "Reti Opening" => "Ouverture Réti",
                "Richter-Veresov Attack" => "Attaque Richter-Veresov",
                "Robatsch Defense" => "Défense Robatsch",
                "Ruy Lopez" => "Partie espagnole",
                "Scandinavian Defense" => "Défense scandinave",
                "Scotch Game" => "Partie écossaise",
                "Semi-Slav Defense" => "Défense semi-slave",
                "Sicilian Defense" => "Défense sicilienne",
                "Slav Defense" => "Défense slave",
                "Spanish Game" => "Partie espagnole",
                "Three Knights Opening" => "Ouverture des trois cavaliers",
                "Torre Attack" => "Attaque Torre",
                "Trompowsky Attack" => "Attaque Trompowsky",
                "Two Knights Defense" => "Défense des deux cavaliers",
                "Van Geet Opening" => "Ouverture Van Geet",
                "Van't Kruijs Opening" => "Ouverture Van't Kruijs",
                "Vienna Game" => "Partie viennoise",
                "Ware Opening" => "Ouverture Ware",
                "Zukertort Opening" => "Ouverture Zukertort",
                "Benko Gambit" => "Gambit Benko",
                "Benoni" => "Benoni",
                "Czech Benoni" => "Benoni tchèque",
                "Colle System" => "Système Colle",
                "Danish Gambit" => "Gambit danois",
                "Evans Gambit" => "Gambit Evans",
                "King's Gambit Accepted" => "Gambit du Roi accepté",
                "King's Gambit Declined" => "Gambit du Roi décliné",
                "Latvian Gambit" => "Gambit letton",
                "Ponziani Game" => "Partie Ponziani",
                "Queen's Gambit Refused" => "Gambit Dame refusé",
                "Russian Game" => "Partie russe",
                "Scotch Opening" => "Ouverture écossaise",
                "Semi-Tarrasch Defense" => "Défense semi-Tarrasch",
                "Smith-Morra Gambit" => "Gambit Morra",
                "Tarrasch Defense" => "Défense Tarrasch",
                "Veresov Opening" => "Ouverture Veresov",
            ],
            'structural_terms' => [
                'Variation' => 'variante',
                'Attack' => 'attaque',
                'Gambit' => 'gambit',
                'Game' => 'partie',
                'Opening' => 'ouverture',
                'Defense' => 'défense',
                'Defence' => 'défense',
                'System' => 'système',
                'Countergambit' => 'contre-gambit',
                'Counter-Gambit' => 'contre-gambit',
                'Line' => 'ligne',
                'Accepted' => 'accepté',
                'Declined' => 'décliné',
                'Deferred' => 'différé',
                'Reversed' => 'inversé',
                'Classical' => 'classique',
                'Modern' => 'moderne',
                'Main Line' => 'ligne principale',
            ],
        ];
    }
}
