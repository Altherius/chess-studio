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
    private function serializeGameSummary(Game $g): array
    {
        return [
            'id' => $g->getId(),
            'playerWhite' => $g->getPlayerWhite(),
            'playerBlack' => $g->getPlayerBlack(),
            'result' => $g->getResult(),
            'event' => $g->getEvent(),
            'date' => $g->getDate()?->format('Y-m-d'),
            'createdAt' => $g->getCreatedAt()->format('c'),
            'isPublic' => $g->isPublic(),
        ];
    }

    #[Route('', methods: ['GET'])]
    public function index(Request $request, GameRepository $repository): JsonResponse
    {
        $user = $this->getUser();
        $offset = max(0, (int) $request->query->get('offset', 0));
        $limit = min(100, max(1, (int) $request->query->get('limit', 20)));

        $games = $repository->findBy(
            ['owner' => $user],
            ['createdAt' => 'DESC'],
            $limit,
            $offset,
        );

        $total = $repository->count(['owner' => $user]);

        return $this->json([
            'items' => array_map($this->serializeGameSummary(...), $games),
            'total' => $total,
            'offset' => $offset,
            'limit' => $limit,
        ]);
    }

    #[Route('/public', methods: ['GET'])]
    public function publicGames(Request $request, GameRepository $repository): JsonResponse
    {
        $offset = max(0, (int) $request->query->get('offset', 0));
        $limit = min(100, max(1, (int) $request->query->get('limit', 20)));

        $games = $repository->findBy(
            ['isPublic' => true],
            ['createdAt' => 'DESC'],
            $limit,
            $offset,
        );

        $total = $repository->count(['isPublic' => true]);

        return $this->json([
            'items' => array_map($this->serializeGameSummary(...), $games),
            'total' => $total,
            'offset' => $offset,
            'limit' => $limit,
        ]);
    }

    #[Route('/{id}', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Game $game): JsonResponse
    {
        $isOwner = $game->getOwner() === $this->getUser();
        if (!$isOwner && !$game->isPublic()) {
            return $this->json(['error' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }

        return $this->json([
            'id' => $game->getId(),
            'pgn' => $game->getPgn(),
            'playerWhite' => $game->getPlayerWhite(),
            'playerBlack' => $game->getPlayerBlack(),
            'result' => $game->getResult(),
            'event' => $game->getEvent(),
            'date' => $game->getDate()?->format('Y-m-d'),
            'createdAt' => $game->getCreatedAt()->format('c'),
            'isPublic' => $game->isPublic(),
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
        $game->setOwner($this->getUser());
        $game->setIsPublic($data['isPublic'] ?? true);

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
        if ($game->getOwner() !== $this->getUser()) {
            return $this->json(['error' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }

        $em->remove($game);
        $em->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}
