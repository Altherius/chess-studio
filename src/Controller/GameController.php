<?php

namespace App\Controller;

use App\Entity\Game;
use App\Repository\GameRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/games')]
class GameController extends AbstractController
{
    #[Route('', methods: ['GET'])]
    public function index(GameRepository $repository): JsonResponse
    {
        $games = $repository->findBy([], ['createdAt' => 'DESC']);

        return $this->json(array_map(fn(Game $g) => [
            'id' => $g->getId(),
            'playerWhite' => $g->getPlayerWhite(),
            'playerBlack' => $g->getPlayerBlack(),
            'result' => $g->getResult(),
            'event' => $g->getEvent(),
            'date' => $g->getDate()?->format('Y-m-d'),
            'createdAt' => $g->getCreatedAt()->format('c'),
        ], $games));
    }

    #[Route('/{id}', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Game $game): JsonResponse
    {
        return $this->json([
            'id' => $game->getId(),
            'pgn' => $game->getPgn(),
            'playerWhite' => $game->getPlayerWhite(),
            'playerBlack' => $game->getPlayerBlack(),
            'result' => $game->getResult(),
            'event' => $game->getEvent(),
            'date' => $game->getDate()?->format('Y-m-d'),
            'createdAt' => $game->getCreatedAt()->format('c'),
            'analyses' => array_map(fn($a) => [
                'id' => $a->getId(),
                'depth' => $a->getDepth(),
                'status' => $a->getStatus(),
                'evaluation' => $a->getEvaluation(),
                'bestMoves' => $a->getBestMoves(),
            ], $game->getAnalyses()->toArray()),
        ]);
    }

    #[Route('/import', methods: ['POST'])]
    public function import(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $pgn = $data['pgn'] ?? null;

        if (!$pgn) {
            return $this->json(['error' => 'PGN is required'], Response::HTTP_BAD_REQUEST);
        }

        $game = new Game();
        $game->setPgn($pgn);

        // Parse PGN headers
        if (preg_match('/\[White "(.+?)"\]/', $pgn, $m)) {
            $game->setPlayerWhite($m[1]);
        }
        if (preg_match('/\[Black "(.+?)"\]/', $pgn, $m)) {
            $game->setPlayerBlack($m[1]);
        }
        if (preg_match('/\[Result "(.+?)"\]/', $pgn, $m)) {
            $game->setResult($m[1]);
        }
        if (preg_match('/\[Event "(.+?)"\]/', $pgn, $m)) {
            $game->setEvent($m[1]);
        }
        if (preg_match('/\[Date "(\d{4}\.\d{2}\.\d{2})"\]/', $pgn, $m)) {
            $dateStr = str_replace('.', '-', $m[1]);
            $game->setDate(new \DateTime($dateStr));
        }

        $em->persist($game);
        $em->flush();

        return $this->json([
            'id' => $game->getId(),
            'playerWhite' => $game->getPlayerWhite(),
            'playerBlack' => $game->getPlayerBlack(),
            'result' => $game->getResult(),
        ], Response::HTTP_CREATED);
    }

    #[Route('/{id}', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(Game $game, EntityManagerInterface $em): JsonResponse
    {
        $em->remove($game);
        $em->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}
