<?php

namespace App\Controller;

use App\Entity\Analysis;
use App\Entity\Game;
use App\Message\AnalyzeGameMessage;
use App\Repository\AnalysisRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/analysis')]
class AnalysisController extends AbstractController
{
    #[Route('/game/{id}', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function analyze(Game $game, Request $request, MessageBusInterface $bus): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $depth = $data['depth'] ?? 30;

        $bus->dispatch(new AnalyzeGameMessage($game->getId(), $depth));

        return $this->json([
            'message' => 'Analyse commencée',
            'gameId' => $game->getId(),
            'depth' => $depth,
        ], Response::HTTP_ACCEPTED);
    }

    #[Route('/game/{id}', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function results(Game $game, AnalysisRepository $repository): JsonResponse
    {
        $analyses = $repository->findBy(
            ['game' => $game],
            ['createdAt' => 'DESC'],
        );

        return $this->json(array_map(fn(Analysis $a) => [
            'id' => $a->getId(),
            'depth' => $a->getDepth(),
            'status' => $a->getStatus(),
            'evaluation' => $a->getEvaluation(),
            'bestMoves' => $a->getBestMoves(),
            'createdAt' => $a->getCreatedAt()->format('c'),
        ], $analyses));
    }
}
