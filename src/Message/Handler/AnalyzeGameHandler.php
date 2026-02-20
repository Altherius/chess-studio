<?php

namespace App\Message\Handler;

use App\Entity\Analysis;
use App\Message\AnalyzeGameMessage;
use App\Repository\GameRepository;
use App\Service\StockfishService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class AnalyzeGameHandler
{
    public function __construct(
        private GameRepository $gameRepository,
        private StockfishService $stockfishService,
        private EntityManagerInterface $em,
        private HubInterface $hub,
    ) {}

    public function __invoke(AnalyzeGameMessage $message): void
    {
        $game = $this->gameRepository->find($message->getGameId());

        if (!$game) {
            return;
        }

        $analysis = new Analysis();
        $analysis->setGame($game);
        $analysis->setDepth($message->getDepth());
        $analysis->setStatus(Analysis::STATUS_RUNNING);
        $this->em->persist($analysis);
        $this->em->flush();

        try {
            // Parse PGN to get positions - this is a simplified approach
            // In production, use a proper PGN parser to extract FEN positions
            $positions = $this->extractPositionsFromPgn($game->getPgn());
            $results = $this->stockfishService->analyzeGame($positions, $message->getDepth());

            $evaluation = [];
            $bestMoves = [];
            foreach ($results as $index => $result) {
                $evaluation[$index] = $result['score'];
                $bestMoves[$index] = $result['bestMove'];
            }

            $analysis->setEvaluation($evaluation);
            $analysis->setBestMoves($bestMoves);
            $analysis->setStatus(Analysis::STATUS_COMPLETED);
        } catch (\Throwable $e) {
            $analysis->setStatus(Analysis::STATUS_FAILED);
        }

        $this->em->flush();

        $this->hub->publish(new Update(
            "game/{$game->getId()}/analysis",
            json_encode([
                'analysisId' => $analysis->getId(),
                'status' => $analysis->getStatus(),
                'evaluation' => $analysis->getEvaluation(),
                'bestMoves' => $analysis->getBestMoves(),
            ]),
        ));
    }

    /**
     * @return string[]
     */
    private function extractPositionsFromPgn(string $pgn): array
    {
        // Start from the standard initial position
        // A full implementation would replay each move and extract FEN after each move
        return ['rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1'];
    }
}
