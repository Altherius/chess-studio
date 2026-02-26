<?php

namespace App\Command;

use App\Entity\Game;
use App\Service\OpeningDetectionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:backfill-openings',
    description: 'Détecte l\'ouverture pour les parties existantes sans ouverture',
)]
class BackfillOpeningsCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly OpeningDetectionService $openingDetection,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('force', null, InputOption::VALUE_NONE, 'Recalculer même si une ouverture existe déjà');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $force = $input->getOption('force');

        $qb = $this->em->getRepository(Game::class)->createQueryBuilder('g');
        if (!$force) {
            $qb->where('g.openingName IS NULL');
        }

        $games = $qb->getQuery()->toIterable();
        $updated = 0;
        $total = 0;

        foreach ($games as $game) {
            $total++;
            $openingName = $this->openingDetection->detectFromPgn($game->getPgn());

            if ($openingName !== null) {
                $game->setOpeningName($openingName);
                $updated++;
            }

            if ($total % 100 === 0) {
                $this->em->flush();
                $io->info("{$total} parties traitées...");
            }
        }

        $this->em->flush();

        $io->success("{$updated} ouvertures détectées sur {$total} parties.");

        return Command::SUCCESS;
    }
}
