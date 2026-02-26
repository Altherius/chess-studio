<?php

namespace App\Controller;

use App\DTO\GameFormInput;
use App\Entity\Game;
use App\Repository\GameRepository;
use App\Service\FormImportService;
use App\Service\OpeningDetectionService;
use App\Service\PgnImportService;
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
            'whiteElo' => $g->getWhiteElo(),
            'blackElo' => $g->getBlackElo(),
            'round' => $g->getRound(),
            'openingName' => $g->getOpeningName(),
        ];
    }

    private function extractFilters(Request $request): array
    {
        $filters = [];
        foreach (['minElo', 'maxElo', 'player', 'event', 'opening', 'minDate', 'maxDate'] as $key) {
            $value = $request->query->get($key);
            if ($value !== null && $value !== '') {
                $filters[$key] = $value;
            }
        }
        return $filters;
    }

    private function persistGame(
        Game $game,
        bool $isPublic,
        EntityManagerInterface $em,
        OpeningDetectionService $openingDetection,
    ): JsonResponse {
        $game->setOwner($this->getUser());
        $game->setIsPublic($isPublic);
        $game->setOpeningName($openingDetection->detectFromPgn($game->getPgn()));

        $em->persist($game);
        $em->flush();

        return $this->json([
            'id' => $game->getId(),
            'playerWhite' => $game->getPlayerWhite(),
            'playerBlack' => $game->getPlayerBlack(),
            'result' => $game->getResult(),
        ], Response::HTTP_CREATED);
    }

    #[Route('', methods: ['GET'])]
    public function index(Request $request, GameRepository $repository): JsonResponse
    {
        $user = $this->getUser();
        $offset = max(0, (int) $request->query->get('offset', 0));
        $limit = min(100, max(1, (int) $request->query->get('limit', 50)));

        $criteria = $this->extractFilters($request);
        $criteria['owner'] = $user;

        $games = $repository->findFiltered($criteria, $offset, $limit);
        $total = $repository->countFiltered($criteria);

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
        $limit = min(100, max(1, (int) $request->query->get('limit', 50)));

        $criteria = $this->extractFilters($request);
        $criteria['isPublic'] = true;

        $games = $repository->findFiltered($criteria, $offset, $limit);
        $total = $repository->countFiltered($criteria);

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
            'openingName' => $game->getOpeningName(),
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
    public function import(
        Request $request,
        PgnImportService $pgnImportService,
        EntityManagerInterface $em,
        OpeningDetectionService $openingDetection,
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $pgn = $data['pgn'] ?? null;

        if (!$pgn) {
            return $this->json(['error' => 'PGN is required'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $game = $pgnImportService->createGameFromPgn($pgn);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return $this->persistGame($game, $data['isPublic'] ?? true, $em, $openingDetection);
    }

    #[Route('/import/file', methods: ['POST'])]
    public function importFile(
        Request $request,
        PgnImportService $pgnImportService,
        EntityManagerInterface $em,
        OpeningDetectionService $openingDetection,
    ): JsonResponse {
        $file = $request->files->get('pgn');

        if (!$file) {
            return $this->json(['error' => 'No file uploaded'], Response::HTTP_BAD_REQUEST);
        }

        if ($file->getSize() > 1024 * 1024) {
            return $this->json(['error' => 'File too large (max 1 MB)'], Response::HTTP_BAD_REQUEST);
        }

        $extension = strtolower($file->getClientOriginalExtension());
        if ($extension !== 'pgn') {
            return $this->json(['error' => 'Only .pgn files are accepted'], Response::HTTP_BAD_REQUEST);
        }

        $content = file_get_contents($file->getPathname());
        if ($content === false || trim($content) === '') {
            return $this->json(['error' => 'File is empty or unreadable'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $game = $pgnImportService->createGameFromPgn($content);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        $isPublic = filter_var(
            $request->request->get('isPublic', 'true'),
            FILTER_VALIDATE_BOOLEAN,
        );

        return $this->persistGame($game, $isPublic, $em, $openingDetection);
    }

    #[Route('/import/form', methods: ['POST'])]
    public function importForm(
        Request $request,
        FormImportService $formImportService,
        EntityManagerInterface $em,
        OpeningDetectionService $openingDetection,
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        $moves = $data['moves'] ?? null;
        if (!$moves || trim($moves) === '') {
            return $this->json(['error' => 'Moves are required'], Response::HTTP_BAD_REQUEST);
        }

        $input = new GameFormInput(
            moves: $moves,
            event: $data['event'] ?? null,
            date: $data['date'] ?? null,
            round: $data['round'] ?? null,
            result: $data['result'] ?? null,
            playerWhite: $data['playerWhite'] ?? null,
            playerBlack: $data['playerBlack'] ?? null,
            whiteElo: isset($data['whiteElo']) ? (int) $data['whiteElo'] : null,
            blackElo: isset($data['blackElo']) ? (int) $data['blackElo'] : null,
        );

        try {
            $game = $formImportService->createGameFromForm($input);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return $this->persistGame($game, $data['isPublic'] ?? true, $em, $openingDetection);
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
