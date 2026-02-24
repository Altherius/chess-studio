<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\UserCreationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/users')]
class AdminUserController extends AbstractController
{
    #[Route('', methods: ['GET'])]
    public function list(Request $request, UserRepository $userRepository): JsonResponse
    {
        $offset = max(0, (int) $request->query->get('offset', 0));
        $limit = min(100, max(1, (int) $request->query->get('limit', 50)));
        $search = trim((string) $request->query->get('search', ''));

        $users = $userRepository->findPaginated($offset, $limit, $search);
        $total = $userRepository->countFiltered($search);

        return $this->json([
            'items' => array_map(fn(User $u) => [
                'id' => $u->getId(),
                'email' => $u->getEmail(),
                'firstName' => $u->getFirstName(),
                'lastName' => $u->getLastName(),
                'active' => $u->isActive(),
                'roles' => $u->getRoles(),
            ], $users),
            'total' => $total,
            'offset' => $offset,
            'limit' => $limit,
        ]);
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $request, UserCreationService $userCreation): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        try {
            $user = $userCreation->createUser(
                $data['email'] ?? '',
                $data['firstName'] ?? '',
                $data['lastName'] ?? '',
            );
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_CONFLICT);
        }

        return $this->json([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'firstName' => $user->getFirstName(),
            'lastName' => $user->getLastName(),
        ], Response::HTTP_CREATED);
    }

    #[Route('/import', methods: ['POST'])]
    public function import(Request $request, UserCreationService $userCreation): JsonResponse
    {
        $file = $request->files->get('file');
        if (!$file) {
            return $this->json(['error' => 'Aucun fichier fourni.'], Response::HTTP_BAD_REQUEST);
        }

        $handle = fopen($file->getPathname(), 'r');
        if (!$handle) {
            return $this->json(['error' => 'Impossible de lire le fichier.'], Response::HTTP_BAD_REQUEST);
        }

        $created = 0;
        $errors = [];
        $row = 0;

        // escape parameter should be provided to avoid deprecation in PHP 8.4 as its value may change
        while (($line = fgetcsv($handle, escape: '\\')) !== false) {
            $row++;

            if ($row === 1 && isset($line[0]) && strtolower(trim($line[0])) === 'email') {
                continue;
            }

            try {
                $userCreation->createUser(
                    $line[0] ?? '',
                    $line[2] ?? '',
                    $line[1] ?? '',
                );
                $created++;
            } catch (\InvalidArgumentException|\DomainException $e) {
                $errors[] = ['row' => $row, 'email' => trim($line[0] ?? ''), 'error' => $e->getMessage()];
            }
        }

        fclose($handle);

        return $this->json(['created' => $created, 'errors' => $errors]);
    }

    #[Route('/{id}/toggle-active', methods: ['PATCH'])]
    public function toggleActive(User $user, EntityManagerInterface $em): JsonResponse
    {
        $user->setActive(!$user->isActive());
        $em->flush();

        return $this->json([
            'id' => $user->getId(),
            'active' => $user->isActive(),
        ]);
    }
}
